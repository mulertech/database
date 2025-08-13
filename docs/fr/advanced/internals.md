# Architecture Interne

Guide détaillé de l'architecture interne de MulerTech Database ORM pour les développeurs avancés et contributeurs.

## Table des Matières
- [Vue d'ensemble architecturale](#vue-densemble-architecturale)
- [Couches et composants](#couches-et-composants)
- [Cycle de vie des entités](#cycle-de-vie-des-entités)
- [Système de métadonnées](#système-de-métadonnées)
- [Moteur de requêtes](#moteur-de-requêtes)
- [Optimisations internes](#optimisations-internes)

## Vue d'ensemble architecturale

### Architecture en couches

```
┌─────────────────────────────────────────────────┐
│                 Application Layer                │
│              (User Entities & Logic)            │
├─────────────────────────────────────────────────┤
│                   ORM Layer                     │
│  EntityManager │ Repositories │ Query Builder   │
├─────────────────────────────────────────────────┤
│                Mapping Layer                    │
│  Metadata │ Type System │ Change Detection      │
├─────────────────────────────────────────────────┤
│              Abstraction Layer                  │
│    Drivers │ Connections │ Transactions         │
├─────────────────────────────────────────────────┤
│                Database Layer                   │
│              (MySQL, PostgreSQL...)             │
└─────────────────────────────────────────────────┘
```

### Composants principaux

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Core;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class ArchitectureMap
{
    /** @var array<string, array<string, string>> */
    private const COMPONENT_MAP = [
        'core' => [
            'EntityManager' => 'MulerTech\\Database\\ORM\\EntityManager',
            'EmEngine' => 'MulerTech\\Database\\ORM\\EmEngine',
            'Configuration' => 'MulerTech\\Database\\Configuration\\Configuration',
        ],
        'mapping' => [
            'MetadataRegistry' => 'MulerTech\\Database\\Mapping\\MetadataRegistry',
            'EntityProcessor' => 'MulerTech\\Database\\Mapping\\EntityProcessor',
            'AttributeProcessor' => 'MulerTech\\Database\\Mapping\\AttributeProcessor',
        ],
        'persistence' => [
            'ChangeDetector' => 'MulerTech\\Database\\ORM\\ChangeDetector',
            'ChangeSetManager' => 'MulerTech\\Database\\ORM\\ChangeSetManager',
            'UnitOfWork' => 'MulerTech\\Database\\ORM\\UnitOfWork',
        ],
        'query' => [
            'QueryBuilder' => 'MulerTech\\Database\\Query\\QueryBuilder',
            'SqlGenerator' => 'MulerTech\\Database\\Query\\SqlGenerator',
            'ResultMapper' => 'MulerTech\\Database\\Query\\ResultMapper',
        ],
        'database' => [
            'ConnectionManager' => 'MulerTech\\Database\\Connection\\ConnectionManager',
            'DriverManager' => 'MulerTech\\Database\\Driver\\DriverManager',
            'TransactionManager' => 'MulerTech\\Database\\Transaction\\TransactionManager',
        ],
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function getComponentMap(): array
    {
        return self::COMPONENT_MAP;
    }

    /**
     * @param string $layer
     * @return array<string, string>
     */
    public static function getLayerComponents(string $layer): array
    {
        return self::COMPONENT_MAP[$layer] ?? [];
    }
}
```

## Couches et composants

### Couche ORM

#### EntityManager - Façade principale

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Configuration\Configuration;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Connection\ConnectionInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityManager
{
    private EmEngine $engine;
    private MetadataRegistry $metadataRegistry;
    private ConnectionInterface $connection;
    private UnitOfWork $unitOfWork;
    private ChangeDetector $changeDetector;

    /**
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->connection = $configuration->createConnection();
        $this->metadataRegistry = new MetadataRegistry();
        $this->changeDetector = new ChangeDetector($this->metadataRegistry);
        $this->unitOfWork = new UnitOfWork($this->connection, $this->metadataRegistry);
        $this->engine = new EmEngine($this, $this->unitOfWork, $this->changeDetector);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function persist(object $entity): void
    {
        $this->engine->schedulePersist($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->engine->scheduleRemove($entity);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->engine->executeScheduledOperations();
    }

    /**
     * @param string $entityClass
     * @param mixed $id
     * @return object|null
     */
    public function find(string $entityClass, mixed $id): ?object
    {
        return $this->engine->loadEntity($entityClass, $id);
    }

    /**
     * @return EmEngine
     */
    public function getEngine(): EmEngine
    {
        return $this->engine;
    }

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    /**
     * @return ChangeDetector
     */
    public function getChangeDetector(): ChangeDetector
    {
        return $this->changeDetector;
    }
}
```

#### EmEngine - Moteur de persistence

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Event\EventDispatcherInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EmEngine
{
    private EntityManager $entityManager;
    private UnitOfWork $unitOfWork;
    private ChangeDetector $changeDetector;
    private EventDispatcherInterface $eventDispatcher;
    
    /** @var array<string, object> */
    private array $identityMap = [];
    
    /** @var array<string, object> */
    private array $scheduledInserts = [];
    
    /** @var array<string, object> */
    private array $scheduledUpdates = [];
    
    /** @var array<string, object> */
    private array $scheduledDeletes = [];

    /**
     * @param EntityManager $entityManager
     * @param UnitOfWork $unitOfWork
     * @param ChangeDetector $changeDetector
     */
    public function __construct(
        EntityManager $entityManager,
        UnitOfWork $unitOfWork,
        ChangeDetector $changeDetector
    ) {
        $this->entityManager = $entityManager;
        $this->unitOfWork = $unitOfWork;
        $this->changeDetector = $changeDetector;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function schedulePersist(object $entity): void
    {
        $oid = spl_object_id($entity);
        
        if ($this->isEntityManaged($entity)) {
            return; // Already managed
        }

        $this->scheduledInserts[$oid] = $entity;
        $this->addToIdentityMap($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleUpdate(object $entity): void
    {
        $oid = spl_object_id($entity);
        
        if (!$this->isEntityManaged($entity)) {
            return; // Not managed
        }

        if (!isset($this->scheduledUpdates[$oid])) {
            $this->scheduledUpdates[$oid] = $entity;
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleRemove(object $entity): void
    {
        $oid = spl_object_id($entity);
        
        if (!$this->isEntityManaged($entity)) {
            return; // Not managed
        }

        $this->scheduledDeletes[$oid] = $entity;
        unset($this->scheduledInserts[$oid], $this->scheduledUpdates[$oid]);
    }

    /**
     * @return void
     */
    public function executeScheduledOperations(): void
    {
        $this->detectChanges();
        
        $this->unitOfWork->beginTransaction();
        
        try {
            $this->executeInserts();
            $this->executeUpdates();
            $this->executeDeletes();
            
            $this->unitOfWork->commit();
            $this->clearScheduledOperations();
        } catch (\Throwable $e) {
            $this->unitOfWork->rollback();
            throw $e;
        }
    }

    /**
     * @param string $entityClass
     * @param mixed $id
     * @return object|null
     */
    public function loadEntity(string $entityClass, mixed $id): ?object
    {
        $identityKey = $this->generateIdentityKey($entityClass, $id);
        
        // Check identity map first
        if (isset($this->identityMap[$identityKey])) {
            return $this->identityMap[$identityKey];
        }

        // Load from database
        $entity = $this->unitOfWork->loadEntity($entityClass, $id);
        
        if ($entity !== null) {
            $this->addToIdentityMap($entity);
        }

        return $entity;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isEntityManaged(object $entity): bool
    {
        $identityKey = $this->getEntityIdentityKey($entity);
        return isset($this->identityMap[$identityKey]);
    }

    /**
     * @return void
     */
    private function detectChanges(): void
    {
        foreach ($this->identityMap as $entity) {
            if ($this->changeDetector->hasChanges($entity)) {
                $this->scheduleUpdate($entity);
            }
        }
    }

    /**
     * @return void
     */
    private function executeInserts(): void
    {
        foreach ($this->scheduledInserts as $entity) {
            $this->unitOfWork->persistEntity($entity);
        }
    }

    /**
     * @return void
     */
    private function executeUpdates(): void
    {
        foreach ($this->scheduledUpdates as $entity) {
            $changeSet = $this->changeDetector->getChangeSet($entity);
            $this->unitOfWork->updateEntity($entity, $changeSet);
        }
    }

    /**
     * @return void
     */
    private function executeDeletes(): void
    {
        foreach ($this->scheduledDeletes as $entity) {
            $this->unitOfWork->removeEntity($entity);
            $this->removeFromIdentityMap($entity);
        }
    }

    /**
     * @return void
     */
    private function clearScheduledOperations(): void
    {
        $this->scheduledInserts = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletes = [];
    }

    /**
     * @param object $entity
     * @return void
     */
    private function addToIdentityMap(object $entity): void
    {
        $identityKey = $this->getEntityIdentityKey($entity);
        $this->identityMap[$identityKey] = $entity;
    }

    /**
     * @param object $entity
     * @return void
     */
    private function removeFromIdentityMap(object $entity): void
    {
        $identityKey = $this->getEntityIdentityKey($entity);
        unset($this->identityMap[$identityKey]);
    }

    /**
     * @param object $entity
     * @return string
     */
    private function getEntityIdentityKey(object $entity): string
    {
        $className = get_class($entity);
        $id = $this->extractEntityId($entity);
        return $this->generateIdentityKey($className, $id);
    }

    /**
     * @param string $entityClass
     * @param mixed $id
     * @return string
     */
    private function generateIdentityKey(string $entityClass, mixed $id): string
    {
        return $entityClass . '#' . (string)$id;
    }

    /**
     * @param object $entity
     * @return mixed
     */
    private function extractEntityId(object $entity): mixed
    {
        // Simplified - in real implementation, use metadata
        return method_exists($entity, 'getId') ? $entity->getId() : null;
    }
}
```

### Couche de mapping

#### MetadataRegistry - Registre des métadonnées

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Exception\MappingException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MetadataRegistry
{
    /** @var array<string, EntityMetadata> */
    private array $metadata = [];
    
    /** @var array<string, bool> */
    private array $loadedClasses = [];
    
    private EntityProcessor $processor;

    public function __construct()
    {
        $this->processor = new EntityProcessor();
    }

    /**
     * @param string $entityClass
     * @return EntityMetadata
     * @throws MappingException
     */
    public function getMetadata(string $entityClass): EntityMetadata
    {
        if (!isset($this->metadata[$entityClass])) {
            $this->loadMetadata($entityClass);
        }

        return $this->metadata[$entityClass];
    }

    /**
     * @param string $entityClass
     * @return bool
     */
    public function hasMetadata(string $entityClass): bool
    {
        return isset($this->metadata[$entityClass]) || $this->canLoadMetadata($entityClass);
    }

    /**
     * @param string $entityClass
     * @return void
     * @throws MappingException
     */
    private function loadMetadata(string $entityClass): void
    {
        if (isset($this->loadedClasses[$entityClass])) {
            return;
        }

        if (!class_exists($entityClass)) {
            throw new MappingException("Entity class '{$entityClass}' does not exist");
        }

        $metadata = $this->processor->processEntity($entityClass);
        $this->metadata[$entityClass] = $metadata;
        $this->loadedClasses[$entityClass] = true;
    }

    /**
     * @param string $entityClass
     * @return bool
     */
    private function canLoadMetadata(string $entityClass): bool
    {
        return class_exists($entityClass) && $this->processor->isEntity($entityClass);
    }

    /**
     * @return array<string, EntityMetadata>
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        $this->metadata = [];
        $this->loadedClasses = [];
    }
}
```

#### ChangeDetector - Détection des modifications

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Mapping\MetadataRegistry;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ChangeDetector
{
    private MetadataRegistry $metadataRegistry;
    
    /** @var array<string, array<string, mixed>> */
    private array $originalData = [];

    /**
     * @param MetadataRegistry $metadataRegistry
     */
    public function __construct(MetadataRegistry $metadataRegistry)
    {
        $this->metadataRegistry = $metadataRegistry;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function takeSnapshot(object $entity): void
    {
        $oid = spl_object_id($entity);
        $this->originalData[$oid] = $this->extractEntityData($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool
    {
        $oid = spl_object_id($entity);
        
        if (!isset($this->originalData[$oid])) {
            return true; // New entity
        }

        $currentData = $this->extractEntityData($entity);
        $originalData = $this->originalData[$oid];

        return $currentData !== $originalData;
    }

    /**
     * @param object $entity
     * @return ChangeSet
     */
    public function getChangeSet(object $entity): ChangeSet
    {
        $oid = spl_object_id($entity);
        $currentData = $this->extractEntityData($entity);
        $originalData = $this->originalData[$oid] ?? [];

        $changes = [];
        
        foreach ($currentData as $field => $currentValue) {
            $originalValue = $originalData[$field] ?? null;
            
            if (!$this->isEqual($currentValue, $originalValue)) {
                $changes[$field] = [$originalValue, $currentValue];
            }
        }

        return new ChangeSet($entity, $changes);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function clearSnapshot(object $entity): void
    {
        $oid = spl_object_id($entity);
        unset($this->originalData[$oid]);
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    private function extractEntityData(object $entity): array
    {
        $metadata = $this->metadataRegistry->getMetadata(get_class($entity));
        $data = [];

        foreach ($metadata->getFields() as $fieldName => $fieldMapping) {
            $value = $this->getPropertyValue($entity, $fieldName);
            $data[$fieldName] = $this->normalizeValue($value);
        }

        return $data;
    }

    /**
     * @param object $entity
     * @param string $property
     * @return mixed
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $reflection = new \ReflectionClass($entity);
        
        if ($reflection->hasProperty($property)) {
            $reflectionProperty = $reflection->getProperty($property);
            $reflectionProperty->setAccessible(true);
            return $reflectionProperty->getValue($entity);
        }

        $getter = 'get' . ucfirst($property);
        if ($reflection->hasMethod($getter)) {
            return $entity->$getter();
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u');
        }

        if (is_object($value)) {
            return spl_object_id($value);
        }

        return $value;
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private function isEqual(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        // Special handling for floating point numbers
        if (is_float($a) && is_float($b)) {
            return abs($a - $b) < PHP_FLOAT_EPSILON;
        }

        return false;
    }
}
```

## Cycle de vie des entités

### États des entités

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum EntityState: string
{
    case NEW = 'new';
    case MANAGED = 'managed';
    case REMOVED = 'removed';
    case DETACHED = 'detached';
}
```

### Gestionnaire d'état

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityStateManager
{
    /** @var array<string, EntityState> */
    private array $entityStates = [];

    /**
     * @param object $entity
     * @return EntityState
     */
    public function getEntityState(object $entity): EntityState
    {
        $oid = spl_object_id($entity);
        return $this->entityStates[$oid] ?? EntityState::NEW;
    }

    /**
     * @param object $entity
     * @param EntityState $state
     * @return void
     */
    public function setEntityState(object $entity, EntityState $state): void
    {
        $oid = spl_object_id($entity);
        $this->entityStates[$oid] = $state;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityState::MANAGED;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isNew(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityState::NEW;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isRemoved(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityState::REMOVED;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isDetached(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityState::DETACHED;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function removeEntity(object $entity): void
    {
        $oid = spl_object_id($entity);
        unset($this->entityStates[$oid]);
    }
}
```

## Système de métadonnées

### Cache de métadonnées

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use Psr\Cache\CacheItemPoolInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MetadataCache
{
    private CacheItemPoolInterface $cache;
    private int $ttl;

    /**
     * @param CacheItemPoolInterface $cache
     * @param int $ttl
     */
    public function __construct(CacheItemPoolInterface $cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    /**
     * @param string $entityClass
     * @return EntityMetadata|null
     */
    public function getMetadata(string $entityClass): ?EntityMetadata
    {
        $cacheKey = $this->generateCacheKey($entityClass);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        return null;
    }

    /**
     * @param string $entityClass
     * @param EntityMetadata $metadata
     * @return void
     */
    public function setMetadata(string $entityClass, EntityMetadata $metadata): void
    {
        $cacheKey = $this->generateCacheKey($entityClass);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        $cacheItem->set($metadata);
        $cacheItem->expiresAfter($this->ttl);
        
        $this->cache->save($cacheItem);
    }

    /**
     * @param string $entityClass
     * @return bool
     */
    public function hasMetadata(string $entityClass): bool
    {
        $cacheKey = $this->generateCacheKey($entityClass);
        return $this->cache->hasItem($cacheKey);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @param string $entityClass
     * @return string
     */
    private function generateCacheKey(string $entityClass): string
    {
        return 'metadata_' . str_replace('\\', '_', $entityClass);
    }
}
```

## Moteur de requêtes

### Générateur SQL

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\Platform\PlatformInterface;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SqlGenerator
{
    private PlatformInterface $platform;

    /**
     * @param PlatformInterface $platform
     */
    public function __construct(PlatformInterface $platform)
    {
        $this->platform = $platform;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return string
     */
    public function generateSelect(QueryBuilder $queryBuilder): string
    {
        $sql = 'SELECT ';
        
        $sql .= $this->buildSelectClause($queryBuilder);
        $sql .= $this->buildFromClause($queryBuilder);
        $sql .= $this->buildJoinClause($queryBuilder);
        $sql .= $this->buildWhereClause($queryBuilder);
        $sql .= $this->buildGroupByClause($queryBuilder);
        $sql .= $this->buildHavingClause($queryBuilder);
        $sql .= $this->buildOrderByClause($queryBuilder);
        $sql .= $this->buildLimitClause($queryBuilder);

        return $sql;
    }

    /**
     * @param string $table
     * @param array<string, mixed> $data
     * @return string
     */
    public function generateInsert(string $table, array $data): string
    {
        $quotedTable = $this->platform->quoteIdentifier($table);
        $columns = array_map([$this->platform, 'quoteIdentifier'], array_keys($data));
        $placeholders = array_fill(0, count($data), '?');

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $quotedTable,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
    }

    /**
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @return string
     */
    public function generateUpdate(string $table, array $data, array $where): string
    {
        $quotedTable = $this->platform->quoteIdentifier($table);
        
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = $this->platform->quoteIdentifier($column) . ' = ?';
        }

        $whereParts = [];
        foreach (array_keys($where) as $column) {
            $whereParts[] = $this->platform->quoteIdentifier($column) . ' = ?';
        }

        return sprintf(
            'UPDATE %s SET %s WHERE %s',
            $quotedTable,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );
    }

    /**
     * @param string $table
     * @param array<string, mixed> $where
     * @return string
     */
    public function generateDelete(string $table, array $where): string
    {
        $quotedTable = $this->platform->quoteIdentifier($table);
        
        $whereParts = [];
        foreach (array_keys($where) as $column) {
            $whereParts[] = $this->platform->quoteIdentifier($column) . ' = ?';
        }

        return sprintf(
            'DELETE FROM %s WHERE %s',
            $quotedTable,
            implode(' AND ', $whereParts)
        );
    }

    /**
     * @param QueryBuilder $qb
     * @return string
     */
    private function buildSelectClause(QueryBuilder $qb): string
    {
        $select = $qb->getSelect();
        
        if (empty($select)) {
            return '*';
        }

        return implode(', ', $select);
    }

    /**
     * @param QueryBuilder $qb
     * @return string
     */
    private function buildFromClause(QueryBuilder $qb): string
    {
        $from = $qb->getFrom();
        
        if (empty($from)) {
            return '';
        }

        return ' FROM ' . implode(', ', $from);
    }

    /**
     * @param QueryBuilder $qb
     * @return string
     */
    private function buildWhereClause(QueryBuilder $qb): string
    {
        $where = $qb->getWhere();
        
        if (empty($where)) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $where);
    }

    /**
     * @param QueryBuilder $qb
     * @return string
     */
    private function buildOrderByClause(QueryBuilder $qb): string
    {
        $orderBy = $qb->getOrderBy();
        
        if (empty($orderBy)) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $orderBy);
    }

    /**
     * @param QueryBuilder $qb
     * @return string
     */
    private function buildLimitClause(QueryBuilder $qb): string
    {
        $limit = $qb->getMaxResults();
        $offset = $qb->getFirstResult();

        if ($limit === null && $offset === null) {
            return '';
        }

        return $this->platform->modifyLimitQuery('', $limit, $offset);
    }

    // Autres méthodes de construction...
}
```

## Optimisations internes

### Pool d'objets

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Core;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ObjectPool
{
    /** @var array<string, array<object>> */
    private array $pools = [];
    
    /** @var array<string, int> */
    private array $maxSizes = [];

    /**
     * @param string $className
     * @param int $maxSize
     * @return void
     */
    public function configure(string $className, int $maxSize = 100): void
    {
        $this->maxSizes[$className] = $maxSize;
        
        if (!isset($this->pools[$className])) {
            $this->pools[$className] = [];
        }
    }

    /**
     * @param string $className
     * @param array<mixed> $args
     * @return object
     */
    public function acquire(string $className, array $args = []): object
    {
        if (!empty($this->pools[$className])) {
            return array_pop($this->pools[$className]);
        }

        return new $className(...$args);
    }

    /**
     * @param object $object
     * @return void
     */
    public function release(object $object): void
    {
        $className = get_class($object);
        
        if (!isset($this->pools[$className])) {
            return;
        }

        $maxSize = $this->maxSizes[$className] ?? 100;
        
        if (count($this->pools[$className]) < $maxSize) {
            // Reset object state if needed
            if (method_exists($object, 'reset')) {
                $object->reset();
            }
            
            $this->pools[$className][] = $object;
        }
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->pools = [];
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        $stats = [];
        
        foreach ($this->pools as $className => $pool) {
            $stats[$className] = [
                'available' => count($pool),
                'max_size' => $this->maxSizes[$className] ?? 100,
            ];
        }
        
        return $stats;
    }
}
```

### Optimiseur de requêtes

```php
<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryOptimizer
{
    /** @var array<string, callable> */
    private array $optimizers = [];

    public function __construct()
    {
        $this->registerDefaultOptimizers();
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    public function optimize(QueryBuilder $queryBuilder): QueryBuilder
    {
        $optimized = clone $queryBuilder;
        
        foreach ($this->optimizers as $optimizer) {
            $optimized = $optimizer($optimized);
        }
        
        return $optimized;
    }

    /**
     * @param string $name
     * @param callable $optimizer
     * @return void
     */
    public function addOptimizer(string $name, callable $optimizer): void
    {
        $this->optimizers[$name] = $optimizer;
    }

    /**
     * @return void
     */
    private function registerDefaultOptimizers(): void
    {
        // Optimiseur pour les jointures redondantes
        $this->addOptimizer('redundant_joins', function (QueryBuilder $qb) {
            return $this->removeRedundantJoins($qb);
        });

        // Optimiseur pour les conditions WHERE
        $this->addOptimizer('where_conditions', function (QueryBuilder $qb) {
            return $this->optimizeWhereConditions($qb);
        });

        // Optimiseur pour les ORDER BY inutiles
        $this->addOptimizer('unnecessary_order', function (QueryBuilder $qb) {
            return $this->removeUnnecessaryOrderBy($qb);
        });
    }

    /**
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    private function removeRedundantJoins(QueryBuilder $qb): QueryBuilder
    {
        // Implementation logic for removing redundant joins
        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    private function optimizeWhereConditions(QueryBuilder $qb): QueryBuilder
    {
        // Implementation logic for optimizing WHERE conditions
        return $qb;
    }

    /**
     * @param QueryBuilder $qb
     * @return QueryBuilder
     */
    private function removeUnnecessaryOrderBy(QueryBuilder $qb): QueryBuilder
    {
        // Remove ORDER BY when using COUNT() without GROUP BY
        $select = $qb->getSelect();
        
        if (count($select) === 1 && stripos($select[0], 'COUNT(') === 0) {
            $groupBy = $qb->getGroupBy();
            if (empty($groupBy)) {
                $qb->resetQueryPart('orderBy');
            }
        }
        
        return $qb;
    }
}
```

---

**Voir aussi :**
- [Étendre l'ORM](extending-orm.md)
- [Types personnalisés](custom-types.md)
- [Système de plugins](plugins.md)
