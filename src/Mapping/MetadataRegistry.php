<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use Exception;
use ReflectionException;

/**
 * Simple registry for EntityMetadata instances
 * Acts as an immutable cache using EntityMetadata objects
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class MetadataRegistry
{
    /**
     * @var array<class-string, EntityMetadata>
     */
    private array $metadata = [];

    private EntityProcessor $entityProcessor;

    /**
     * @param string|null $entitiesPath Automatic loading of entities from this path
     * @throws Exception
     */
    public function __construct(?string $entitiesPath = null)
    {
        $this->entityProcessor = new EntityProcessor();

        // Automatically load entities if path is provided
        if ($entitiesPath !== null) {
            $this->loadEntitiesFromPath($entitiesPath);
        }
    }

    /**
     * Load entities from a directory path and register their metadata
     * @param string $entitiesPath
     * @return void
     * @throws Exception
     */
    public function loadEntitiesFromPath(string $entitiesPath): void
    {
        $this->entityProcessor->loadEntities($entitiesPath);

        // Get the loaded entity classes and register their metadata
        $loadedClasses = $this->entityProcessor->getLoadedEntityClasses();
        foreach ($loadedClasses as $className) {
            // This will populate the metadata array via lazy loading
            $this->getEntityMetadata($className);
        }
    }

    /**
     * Get EntityMetadata for a class, loading it if not already registered
     * @param class-string $className
     * @return EntityMetadata
     * @throws ReflectionException
     */
    public function getEntityMetadata(string $className): EntityMetadata
    {
        return $this->metadata[$className] ??= $this->entityProcessor->buildEntityMetadataForClass($className);
    }

    /**
     * Check if metadata is registered for a class
     * @param class-string $className
     * @return bool
     */
    public function hasMetadata(string $className): bool
    {
        return isset($this->metadata[$className]);
    }

    /**
     * Register metadata manually (useful for tests)
     * @param class-string $className
     * @param EntityMetadata $metadata
     * @return void
     */
    public function registerMetadata(string $className, EntityMetadata $metadata): void
    {
        $this->metadata[$className] = $metadata;
    }

    /**
     * Get all registered class names
     * @return array<class-string>
     */
    public function getRegisteredClasses(): array
    {
        return array_keys($this->metadata);
    }

    /**
     * Get metadata for all registered entities
     * @return array<class-string, EntityMetadata>
     */
    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Clear all registered metadata
     * @return void
     */
    public function clear(): void
    {
        $this->metadata = [];
    }

    /**
     * Get total number of registered entities
     * @return int
     */
    public function count(): int
    {
        return count($this->metadata);
    }

    // ============================================
    // Legacy compatibility methods (to be phased out)
    // ============================================

    /**
     * @param class-string $entityClass
     * @return string
     * @throws Exception
     */
    public function getTableName(string $entityClass): string
    {
        return $this->getEntityMetadata($entityClass)->tableName;
    }

    /**
     * @param class-string $entityClass
     * @param string $property
     * @return mixed
     * @throws ReflectionException
     */
    public function getPropertyMetadata(string $entityClass, string $property): mixed
    {
        $metadata = $this->getEntityMetadata($entityClass);
        return $metadata->getColumn($property);
    }

    /**
     * @param class-string $entityClass
     * @param bool $withoutId
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entityClass, bool $withoutId = true): array
    {
        $metadata = $this->getEntityMetadata($entityClass);
        $columns = $metadata->getPropertiesColumns();

        if ($withoutId && isset($columns['id'])) {
            unset($columns['id']);
        }

        return $columns;
    }

    /**
     * Get all loaded entities
     * @return array<class-string>
     */
    public function getLoadedEntities(): array
    {
        $entities = array_keys($this->metadata);
        sort($entities);
        return $entities;
    }
}
