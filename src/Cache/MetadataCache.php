<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

use MulerTech\Database\Cache\MemoryCache;
use MulerTech\Database\Mapping\DbMappingInterface;

/**
 * Cache spécialisé pour les métadonnées d'entités
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
final class MetadataCache extends MemoryCache
{
    /**
     * @param CacheConfig|null $config
     * @param DbMappingInterface|null $dbMapping
     */
    public function __construct(
        ?CacheConfig $config = null,
        private readonly ?DbMappingInterface $dbMapping = null
    ) {
        // Metadata cache needs larger size and no TTL by default
        $metadataConfig = new CacheConfig(
            maxSize: $config->maxSize ?? 50000,
            ttl: 0, // No expiration for metadata
            enableStats: $config->enableStats ?? true,
            evictionPolicy: $config->evictionPolicy ?? 'lru'
        );

        parent::__construct($metadataConfig);
    }

    /**
     * @param string $key
     * @param mixed $metadata
     * @param bool $isPermanent
     * @return void
     */
    public function setMetadata(string $key, mixed $metadata, bool $isPermanent = false): void
    {
        $this->set($key, $metadata, $isPermanent ? 0 : $this->config->ttl);
    }

    /**
     * @param string $entityClass
     * @return mixed
     */
    public function getEntityMetadata(string $entityClass): mixed
    {
        return $this->get($this->getEntityKey($entityClass));
    }

    /**
     * @param string $entityClass
     * @param mixed $metadata
     * @return void
     */
    public function setEntityMetadata(string $entityClass, mixed $metadata): void
    {
        $this->setMetadata($this->getEntityKey($entityClass), $metadata, true);
        $this->tag($this->getEntityKey($entityClass), ['entity_metadata']);
    }

    /**
     * @param string $entityClass
     * @param string $property
     * @return mixed
     */
    public function getPropertyMetadata(string $entityClass, string $property): mixed
    {
        return $this->get($this->getPropertyKey($entityClass, $property));
    }

    /**
     * @param string $entityClass
     * @param string $property
     * @param mixed $metadata
     * @return void
     */
    public function setPropertyMetadata(string $entityClass, string $property, mixed $metadata): void
    {
        $key = $this->getPropertyKey($entityClass, $property);
        $this->setMetadata($key, $metadata, true);
        $this->tag($key, ['property_metadata', $entityClass]);
    }

    /**
     * @param string $entityClass
     * @param string $relation
     * @return mixed
     */
    public function getRelationMetadata(string $entityClass, string $relation): mixed
    {
        return $this->get($this->getRelationKey($entityClass, $relation));
    }

    /**
     * @param string $entityClass
     * @param string $relation
     * @param mixed $metadata
     * @return void
     */
    public function setRelationMetadata(string $entityClass, string $relation, mixed $metadata): void
    {
        $key = $this->getRelationKey($entityClass, $relation);
        $this->setMetadata($key, $metadata, true);
        $this->tag($key, ['relation_metadata', $entityClass]);
    }

    /**
     * @param string $entityClass
     * @return array<string, mixed>
     */
    public function getAllMetadataForEntity(string $entityClass): array
    {
        $result = [
            'warmed' => $this->isWarmedUp($entityClass),
            'table' => $this->getTableName($entityClass),
            'properties' => $this->getPropertiesColumns($entityClass),
            'primaryKey' => $this->getPrimaryKey($entityClass),
            'relations' => $this->getRelations($entityClass),
            'reflection' => $this->getReflectionData($entityClass),
            'entity' => $this->getEntityMetadata($entityClass),
            'custom' => []
        ];

        // Get all cached keys for this entity
        foreach ($this->getAllKeys() as $key) {
            if (str_starts_with($key, $entityClass . ':')) {
                $metadataType = substr($key, strlen($entityClass . ':'));
                // Skip already included metadata
                if (!in_array($metadataType, ['warmed', 'table', 'properties', 'primaryKey', 'relations', 'reflection'], true)) {
                    $result['custom'][$metadataType] = $this->get($key);
                }
            }
        }

        return array_filter($result, fn ($value) => $value !== null);
    }

    /**
     * @param string $entityClass
     * @return void
     */
    public function invalidateEntityMetadata(string $entityClass): void
    {
        $this->invalidateTag($entityClass);
        $this->invalidateTag('entity_metadata');

        // Also remove warmed status
        $this->delete($entityClass . ':warmed');
    }

    /**
     * Check if entity metadata has been warmed up
     * @param string $entityClass
     * @return bool
     */
    public function isWarmedUp(string $entityClass): bool
    {
        return $this->get($entityClass . ':warmed') === true;
    }

    /**
     * Pre-load metadata for entity classes to improve performance
     * @param array<class-string> $entityClasses
     * @return void
     */
    public function warmUp(array $entityClasses = []): void
    {
        if ($this->dbMapping === null || empty($entityClasses)) {
            // Cannot warm up without mapping and entity classes
            // Note: DbMapping doesn't have a getEntityClasses method,
            // so entity classes must be provided explicitly
            return;
        }

        foreach ($entityClasses as $entityClass) {
            $this->warmUpEntity($entityClass);
        }
    }

    /**
     * Warm up a single entity class
     * @param class-string $entityClass
     * @return void
     */
    private function warmUpEntity(string $entityClass): void
    {
        if ($this->dbMapping === null || $this->isWarmedUp($entityClass)) {
            return;
        }

        try {
            // Cache table name
            $tableName = $this->dbMapping->getTableName($entityClass);
            if ($tableName !== null) {
                $this->setEntityMetadata($entityClass . ':table', $tableName);
            }

            // Cache properties to columns mapping
            $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityClass);
            $this->setEntityMetadata($entityClass . ':properties', $propertiesColumns);

            // Cache primary key information
            // DbMapping doesn't have getPrimaryKey, so we need to find it
            $primaryKey = $this->findPrimaryKey($entityClass);
            if ($primaryKey !== null) {
                $this->setEntityMetadata($entityClass . ':primaryKey', $primaryKey);
            }

            // Cache relations
            $relations = [];

            // OneToOne relations
            $oneToOne = $this->dbMapping->getOneToOne($entityClass);
            if (!empty($oneToOne)) {
                $relations['oneToOne'] = $oneToOne;
            }

            // OneToMany relations
            $oneToMany = $this->dbMapping->getOneToMany($entityClass);
            if (!empty($oneToMany)) {
                $relations['oneToMany'] = $oneToMany;
            }

            // ManyToOne relations
            $manyToOne = $this->dbMapping->getManyToOne($entityClass);
            if (!empty($manyToOne)) {
                $relations['manyToOne'] = $manyToOne;
            }

            // ManyToMany relations
            $manyToMany = $this->dbMapping->getManyToMany($entityClass);
            if (!empty($manyToMany)) {
                $relations['manyToMany'] = $manyToMany;
            }

            if (!empty($relations)) {
                $this->setEntityMetadata($entityClass . ':relations', $relations);
            }

            // Cache reflection data
            $reflection = new \ReflectionClass($entityClass);
            $reflectionData = [
                'isInstantiable' => $reflection->isInstantiable(),
                'hasConstructor' => $reflection->getConstructor() !== null,
                'constructorParams' => [],
                'properties' => []
            ];

            if ($reflection->getConstructor() !== null) {
                foreach ($reflection->getConstructor()->getParameters() as $param) {
                    $reflectionData['constructorParams'][] = [
                        'name' => $param->getName(),
                        'isOptional' => $param->isOptional(),
                        'hasDefault' => $param->isDefaultValueAvailable(),
                        'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null
                    ];
                }
            }

            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $type = $property->getType();
                    $typeName = null;
                    if ($type instanceof \ReflectionNamedType) {
                        $typeName = $type->getName();
                    } elseif ($type instanceof \ReflectionUnionType) {
                        $typeName = implode('|', array_map(function ($t) {
                            return $t instanceof \ReflectionNamedType ? $t->getName() : (string)$t;
                        }, $type->getTypes()));
                    } elseif ($type instanceof \ReflectionIntersectionType) {
                        $typeName = implode('&', array_map(function ($t) {
                            return $t instanceof \ReflectionNamedType ? $t->getName() : (string)$t;
                        }, $type->getTypes()));
                    }

                    $reflectionData['properties'][$property->getName()] = [
                        'isPublic' => $property->isPublic(),
                        'isProtected' => $property->isProtected(),
                        'isPrivate' => $property->isPrivate(),
                        'type' => $typeName
                    ];
                }
            }

            $this->setEntityMetadata($entityClass . ':reflection', $reflectionData);

            // Mark entity as warmed up
            $this->setEntityMetadata($entityClass . ':warmed', true);

        } catch (\Exception $e) {
            // Log error but continue with other entities
            // In production, you'd want to log this properly
        }
    }

    /**
     * @param string $entityClass
     * @return string
     */
    private function getEntityKey(string $entityClass): string
    {
        return sprintf('entity:%s', $entityClass);
    }

    /**
     * @param string $entityClass
     * @param string $property
     * @return string
     */
    private function getPropertyKey(string $entityClass, string $property): string
    {
        return sprintf('property:%s:%s', $entityClass, $property);
    }

    /**
     * @param string $entityClass
     * @param string $relation
     * @return string
     */
    private function getRelationKey(string $entityClass, string $relation): string
    {
        return sprintf('relation:%s:%s', $entityClass, $relation);
    }

    /**
     * Get cached table name for entity or load it if not cached
     * @param string $entityClass
     * @return string|null
     */
    public function getTableName(string $entityClass): ?string
    {
        $cached = $this->get($entityClass . ':table');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':table');
        }

        return $cached;
    }

    /**
     * Get cached properties to columns mapping or load it if not cached
     * @param string $entityClass
     * @return array<string, string>|null
     */
    public function getPropertiesColumns(string $entityClass): ?array
    {
        $cached = $this->get($entityClass . ':properties');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':properties');
        }

        return $cached;
    }

    /**
     * Get cached primary key or load it if not cached
     * @param string $entityClass
     * @return string|null
     */
    public function getPrimaryKey(string $entityClass): ?string
    {
        $cached = $this->get($entityClass . ':primaryKey');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':primaryKey');
        }

        return $cached;
    }

    /**
     * Get cached relations or load them if not cached
     * @param string $entityClass
     * @return array<string, mixed>|null
     */
    public function getRelations(string $entityClass): ?array
    {
        $cached = $this->get($entityClass . ':relations');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':relations');
        }

        return $cached;
    }

    /**
     * Get cached reflection data or load it if not cached
     * @param string $entityClass
     * @return array<string, mixed>|null
     */
    public function getReflectionData(string $entityClass): ?array
    {
        $cached = $this->get($entityClass . ':reflection');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':reflection');
        }

        return $cached;
    }

    /**
     * Get all warmed up entity classes
     * @return array<class-string>
     */
    public function getWarmedUpClasses(): array
    {
        /** @var array<class-string> $warmedClasses */
        $warmedClasses = [];

        // This would need access to internal cache structure
        // For now, we'll check known patterns
        foreach ($this->cache as $key => $value) {
            if (str_ends_with($key, ':warmed') && $value === true) {
                $entityClass = str_replace(':warmed', '', $key);
                /** @var class-string $entityClass */
                $warmedClasses[] = $entityClass;
            }
        }

        return $warmedClasses;
    }

    /**
     * Find primary key column for an entity
     * @param class-string $entityClass
     * @return string|null
     */
    private function findPrimaryKey(string $entityClass): ?string
    {
        if ($this->dbMapping === null) {
            return null;
        }

        try {
            $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityClass);

            foreach ($propertiesColumns as $property => $column) {
                $columnKey = $this->dbMapping->getColumnKey($entityClass, $property);
                if ($columnKey === 'PRI') {
                    return $property;
                }
            }

            // If no PRI key found, check for 'id' property as fallback
            if (isset($propertiesColumns['id'])) {
                return 'id';
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return array<string>
     */
    private function getAllKeys(): array
    {
        return array_keys($this->cache);
    }
}
