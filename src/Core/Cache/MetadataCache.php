<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use Exception;
use MulerTech\Database\Mapping\DbMappingInterface;
use ReflectionClass;
use RuntimeException;

/**
 * Class MetadataCache
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class MetadataCache extends MemoryCache
{
    private readonly MetadataRelationsHelper $relationsHelper;
    private readonly MetadataReflectionHelper $reflectionHelper;

    /**
     * @param CacheConfig|null $config
     * @param DbMappingInterface|null $dbMapping
     * @param MetadataRelationsHelper|null $relationsHelper
     * @param MetadataReflectionHelper|null $reflectionHelper
     */
    public function __construct(
        ?CacheConfig $config = null,
        private readonly ?DbMappingInterface $dbMapping = null,
        ?MetadataRelationsHelper $relationsHelper = null,
        ?MetadataReflectionHelper $reflectionHelper = null
    ) {
        $metadataConfig = new CacheConfig(
            maxSize: $config->maxSize ?? 50000,
            ttl: 0, // No expiration for metadata
            evictionPolicy: $config->evictionPolicy ?? 'lru'
        );

        parent::__construct($metadataConfig);

        $this->relationsHelper = $relationsHelper ?? new MetadataRelationsHelper();
        $this->reflectionHelper = $reflectionHelper ?? new MetadataReflectionHelper();
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
     * @param string $entityClass
     * @param mixed $metadata
     * @return void
     */
    public function setEntityMetadata(string $entityClass, mixed $metadata): void
    {
        $this->setPermanentMetadata($this->getEntityKey($entityClass), $metadata);
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
        $this->setPermanentMetadata($key, $metadata);
        $this->tag($key, ['property_metadata', $entityClass]);
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
     * Warm up a single entity class
     * @param class-string $entityClass
     * @return void
     * @throws Exception
     */
    private function warmUpEntity(string $entityClass): void
    {
        if ($this->dbMapping === null || $this->isWarmedUp($entityClass)) {
            return;
        }

        try {
            $this->cacheBasicMetadata($entityClass);
            $this->cacheRelationsMetadata($entityClass);
            $this->cacheReflectionMetadata($entityClass);

            // Mark entity as warmed up
            $this->setEntityMetadata($entityClass . ':warmed', true);

        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to warm up entity metadata for %s: %s', $entityClass, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Cache basic metadata for entity
     * @param class-string $entityClass
     * @return void
     */
    private function cacheBasicMetadata(string $entityClass): void
    {
        if ($this->dbMapping === null) {
            return;
        }

        // Cache table name
        $tableName = $this->dbMapping->getTableName($entityClass);
        if ($tableName !== null) {
            $this->setEntityMetadata($entityClass . ':table', $tableName);
        }

        // Cache properties to columns mapping
        $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityClass);
        $this->setEntityMetadata($entityClass . ':properties', $propertiesColumns);

        // Cache primary key information
        $primaryKey = $this->findPrimaryKey($entityClass);
        if ($primaryKey !== null) {
            $this->setEntityMetadata($entityClass . ':primaryKey', $primaryKey);
        }
    }

    /**
     * Cache relations metadata for entity
     * @param class-string $entityClass
     * @return void
     */
    private function cacheRelationsMetadata(string $entityClass): void
    {
        if ($this->dbMapping === null) {
            return;
        }

        $relations = $this->relationsHelper->buildRelationsData($this->dbMapping, $entityClass);

        if (!empty($relations)) {
            $this->setEntityMetadata($entityClass . ':relations', $relations);
        }
    }

    /**
     * Cache reflection metadata for entity
     * @param class-string $entityClass
     * @return void
     */
    private function cacheReflectionMetadata(string $entityClass): void
    {
        $reflection = new ReflectionClass($entityClass);
        $reflectionData = $this->reflectionHelper->buildReflectionData($reflection);
        $this->setEntityMetadata($entityClass . ':reflection', $reflectionData);
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
     * Get cached table name for entity or load it if not cached
     * @param string $entityClass
     * @return string|null
     * @throws Exception
     */
    public function getTableName(string $entityClass): ?string
    {
        $cached = $this->get($entityClass . ':table');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':table');
        }

        return is_string($cached) ? $cached : null;
    }

    /**
     * Get cached properties to columns mapping or load it if not cached
     * @param string $entityClass
     * @return array<string, string>|null
     * @throws Exception
     */
    public function getPropertiesColumns(string $entityClass): ?array
    {
        $cached = $this->get($entityClass . ':properties');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':properties');
        }

        // Ensure we return the correct type
        if (is_array($cached)) {
            // Validate that all keys and values are strings
            foreach ($cached as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    return null;
                }
            }
            /** @var array<string, string> $cached */
            return $cached;
        }

        return null;
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

            foreach (array_keys($propertiesColumns) as $property) {
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
        } catch (Exception) {
            return null;
        }
    }
}
