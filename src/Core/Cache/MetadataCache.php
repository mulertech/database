<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use Exception;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\EntityProcessor;
use RuntimeException;

/**
 * Class MetadataCache
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class MetadataCache extends MemoryCache
{
    private EntityProcessor $entityProcessor;

    /**
     * @param CacheConfig|null $config
     */
    public function __construct(
        ?CacheConfig $config = null,
    ) {
        $metadataConfig = new CacheConfig(
            maxSize: $config->maxSize ?? 50000,
            ttl: 0, // No expiration for metadata
            evictionPolicy: $config->evictionPolicy ?? 'lru'
        );

        parent::__construct($metadataConfig);
        $this->entityProcessor = new EntityProcessor();
    }

    /**
     * Load entities from a directory path and cache their metadata
     * @param string $entitiesPath
     * @return void
     */
    public function loadEntitiesFromPath(string $entitiesPath): void
    {
        $this->entityProcessor->loadEntities($entitiesPath);

        // Now warm up the cache with all discovered entities
        $tables = $this->entityProcessor->getTables();
        foreach (array_keys($tables) as $entityClass) {
            $this->warmUpEntity($entityClass);
        }
    }

    /**
     * @param string $key
     * @param mixed $metadata
     * @return void
     */
    public function setMetadata(string $key, mixed $metadata): void
    {
        $this->set($key, $metadata, $this->config->ttl);
    }

    /**
     * @param string $key
     * @param mixed $metadata
     * @return void
     */
    public function setPermanentMetadata(string $key, mixed $metadata): void
    {
        $this->set($key, $metadata);
    }

    /**
     * @param class-string $entityClass
     * @param mixed $metadata
     * @return void
     */
    public function setEntityMetadata(string $entityClass, mixed $metadata): void
    {
        $this->setPermanentMetadata($this->getEntityKey($entityClass), $metadata);
        $this->tag($this->getEntityKey($entityClass), ['entity_metadata']);
    }

    /**
     * @param class-string $entityClass
     * @param string $property
     * @return mixed
     */
    public function getPropertyMetadata(string $entityClass, string $property): mixed
    {
        return $this->get($this->getPropertyKey($entityClass, $property));
    }

    /**
     * @param class-string $entityClass
     * @param string $property
     * @param mixed $metadata
     * @return void
     */
    public function setPropertyMetadata(string $entityClass, string $property, mixed $metadata): void
    {
        $key = $this->getPropertyKey($entityClass, $property);
        $this->setPermanentMetadata($key, $metadata);
        $this->tag($key, ['property_metadata', $entityClass]);
    }

    /**
     * Check if entity metadata has been warmed up
     * @param class-string $entityClass
     * @return bool
     */
    public function isWarmedUp(string $entityClass): bool
    {
        return $this->get($entityClass . ':warmed') === true;
    }

    /**
     * Warm up a single entity class using Mt* attributes
     * @param class-string $entityClass
     * @return void
     * @throws Exception
     */
    private function warmUpEntity(string $entityClass): void
    {
        if ($this->isWarmedUp($entityClass)) {
            return;
        }

        try {
            // Pre-load EntityMetadata to cache it
            $this->getEntityMetadata($entityClass);

            // Mark entity as warmed up
            $this->setPermanentMetadata($entityClass . ':warmed', true);

        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to warm up entity metadata for %s: %s', $entityClass, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Warm up multiple entity classes at once
     * @param array<class-string> $entityClasses
     * @return void
     * @throws Exception
     */
    public function warmUpEntities(array $entityClasses): void
    {
        foreach ($entityClasses as $entityClass) {
            $this->warmUpEntity($entityClass);
        }
    }

    /**
     * @param class-string $entityClass
     * @return string
     */
    private function getEntityKey(string $entityClass): string
    {
        return sprintf('entity:%s', $entityClass);
    }

    /**
     * @param class-string $entityClass
     * @param string $property
     * @return string
     */
    private function getPropertyKey(string $entityClass, string $property): string
    {
        return sprintf('property:%s:%s', $entityClass, $property);
    }

    /**
     * Get table name for entity using EntityMetadata
     * @param class-string $entityClass
     * @return string
     * @throws Exception
     */
    public function getTableName(string $entityClass): string
    {
        return $this->getEntityMetadata($entityClass)->tableName;
    }

    /**
     * Get properties to columns mapping using EntityMetadata
     * @param class-string $entityClass
     * @return array<string, string>
     * @throws Exception
     */
    public function getPropertiesColumns(string $entityClass): array
    {
        return $this->getEntityMetadata($entityClass)->getPropertiesColumns();
    }


    /**
     * Get EntityMetadata using EntityProcessor for Mt*-only mapping
     * @param class-string $entityClass
     * @return EntityMetadata
     * @throws \ReflectionException
     */
    public function getEntityMetadata(string $entityClass): EntityMetadata
    {
        $key = $entityClass . ':entity_metadata';
        $cached = $this->get($key);

        if ($cached instanceof EntityMetadata) {
            return $cached;
        }

        // Use EntityProcessor to build metadata from Mt* attributes
        $entityMetadata = $this->entityProcessor->buildEntityMetadataForClass($entityClass);

        $this->setPermanentMetadata($key, $entityMetadata);

        // Mark entity as warmed up
        $this->setPermanentMetadata($entityClass . ':warmed', true);

        return $entityMetadata;
    }

    /**
     * Get all loaded entities (from cache only)
     * Note: This only returns entities that have been loaded into cache
     * @return array<class-string>
     */
    public function getLoadedEntities(): array
    {
        $entities = [];
        foreach ($this->cache as $key => $value) {
            if (str_ends_with($key, ':entity_metadata') && $value instanceof \MulerTech\Database\Mapping\EntityMetadata) {
                $entities[] = $value->className;
            }
        }
        sort($entities);
        return $entities;
    }

    /**
     * Get all loaded table names (from cache only)
     * @return array<string>
     */
    public function getLoadedTables(): array
    {
        $tables = [];
        foreach ($this->cache as $key => $value) {
            if (str_ends_with($key, ':entity_metadata') && $value instanceof \MulerTech\Database\Mapping\EntityMetadata) {
                $tables[] = $value->tableName;
            }
        }
        sort($tables);
        return $tables;
    }
}
