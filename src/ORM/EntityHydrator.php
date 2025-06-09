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
        $columnToPropertyMap = $this->getColumnToPropertyMap($entityName);

        foreach ($data as $column => $value) {
            // Find the property corresponding to this column
            $propertyName = $columnToPropertyMap[$column] ?? $this->snakeToCamelCase($column);

            // Check if the property has a mapping of type MtOneToOne or MtManyToOne
            if ($this->isRelationMapping($entityName, $propertyName)) {
                continue; // Skip hydration for relational mappings
            }

            // Check if a setter method exists for this property
            if ($this->hasSetterMethod($entityName, $propertyName)) {
                $setterMethod = 'set' . ucfirst($propertyName);

                // Process the value based on property type
                $processedValue = $this->processValue($entityName, $propertyName, $value);

                // Call the setter with the processed value
                $entity->$setterMethod($processedValue);
            }
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
        $stats = $this->metadataCache->getStats();

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
     * @return bool
     */
    private function hasSetterMethod(string $entityClass, string $propertyName): bool
    {
        $cacheKey = $entityClass . '::' . $propertyName;

        if (isset($this->setterCache[$cacheKey])) {
            return $this->setterCache[$cacheKey];
        }

        $setterMethod = 'set' . ucfirst($propertyName);
        $hasSetter = method_exists($entityClass, $setterMethod);

        $this->setterCache[$cacheKey] = $hasSetter;

        return $hasSetter;
    }

    /**
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    private function isRelationMapping(string $entityClass, string $propertyName): bool
    {
        $cacheKey = 'relation:' . $entityClass . ':' . $propertyName;

        $cached = $this->metadataCache->getPropertyMetadata($entityClass, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $isRelation = false;

        try {
            $property = $this->getReflectionProperty($entityClass, $propertyName);
            if ($property !== null) {
                $attributes = $property->getAttributes();

                foreach ($attributes as $attribute) {
                    $attributeClass = $attribute->getName();
                    if ($attributeClass === MtOneToOne::class || $attributeClass === MtManyToOne::class) {
                        $isRelation = true;
                        break;
                    }
                }
            }
        } catch (ReflectionException $e) {
            // Ignore reflection errors
        }

        $this->metadataCache->setPropertyMetadata($entityClass, $cacheKey, $isRelation);

        return $isRelation;
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
}
