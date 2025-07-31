<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use Exception;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\EntityProcessor;
use ReflectionClass;
use RuntimeException;

/**
 * Class MetadataCache
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class MetadataCache extends MemoryCache
{
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
        $this->set($key, $metadata, 0);
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
        $metadata = $this->getEntityMetadata($entityClass);
        return $metadata->tableName;
    }

    /**
     * Get properties to columns mapping using EntityMetadata
     * @param class-string $entityClass
     * @return array<string, string>
     * @throws Exception
     */
    public function getPropertiesColumns(string $entityClass): array
    {
        $metadata = $this->getEntityMetadata($entityClass);
        return $metadata->getPropertiesColumns();
    }


    /**
     * Get EntityMetadata using EntityProcessor for Mt*-only mapping
     * @param class-string $entityClass
     * @return EntityMetadata
     */
    public function getEntityMetadata(string $entityClass): EntityMetadata
    {
        $key = $entityClass . ':entity_metadata';
        $cached = $this->get($key);

        if ($cached instanceof EntityMetadata) {
            return $cached;
        }

        // Use EntityProcessor to build metadata from Mt* attributes
        $reflection = new ReflectionClass($entityClass);
        $entityMetadata = $this->buildEntityMetadataFromAttributes($reflection);

        $this->setPermanentMetadata($key, $entityMetadata);

        // Mark entity as warmed up
        $this->setPermanentMetadata($entityClass . ':warmed', true);

        return $entityMetadata;
    }

    /**
     * Build EntityMetadata from Mt* attributes using EntityProcessor logic
     * @param ReflectionClass<object> $reflection
     * @return EntityMetadata
     */
    private function buildEntityMetadataFromAttributes(ReflectionClass $reflection): EntityMetadata
    {
        // Load a temporary EntityProcessor to build metadata
        $tempProcessor = new EntityProcessor();

        // Use the private buildEntityMetadata method through reflection
        $method = new \ReflectionMethod($tempProcessor, 'buildEntityMetadata');
        $method->setAccessible(true);

        $result = $method->invoke($tempProcessor, $reflection);
        if (!$result instanceof EntityMetadata) {
            throw new RuntimeException('Failed to build EntityMetadata from EntityProcessor');
        }

        return $result;
    }

    /**
     * Get the column type for a given property of an entity.
     *
     * @param class-string $entityClass
     * @param string $property
     * @return \MulerTech\Database\Mapping\Types\ColumnType|null
     */
    public function getColumnType(string $entityClass, string $property): ?\MulerTech\Database\Mapping\Types\ColumnType
    {
        $metadata = $this->getEntityMetadata($entityClass);
        return $metadata->getColumnType($property);
    }
}
