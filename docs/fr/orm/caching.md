# Système de Cache

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Types de Cache](#types-de-cache)
- [Configuration du Cache](#configuration-du-cache)
- [Cache d'Entités](#cache-dentités)
- [Cache de Requêtes](#cache-de-requêtes)
- [Cache de Métadonnées](#cache-de-métadonnées)
- [Stratégies d'Invalidation](#stratégies-dinvalidation)
- [Performance et Monitoring](#performance-et-monitoring)

## Vue d'ensemble

Le système de cache de MulerTech Database améliore les performances en réduisant les accès à la base de données et en optimisant les opérations répétitives.

### Niveaux de Cache

```php
<?php
use MulerTech\Database\Core\Cache\CacheInterface;
use MulerTech\Database\Core\Cache\ArrayCache;
use MulerTech\Database\Core\Cache\RedisCache;

// Configuration multicouche
$l1Cache = new ArrayCache();     // Cache mémoire (rapide)
$l2Cache = new RedisCache();     // Cache persistant (partagé)

$cacheManager = new CacheManager();
$cacheManager->addLayer('memory', $l1Cache);
$cacheManager->addLayer('redis', $l2Cache);

$emEngine = new EmEngine($driver);
$emEngine->setCacheManager($cacheManager);
```

## Types de Cache

### Interface Cache

```php
<?php
namespace MulerTech\Database\Core\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function deleteByPattern(string $pattern): bool;
    public function getMultiple(array $keys): array;
    public function setMultiple(array $values, int $ttl = 3600): bool;
}
```

### Cache Mémoire

```php
<?php
class ArrayCache implements CacheInterface
{
    private array $cache = [];
    private array $expiry = [];

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->cache[$key];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->cache[$key] = $value;
        $this->expiry[$key] = time() + $ttl;
        
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        if (isset($this->expiry[$key]) && $this->expiry[$key] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expiry[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiry = [];
        return true;
    }

    public function deleteByPattern(string $pattern): bool
    {
        $pattern = str_replace('*', '.*', $pattern);
        
        foreach (array_keys($this->cache) as $key) {
            if (preg_match("/^{$pattern}$/", $key)) {
                $this->delete($key);
            }
        }
        
        return true;
    }

    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function getStats(): array
    {
        return [
            'size' => count($this->cache),
            'memory_usage' => strlen(serialize($this->cache)),
            'expired_keys' => count(array_filter($this->expiry, fn($exp) => $exp < time()))
        ];
    }
}
```

### Cache Redis

```php
<?php
class RedisCache implements CacheInterface
{
    private \Redis $redis;
    private string $prefix;

    public function __construct(\Redis $redis, string $prefix = 'mt_db_')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        
        if ($value === false) {
            return null;
        }
        
        return unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        
        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        } else {
            return $this->redis->set($this->prefix . $key, $serialized);
        }
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function clear(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        
        if (empty($keys)) {
            return true;
        }
        
        return $this->redis->del($keys) > 0;
    }

    public function deleteByPattern(string $pattern): bool
    {
        $keys = $this->redis->keys($this->prefix . $pattern);
        
        if (empty($keys)) {
            return true;
        }
        
        return $this->redis->del($keys) > 0;
    }

    public function getMultiple(array $keys): array
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $values = $this->redis->mget($prefixedKeys);
        
        $result = [];
        foreach ($keys as $i => $key) {
            $result[$key] = $values[$i] !== false ? unserialize($values[$i]) : null;
        }
        
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        $pipe = $this->redis->multi();
        
        foreach ($values as $key => $value) {
            $serialized = serialize($value);
            if ($ttl > 0) {
                $pipe->setex($this->prefix . $key, $ttl, $serialized);
            } else {
                $pipe->set($this->prefix . $key, $serialized);
            }
        }
        
        $results = $pipe->exec();
        return !in_array(false, $results, true);
    }
}
```

## Configuration du Cache

### Cache Manager

```php
<?php
class CacheManager
{
    private array $layers = [];
    private array $config = [];

    public function addLayer(string $name, CacheInterface $cache, array $config = []): void
    {
        $this->layers[$name] = $cache;
        $this->config[$name] = array_merge([
            'read' => true,
            'write' => true,
            'fallthrough' => true
        ], $config);
    }

    public function get(string $key): mixed
    {
        foreach ($this->layers as $name => $cache) {
            if (!$this->config[$name]['read']) {
                continue;
            }

            $value = $cache->get($key);
            
            if ($value !== null) {
                // Propager vers les couches supérieures
                $this->propagateUp($key, $value, $name);
                return $value;
            }
            
            if (!$this->config[$name]['fallthrough']) {
                break;
            }
        }
        
        return null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $success = true;
        
        foreach ($this->layers as $name => $cache) {
            if ($this->config[$name]['write']) {
                $result = $cache->set($key, $value, $ttl);
                $success = $success && $result;
            }
        }
        
        return $success;
    }

    public function delete(string $key): bool
    {
        $success = true;
        
        foreach ($this->layers as $cache) {
            $result = $cache->delete($key);
            $success = $success && $result;
        }
        
        return $success;
    }

    public function clear(): bool
    {
        $success = true;
        
        foreach ($this->layers as $cache) {
            $result = $cache->clear();
            $success = $success && $result;
        }
        
        return $success;
    }

    private function propagateUp(string $key, mixed $value, string $currentLayer): void
    {
        $foundCurrent = false;
        
        foreach ($this->layers as $name => $cache) {
            if ($name === $currentLayer) {
                $foundCurrent = true;
                continue;
            }
            
            if (!$foundCurrent) {
                // Couche supérieure, mettre à jour
                if ($this->config[$name]['write']) {
                    $cache->set($key, $value);
                }
            }
        }
    }

    public function getLayerStats(): array
    {
        $stats = [];
        
        foreach ($this->layers as $name => $cache) {
            if (method_exists($cache, 'getStats')) {
                $stats[$name] = $cache->getStats();
            }
        }
        
        return $stats;
    }
}
```

### Configuration par Entité

```php
<?php
class EntityCacheConfig
{
    private array $config = [];

    public function setEntityConfig(string $entityClass, array $config): void
    {
        $this->config[$entityClass] = array_merge([
            'enabled' => true,
            'ttl' => 3600,
            'regions' => ['default'],
            'invalidation' => 'immediate'
        ], $config);
    }

    public function getEntityConfig(string $entityClass): array
    {
        return $this->config[$entityClass] ?? [
            'enabled' => true,
            'ttl' => 3600,
            'regions' => ['default'],
            'invalidation' => 'immediate'
        ];
    }

    public function isEntityCacheable(string $entityClass): bool
    {
        $config = $this->getEntityConfig($entityClass);
        return $config['enabled'];
    }

    public function getEntityTtl(string $entityClass): int
    {
        $config = $this->getEntityConfig($entityClass);
        return $config['ttl'];
    }
}

// Configuration
$cacheConfig = new EntityCacheConfig();

// Configuration spécifique par entité
$cacheConfig->setEntityConfig(User::class, [
    'enabled' => true,
    'ttl' => 1800,  // 30 minutes
    'regions' => ['users', 'auth']
]);

$cacheConfig->setEntityConfig(Product::class, [
    'enabled' => true,
    'ttl' => 7200,  // 2 heures
    'regions' => ['products', 'catalog']
]);

$cacheConfig->setEntityConfig(AuditLog::class, [
    'enabled' => false  // Pas de cache pour les logs
]);
```

## Cache d'Entités

### Entity Cache Manager

```php
<?php
class EntityCacheManager
{
    private CacheInterface $cache;
    private EntityCacheConfig $config;

    public function __construct(CacheInterface $cache, EntityCacheConfig $config)
    {
        $this->cache = $cache;
        $this->config = $config;
    }

    public function getEntity(string $entityClass, mixed $id): ?object
    {
        if (!$this->config->isEntityCacheable($entityClass)) {
            return null;
        }

        $key = $this->getEntityKey($entityClass, $id);
        return $this->cache->get($key);
    }

    public function setEntity(object $entity): void
    {
        $entityClass = get_class($entity);
        
        if (!$this->config->isEntityCacheable($entityClass)) {
            return;
        }

        $id = $this->getEntityId($entity);
        $key = $this->getEntityKey($entityClass, $id);
        $ttl = $this->config->getEntityTtl($entityClass);

        $this->cache->set($key, $entity, $ttl);
    }

    public function deleteEntity(string $entityClass, mixed $id): void
    {
        $key = $this->getEntityKey($entityClass, $id);
        $this->cache->delete($key);
    }

    public function invalidateEntity(object $entity): void
    {
        $entityClass = get_class($entity);
        $id = $this->getEntityId($entity);
        
        $this->deleteEntity($entityClass, $id);
        
        // Invalider les caches liés
        $this->invalidateRelatedCaches($entityClass, $id);
    }

    private function getEntityKey(string $entityClass, mixed $id): string
    {
        return "entity_{$entityClass}_{$id}";
    }

    private function getEntityId(object $entity): mixed
    {
        // Utiliser la réflection pour obtenir l'ID
        $reflection = new \ReflectionClass($entity);
        
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes();
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === 'MulerTech\\Database\\Mapping\\Attributes\\MtColumn') {
                    $args = $attribute->getArguments();
                    if (isset($args['primaryKey']) && $args['primaryKey']) {
                        $property->setAccessible(true);
                        return $property->getValue($entity);
                    }
                }
            }
        }
        
        throw new \RuntimeException('No primary key found for entity');
    }

    private function invalidateRelatedCaches(string $entityClass, mixed $id): void
    {
        // Invalider les listes qui pourraient contenir cette entité
        $patterns = [
            "list_{$entityClass}_*",
            "query_{$entityClass}_*",
            "count_{$entityClass}_*"
        ];

        foreach ($patterns as $pattern) {
            $this->cache->deleteByPattern($pattern);
        }
    }
}
```

### Intégration avec EmEngine

```php
<?php
class CachedEmEngine extends EmEngine
{
    private EntityCacheManager $entityCache;

    public function __construct(
        DatabaseDriverInterface $driver,
        EntityCacheManager $entityCache = null
    ) {
        parent::__construct($driver);
        $this->entityCache = $entityCache ?? new EntityCacheManager(
            new ArrayCache(),
            new EntityCacheConfig()
        );
    }

    public function find(string $entityClass, mixed $id): ?object
    {
        // Chercher d'abord dans le cache
        $entity = $this->entityCache->getEntity($entityClass, $id);
        
        if ($entity !== null) {
            $this->changeDetector->registerEntity($entity);
            return $entity;
        }

        // Charger depuis la base de données
        $entity = parent::find($entityClass, $id);
        
        if ($entity !== null) {
            $this->entityCache->setEntity($entity);
        }

        return $entity;
    }

    public function flush(): void
    {
        $changeSets = $this->changeDetector->getAllChangeSets();

        // Invalider le cache pour les entités modifiées
        foreach ($changeSets as $changeSet) {
            $entity = $changeSet->getEntity();
            $this->entityCache->invalidateEntity($entity);
        }

        parent::flush();

        // Remettre en cache les entités mises à jour
        foreach ($changeSets as $changeSet) {
            $operation = $changeSet->getOperation();
            
            if (in_array($operation, ['INSERT', 'UPDATE'])) {
                $entity = $changeSet->getEntity();
                $this->entityCache->setEntity($entity);
            }
        }
    }
}
```

## Cache de Requêtes

### Query Cache Manager

```php
<?php
class QueryCacheManager
{
    private CacheInterface $cache;
    private int $defaultTtl = 3600;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getCachedQuery(string $sql, array $parameters = []): ?array
    {
        $key = $this->getQueryKey($sql, $parameters);
        return $this->cache->get($key);
    }

    public function setCachedQuery(string $sql, array $parameters, array $result, int $ttl = null): void
    {
        $key = $this->getQueryKey($sql, $parameters);
        $this->cache->set($key, $result, $ttl ?? $this->defaultTtl);
    }

    public function invalidateQueriesForEntity(string $entityClass): void
    {
        $this->cache->deleteByPattern("query_{$entityClass}_*");
    }

    public function invalidateQueriesForTable(string $tableName): void
    {
        $this->cache->deleteByPattern("query_table_{$tableName}_*");
    }

    private function getQueryKey(string $sql, array $parameters): string
    {
        $normalizedSql = $this->normalizeSql($sql);
        $paramHash = md5(serialize($parameters));
        
        return "query_" . md5($normalizedSql) . "_{$paramHash}";
    }

    private function normalizeSql(string $sql): string
    {
        // Normaliser le SQL pour le cache
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = trim($sql);
        $sql = strtoupper($sql);
        
        return $sql;
    }
}
```

### Repository avec Cache

```php
<?php
trait CachedRepositoryTrait
{
    private ?QueryCacheManager $queryCache = null;

    public function setQueryCache(QueryCacheManager $queryCache): void
    {
        $this->queryCache = $queryCache;
    }

    protected function executeQueryWithCache(string $sql, array $parameters = [], int $ttl = null): array
    {
        if ($this->queryCache === null) {
            return $this->emEngine->getDriver()->fetchAll($sql, $parameters);
        }

        // Chercher dans le cache
        $cached = $this->queryCache->getCachedQuery($sql, $parameters);
        
        if ($cached !== null) {
            return $cached;
        }

        // Exécuter la requête
        $result = $this->emEngine->getDriver()->fetchAll($sql, $parameters);

        // Mettre en cache
        $this->queryCache->setCachedQuery($sql, $parameters, $result, $ttl);

        return $result;
    }

    protected function findByCached(array $criteria, array $orderBy = null, int $limit = null, int $ttl = 1800): array
    {
        $qb = $this->createQueryBuilder()->select('e');

        foreach ($criteria as $field => $value) {
            $qb->andWhere("e.{$field} = :{$field}")
               ->setParameter($field, $value);
        }

        if ($orderBy) {
            foreach ($orderBy as $field => $direction) {
                $qb->orderBy("e.{$field}", $direction);
            }
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        $sql = $qb->getSQL();
        $parameters = $qb->getParameters();

        return $this->executeQueryWithCache($sql, $parameters, $ttl);
    }
}

class ProductRepository extends AbstractRepository
{
    use CachedRepositoryTrait;

    public function findByCategory(Category $category): array
    {
        return $this->findByCached(
            ['category' => $category->getId()],
            ['name' => 'ASC'],
            null,
            3600 // 1 heure
        );
    }

    public function findFeatured(): array
    {
        return $this->findByCached(
            ['isFeatured' => true],
            ['createdAt' => 'DESC'],
            10,
            1800 // 30 minutes
        );
    }
}
```

## Cache de Métadonnées

### Metadata Cache

```php
<?php
class MetadataCacheManager
{
    private CacheInterface $cache;
    private string $cachePrefix = 'metadata_';

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getEntityMetadata(string $entityClass): ?EntityMetadata
    {
        $key = $this->cachePrefix . md5($entityClass);
        return $this->cache->get($key);
    }

    public function setEntityMetadata(string $entityClass, EntityMetadata $metadata): void
    {
        $key = $this->cachePrefix . md5($entityClass);
        $this->cache->set($key, $metadata, 86400); // 24 heures
    }

    public function clearMetadataCache(): void
    {
        $this->cache->deleteByPattern($this->cachePrefix . '*');
    }

    public function warmUpCache(array $entityClasses): void
    {
        foreach ($entityClasses as $entityClass) {
            if (!$this->getEntityMetadata($entityClass)) {
                $metadata = $this->loadMetadataFromClass($entityClass);
                $this->setEntityMetadata($entityClass, $metadata);
            }
        }
    }

    private function loadMetadataFromClass(string $entityClass): EntityMetadata
    {
        // Logique de chargement des métadonnées depuis les attributs
        $processor = new EntityProcessor();
        return $processor->processEntity($entityClass);
    }
}
```

## Stratégies d'Invalidation

### Cache Invalidation Manager

```php
<?php
class CacheInvalidationManager
{
    private CacheInterface $cache;
    private array $dependencies = [];

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function addDependency(string $entityClass, array $dependentEntities): void
    {
        $this->dependencies[$entityClass] = $dependentEntities;
    }

    public function invalidateEntity(string $entityClass, mixed $id): void
    {
        // Invalider l'entité elle-même
        $this->cache->delete("entity_{$entityClass}_{$id}");

        // Invalider les entités dépendantes
        if (isset($this->dependencies[$entityClass])) {
            foreach ($this->dependencies[$entityClass] as $dependentClass) {
                $this->cache->deleteByPattern("entity_{$dependentClass}_*");
                $this->cache->deleteByPattern("list_{$dependentClass}_*");
            }
        }

        // Invalider les requêtes liées
        $this->cache->deleteByPattern("query_{$entityClass}_*");
    }

    public function invalidateTag(string $tag): void
    {
        $this->cache->deleteByPattern("*_tag_{$tag}_*");
    }
}

// Configuration des dépendances
$invalidationManager = new CacheInvalidationManager($cache);

// Quand un User change, invalider ses Orders et ses Comments
$invalidationManager->addDependency(User::class, [Order::class, Comment::class]);

// Quand une Category change, invalider ses Products
$invalidationManager->addDependency(Category::class, [Product::class]);
```

### Time-based Invalidation

```php
<?php
class TimeBasedCacheManager extends CacheManager
{
    private array $invalidationSchedule = [];

    public function scheduleInvalidation(string $pattern, \DateTime $invalidateAt): void
    {
        $this->invalidationSchedule[] = [
            'pattern' => $pattern,
            'invalidate_at' => $invalidateAt,
            'created_at' => new \DateTime()
        ];
    }

    public function processScheduledInvalidations(): void
    {
        $now = new \DateTime();
        
        foreach ($this->invalidationSchedule as $key => $schedule) {
            if ($schedule['invalidate_at'] <= $now) {
                $this->deleteByPattern($schedule['pattern']);
                unset($this->invalidationSchedule[$key]);
            }
        }
    }

    public function setCacheWithExpiration(string $key, mixed $value, \DateTime $expiresAt): bool
    {
        $ttl = $expiresAt->getTimestamp() - time();
        
        if ($ttl <= 0) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }
}

// Usage
$cacheManager = new TimeBasedCacheManager();

// Cache avec expiration programmée
$tomorrow = new \DateTime('+1 day');
$cacheManager->setCacheWithExpiration('daily_report', $reportData, $tomorrow);

// Invalider tous les caches de produits à minuit
$midnight = new \DateTime('tomorrow midnight');
$cacheManager->scheduleInvalidation('product_*', $midnight);
```

## Performance et Monitoring

### Cache Statistics

```php
<?php
class CacheStatsCollector
{
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'execution_times' => []
    ];

    public function recordHit(float $executionTime = null): void
    {
        $this->stats['hits']++;
        if ($executionTime !== null) {
            $this->stats['execution_times']['hits'][] = $executionTime;
        }
    }

    public function recordMiss(float $executionTime = null): void
    {
        $this->stats['misses']++;
        if ($executionTime !== null) {
            $this->stats['execution_times']['misses'][] = $executionTime;
        }
    }

    public function recordSet(): void
    {
        $this->stats['sets']++;
    }

    public function recordDelete(): void
    {
        $this->stats['deletes']++;
    }

    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;

        return array_merge($this->stats, [
            'total_requests' => $total,
            'hit_rate' => $hitRate,
            'miss_rate' => 100 - $hitRate,
            'average_hit_time' => $this->getAverageTime('hits'),
            'average_miss_time' => $this->getAverageTime('misses')
        ]);
    }

    private function getAverageTime(string $type): float
    {
        $times = $this->stats['execution_times'][$type] ?? [];
        return !empty($times) ? array_sum($times) / count($times) : 0.0;
    }

    public function reset(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'execution_times' => []
        ];
    }
}

class InstrumentedCache implements CacheInterface
{
    private CacheInterface $cache;
    private CacheStatsCollector $statsCollector;

    public function __construct(CacheInterface $cache, CacheStatsCollector $statsCollector)
    {
        $this->cache = $cache;
        $this->statsCollector = $statsCollector;
    }

    public function get(string $key): mixed
    {
        $startTime = microtime(true);
        $value = $this->cache->get($key);
        $executionTime = microtime(true) - $startTime;

        if ($value !== null) {
            $this->statsCollector->recordHit($executionTime);
        } else {
            $this->statsCollector->recordMiss($executionTime);
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $result = $this->cache->set($key, $value, $ttl);
        $this->statsCollector->recordSet();
        return $result;
    }

    public function delete(string $key): bool
    {
        $result = $this->cache->delete($key);
        $this->statsCollector->recordDelete();
        return $result;
    }

    // Déléguer les autres méthodes...
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    public function deleteByPattern(string $pattern): bool
    {
        return $this->cache->deleteByPattern($pattern);
    }

    public function getMultiple(array $keys): array
    {
        return $this->cache->getMultiple($keys);
    }

    public function setMultiple(array $values, int $ttl = 3600): bool
    {
        return $this->cache->setMultiple($values, $ttl);
    }

    public function getStats(): array
    {
        return $this->statsCollector->getStats();
    }
}
```

### Cache Warming

```php
<?php
class CacheWarmer
{
    private CacheInterface $cache;
    private EmEngine $emEngine;
    private array $strategies = [];

    public function __construct(CacheInterface $cache, EmEngine $emEngine)
    {
        $this->cache = $cache;
        $this->emEngine = $emEngine;
    }

    public function addWarmingStrategy(string $name, callable $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }

    public function warmUp(array $strategies = null): void
    {
        $strategiesToRun = $strategies ?? array_keys($this->strategies);

        foreach ($strategiesToRun as $strategyName) {
            if (isset($this->strategies[$strategyName])) {
                call_user_func($this->strategies[$strategyName], $this->cache, $this->emEngine);
            }
        }
    }

    public function warmUpPopularEntities(): void
    {
        // Précharger les entités les plus consultées
        $popularUsers = $this->emEngine->createQueryBuilder(User::class)
            ->select('u')
            ->orderBy('u.loginCount', 'DESC')
            ->setMaxResults(100)
            ->getResult();

        foreach ($popularUsers as $user) {
            $key = "entity_" . User::class . "_{$user->getId()}";
            $this->cache->set($key, $user, 3600);
        }
    }
}

// Configuration des stratégies
$warmer = new CacheWarmer($cache, $emEngine);

$warmer->addWarmingStrategy('popular_users', function($cache, $emEngine) {
    $users = $emEngine->createQueryBuilder(User::class)
        ->select('u')
        ->where('u.isActive = :active')
        ->setParameter('active', true)
        ->orderBy('u.lastLoginAt', 'DESC')
        ->setMaxResults(50)
        ->getResult();

    foreach ($users as $user) {
        $cache->set("entity_User_{$user->getId()}", $user, 1800);
    }
});

$warmer->addWarmingStrategy('featured_products', function($cache, $emEngine) {
    $products = $emEngine->createQueryBuilder(Product::class)
        ->select('p')
        ->where('p.isFeatured = :featured')
        ->setParameter('featured', true)
        ->getResult();

    $cache->set('featured_products', $products, 3600);
});

// Exécuter le préchauffage
$warmer->warmUp(['popular_users', 'featured_products']);
```

---

**Navigation :**
- [← Système d'Événements](events.md)
- [→ Optimisations et Performance](performance.md)
- [↑ ORM](../README.md)
