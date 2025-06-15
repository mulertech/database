<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTime;
use Exception;
use MulerTech\Database\Cache\CacheFactory;
use MulerTech\Database\Cache\MetadataCache;
use MulerTech\Database\Mapping\ColumnType;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\Mapping\MtManyToOne;
use MulerTech\Collections\Collection;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * EntityHydrator with metadata caching for improved performance
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
class EntityHydrator
{
    /**
     * @var DbMappingInterface|null
     */
    private ?DbMappingInterface $dbMapping;

    /**
     * @var MetadataCache
     */
    private readonly MetadataCache $metadataCache;

    /**
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $reflectionCache = [];

    /**
     * @var array<string, bool>
     */
    private array $setterCache = [];

    /**
     * @param DbMappingInterface|null $dbMapping
     * @param MetadataCache|null $metadataCache
     */
    public function __construct(
        ?DbMappingInterface $dbMapping = null,
        ?MetadataCache $metadataCache = null
    ) {
        $this->dbMapping = $dbMapping;
        $this->metadataCache = $metadataCache ?? CacheFactory::createMetadataCache('entity_hydrator');
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string $entityName
     * @return object
     * @throws ReflectionException
     */
    public function hydrate(array $data, string $entityName): object
    {
        $entity = new $entityName();
        $reflection = new ReflectionClass($entityName);

        // Initialize collections first
        $this->initializeCollections($entity, $reflection);

        // Then hydrate scalar properties and relations
        if ($this->dbMapping !== null) {
            try {
                $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityName);

                foreach ($propertiesColumns as $property => $column) {
                    if (!isset($data[$column])) {
                        continue;
                    }

                    $value = $data[$column];

                    // Check if this property is a relation
                    if ($this->isRelationProperty($entityName, $property)) {
                        // For relations, we store the foreign key value but don't set the property yet
                        // The relation will be loaded later by EntityRelationLoader
                        continue;
                    }

                    // Process the value according to its type for scalar properties
                    $processedValue = $this->processValue($entityName, $property, $value);

                    // Validate nullable constraints
                    if ($processedValue === null && !$this->isPropertyNullable($entityName, $property)) {
                        throw new \TypeError("Property {$property} of {$entityName} cannot be null");
                    }

                    $reflectionProperty = $reflection->getProperty($property);
                    $reflectionProperty->setValue($entity, $processedValue);
                }
            } catch (\Exception $e) {
                // If dbMapping fails, fall back to basic hydration
                $this->fallbackHydration($entity, $data, $reflection);
            }
        } else {
            // No dbMapping available, use fallback hydration
            $this->fallbackHydration($entity, $data, $reflection);
        }

        return $entity;
    }

    /**
     * @param class-string $entityName
     * @return void
     */
    public function warmUpCache(string $entityName): void
    {
        try {
            // Pre-load all metadata for this entity
            $this->getColumnToPropertyMap($entityName);
            $this->getReflectionProperties($entityName);

            // Cache property types
            if ($this->dbMapping !== null) {
                $properties = $this->dbMapping->getPropertiesColumns($entityName);
                foreach (array_keys($properties) as $property) {
                    $this->dbMapping->getColumnType($entityName, $property);
                }
            }
        } catch (ReflectionException $e) {
            // Log warning but don't fail
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        $stats = $this->metadataCache->getStatistics();

        return [
            'metadata_cache' => $stats,
            'reflection_cache_size' => count($this->reflectionCache),
            'setter_cache_size' => count($this->setterCache),
            'total_cached_properties' => array_sum(array_map('count', $this->reflectionCache)),
        ];
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    private function getColumnToPropertyMap(string $entityName): array
    {
        $cacheKey = 'column_map:' . $entityName;

        $cached = $this->metadataCache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $columnToPropertyMap = [];
        // If DbMapping is available, use it to create a column-to-property mapping
        if ($this->dbMapping !== null) {
            try {
                $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityName);
                foreach ($propertiesColumns as $property => $column) {
                    $columnToPropertyMap[$column] = $property;
                }
            } catch (ReflectionException $e) {
                // If mapping fails, we'll fall back to snake_case conversion
            }
        }

        $this->metadataCache->setEntityMetadata($cacheKey, $columnToPropertyMap);

        return $columnToPropertyMap;
    }

    /**
     * @param class-string $entityClass
     * @param string $propertyName
     * @return ReflectionProperty|null
     * @throws ReflectionException
     */
    private function getReflectionProperty(string $entityClass, string $propertyName): ?ReflectionProperty
    {
        if (!isset($this->reflectionCache[$entityClass])) {
            $this->reflectionCache[$entityClass] = [];
        }

        if (isset($this->reflectionCache[$entityClass][$propertyName])) {
            return $this->reflectionCache[$entityClass][$propertyName];
        }

        $reflection = new ReflectionClass($entityClass);
        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
            $this->reflectionCache[$entityClass][$propertyName] = $property;
            return $property;
        }

        return null;
    }

    /**
     * @param class-string $entityClass
     * @return array<string, ReflectionProperty>
     * @throws ReflectionException
     */
    private function getReflectionProperties(string $entityClass): array
    {
        if (isset($this->reflectionCache[$entityClass])) {
            return $this->reflectionCache[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        $this->reflectionCache[$entityClass] = $properties;

        return $properties;
    }

    /**
     * Process a value according to its type (make public for external use)
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @param mixed $value
     * @return mixed
     */
    public function processPropertyValue(string $entityClass, string $propertyName, mixed $value): mixed
    {
        return $this->processValue($entityClass, $propertyName, $value);
    }

    /**
     * @param class-string $entityClass
     * @param string $propertyName
     * @return ColumnType|null
     * @throws ReflectionException
     */
    private function getCachedColumnType(string $entityClass, string $propertyName): ?ColumnType
    {
        $cacheKey = 'column_type:' . $entityClass . ':' . $propertyName;

        $cached = $this->metadataCache->getPropertyMetadata($entityClass, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $columnType = $this->dbMapping?->getColumnType($entityClass, $propertyName);

        if ($columnType !== null) {
            $this->metadataCache->setPropertyMetadata($entityClass, $cacheKey, $columnType);
        }

        return $columnType;
    }

    /**
     * @param mixed $value
     * @param ColumnType $columnType
     * @return mixed
     */
    private function processValueByColumnType(mixed $value, ColumnType $columnType): mixed
    {
        return match ($columnType) {
            ColumnType::INT, ColumnType::SMALLINT, ColumnType::MEDIUMINT, ColumnType::BIGINT,
            ColumnType::YEAR => $this->processInt($value),
            ColumnType::TINYINT => $this->processBool($value),
            ColumnType::DECIMAL, ColumnType::FLOAT, ColumnType::DOUBLE => $this->processFloat($value),
            ColumnType::VARCHAR, ColumnType::CHAR, ColumnType::TEXT,
            ColumnType::TINYTEXT, ColumnType::MEDIUMTEXT, ColumnType::LONGTEXT,
            ColumnType::ENUM, ColumnType::SET, ColumnType::TIME => $this->processString($value),
            ColumnType::DATE, ColumnType::DATETIME, ColumnType::TIMESTAMP => $this->processDateTime($value),
            ColumnType::BLOB, ColumnType::TINYBLOB, ColumnType::MEDIUMBLOB, ColumnType::LONGBLOB,
            ColumnType::BINARY, ColumnType::VARBINARY => $value, // Keep as-is
            ColumnType::JSON => $this->processJson($value),
            default => $value,
        };
    }

    /**
     * @param mixed $value
     * @param class-string $className
     * @return mixed
     * @throws ReflectionException
     */
    private function processValueByPhpType(mixed $value, string $className): mixed
    {
        if ($className === DateTime::class || is_subclass_of($className, DateTime::class)) {
            return $this->processDateTime($value);
        }

        // For other object types, try to instantiate
        if (is_array($value)) {
            return $this->processObject($value, $className);
        }

        return $value;
    }

    /**
     * @param string $column
     * @return string
     */
    private function snakeToCamelCase(string $column): string
    {
        $result = str_replace('_', '', ucwords($column, '_'));
        return lcfirst($result);
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function processInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function processBool(mixed $value): bool
    {
        return (bool) $value;
    }

    /**
     * @param mixed $value
     * @return float
     */
    private function processFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return 0.0;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function processString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        return (string) $value;
    }

    /**
     * @param mixed $value
     * @return DateTime
     */
    private function processDateTime(mixed $value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        try {
            return new DateTime($value);
        } catch (Exception $e) {
            // Log error or handle invalid date
            return new DateTime(); // Default to current time
        }
    }

    /**
     * @param mixed $value
     * @return array<mixed>
     */
    private function processJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return []; // Default empty array for invalid JSON
    }

    /**
     * @param mixed $value
     * @param class-string $className
     * @return object
     * @throws ReflectionException
     */
    private function processObject(mixed $value, string $className): object
    {
        if (is_array($value)) {
            // Recursively hydrate nested object
            return $this->hydrate($value, $className);
        }

        return new $className();
    }

    /**
     * @param object $entity
     * @param ReflectionClass<object> $reflection
     * @return void
     * @throws ReflectionException
     */
    private function initializeCollections(object $entity, ReflectionClass $reflection): void
    {
        $entityName = $entity::class;

        // Only initialize collections if dbMapping is available
        $dbMapping = $this->dbMapping;
        if ($dbMapping === null) {
            return;
        }

        // Initialize OneToMany collections with DatabaseCollection
        $oneToManyList = $dbMapping->getOneToMany($entityName);
        if (!empty($oneToManyList)) {
            foreach ($oneToManyList as $property => $oneToMany) {
                $this->initializeCollectionProperty($entity, $reflection, $property);
            }
        }

        // Initialize ManyToMany collections with DatabaseCollection
        $manyToManyList = $dbMapping->getManyToMany($entityName);
        if (!empty($manyToManyList)) {
            foreach ($manyToManyList as $property => $manyToMany) {
                $this->initializeCollectionProperty($entity, $reflection, $property);
            }
        }
    }

    /**
     * @param object $entity
     * @param ReflectionClass<object> $reflection
     * @param string $property
     * @return void
     */
    private function initializeCollectionProperty(object $entity, ReflectionClass $reflection, string $property): void
    {
        try {
            $reflectionProperty = $reflection->getProperty($property);

            // ALWAYS use DatabaseCollection for all relation collections to ensure change tracking
            if (!$reflectionProperty->isInitialized($entity)) {
                $reflectionProperty->setValue($entity, new DatabaseCollection());
            } else {
                // If already initialized, ALWAYS convert to DatabaseCollection
                $currentValue = $reflectionProperty->getValue($entity);
                if ($currentValue instanceof Collection && !($currentValue instanceof DatabaseCollection)) {
                    $reflectionProperty->setValue($entity, new DatabaseCollection($currentValue->items()));
                } elseif (!($currentValue instanceof DatabaseCollection)) {
                    // Initialize with empty DatabaseCollection if it's not a Collection at all
                    $reflectionProperty->setValue($entity, new DatabaseCollection());
                }
            }
        } catch (ReflectionException) {
            // Property doesn't exist, ignore
        }
    }

    /**
     * Check if a property represents a relation (OneToOne, ManyToOne, etc.)
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    private function isRelationProperty(string $entityClass, string $propertyName): bool
    {
        if ($this->dbMapping === null) {
            return false;
        }

        // Check if it's a OneToOne relation
        $oneToOneList = $this->dbMapping->getOneToOne($entityClass);
        if (!empty($oneToOneList) && isset($oneToOneList[$propertyName])) {
            return true;
        }

        // Check if it's a ManyToOne relation
        $manyToOneList = $this->dbMapping->getManyToOne($entityClass);
        if (!empty($manyToOneList) && isset($manyToOneList[$propertyName])) {
            return true;
        }

        return false;
    }

    /**
     * Fallback hydration when dbMapping is not available
     *
     * @param object $entity
     * @param array<string, mixed> $data
     * @param ReflectionClass<object> $reflection
     * @return void
     */
    private function fallbackHydration(object $entity, array $data, ReflectionClass $reflection): void
    {
        $entityName = $entity::class;

        foreach ($data as $column => $value) {
            // Convert snake_case to camelCase
            $propertyName = $this->snakeToCamelCase($column);

            if ($reflection->hasProperty($propertyName)) {
                try {
                    $property = $reflection->getProperty($propertyName);

                    // Process the value according to its type before assignment
                    $processedValue = $this->processValue($entityName, $propertyName, $value);

                    $property->setValue($entity, $processedValue);
                } catch (\ReflectionException $e) {
                    // Skip properties that can't be set
                    continue;
                }
            }
        }
    }

    /**
     * Check if a property is nullable based on mapping or reflection
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    private function isPropertyNullable(string $entityClass, string $propertyName): bool
    {
        // First check DbMapping if available
        if ($this->dbMapping !== null) {
            try {
                $nullable = $this->dbMapping->isNullable($entityClass, $propertyName);
                if ($nullable !== null) {
                    return $nullable;
                }
            } catch (\Exception $e) {
                // Fall through to reflection check
            }
        }

        // Fall back to reflection-based check
        try {
            $property = $this->getReflectionProperty($entityClass, $propertyName);
            if ($property !== null) {
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType) {
                    return $type->allowsNull();
                }
            }
        } catch (\Exception $e) {
            // If we can't determine, assume nullable
        }

        return true; // Default to nullable if we can't determine
    }

    /**
     * Process a value according to its type
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @param mixed $value
     * @return mixed
     */
    private function processValue(string $entityClass, string $propertyName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // First try to use DbMapping if available
        if ($this->dbMapping !== null) {
            try {
                $columnType = $this->getCachedColumnType($entityClass, $propertyName);

                if ($columnType !== null) {
                    return $this->processValueByColumnType($value, $columnType);
                }
            } catch (ReflectionException $e) {
                // If this fails, try the reflection-based approach
            }
        }

        // Fall back to attribute-based approach
        try {
            $property = $this->getReflectionProperty($entityClass, $propertyName);
            if ($property !== null) {
                // Try to use MtColumn attribute if available
                $mtColumnAttrs = $property->getAttributes(MtColumn::class);
                if (!empty($mtColumnAttrs)) {
                    $mtColumnAttr = $mtColumnAttrs[0];
                    $mtColumn = $mtColumnAttr->newInstance();
                    if ($mtColumn->columnType !== null) {
                        return $this->processValueByColumnType($value, $mtColumn->columnType);
                    }
                }

                // Use PHP's type information
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    // If the type is a class, process it accordingly
                    if (class_exists($typeName)) {
                        return $this->processValueByPhpType($value, $typeName);
                    }
                }
            }
        } catch (ReflectionException $e) {
            // If reflection fails, return value as-is
        }

        return $value;
    }
}
