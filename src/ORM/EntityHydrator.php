<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTime;
use Error;
use Exception;
use JsonException;
use MulerTech\Collections\Collection;
use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\Types\ColumnType;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use TypeError;

/**
 * Class EntityHydrator
 *
 * EntityHydrator with metadata caching for improved performance
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityHydrator
{
    /**
     * @var MetadataCache
     */
    private readonly MetadataCache $metadataCache;

    /**
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $reflectionCache = [];

    /**
     * @param DbMappingInterface $dbMapping
     * @param MetadataCache|null $metadataCache
     */
    public function __construct(
        private readonly DbMappingInterface $dbMapping,
        ?MetadataCache $metadataCache = null
    ) {
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
                    throw new TypeError("Property $property of $entityName cannot be null");
                }

                $reflectionProperty = $reflection->getProperty($property);
                $reflectionProperty->setValue($entity, $processedValue);
            }
        } catch (Exception) {
            throw new RuntimeException("Failed to hydrate entity of type $entityName");
        }

        return $entity;
    }

    /**
     * Extract data from an entity (reverse of hydration)
     *
     * @param object $entity
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public function extract(object $entity): array
    {
        $entityClass = $entity::class;
        $reflection = new ReflectionClass($entityClass);

        // Get all non-static properties
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = $property;
            }
        }

        $data = [];

        foreach ($properties as $propertyName => $property) {
            try {
                $data[$propertyName] = $property->getValue($entity);
            } catch (Error) {
                // Handle uninitialized properties
                $data[$propertyName] = null;
            }
        }

        return $data;
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
     * @param string $propertyName
     * @return ColumnType|null
     * @throws ReflectionException
     */
    private function getCachedColumnType(string $entityClass, string $propertyName): ?ColumnType
    {
        $cacheKey = 'column_type:' . $entityClass . ':' . $propertyName;

        $cached = $this->metadataCache->getPropertyMetadata($entityClass, $cacheKey);
        if ($cached instanceof ColumnType) {
            return $cached;
        }

        $columnType = $this->dbMapping->getColumnType($entityClass, $propertyName);

        if ($columnType !== null) {
            $this->metadataCache->setPropertyMetadata($entityClass, $cacheKey, $columnType);
        }

        return $columnType;
    }

    /**
     * @param mixed $value
     * @param ColumnType $columnType
     * @return mixed
     * @throws JsonException
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
            ColumnType::DATE, ColumnType::DATETIME, ColumnType::TIMESTAMP => $this->processDateTime($value), // Keep as-is
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

        return match (true) {
            is_null($value) => '',
            is_scalar($value) => (string) $value,
            default => throw new TypeError('Value cannot be converted to string')
        };
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
            $dateString = match (true) {
                is_string($value) => $value,
                is_null($value) => 'now',
                is_scalar($value) => (string) $value,
                default => throw new TypeError('Value cannot be converted to date string')
            };

            return new DateTime($dateString);
        } catch (Exception) {
            // Log error or handle invalid date
            return new DateTime(); // Default to current time
        }
    }

    /**
     * @param mixed $value
     * @return array<int|string, mixed>
     * @throws JsonException
     */
    private function processJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
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
            // Ensure the array has string keys before passing to hydrate
            $arrayData = [];
            foreach ($value as $key => $val) {
                $stringKey = is_string($key) ? $key : (string)$key;
                $arrayData[$stringKey] = $val;
            }
            // Recursively hydrate nested object
            return $this->hydrate($arrayData, $className);
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

        // Initialize OneToMany collections with DatabaseCollection
        $oneToManyList = $this->dbMapping->getOneToMany($entityName);
        if (!empty($oneToManyList)) {
            foreach ($oneToManyList as $property => $oneToMany) {
                $this->initializeCollectionProperty($entity, $reflection, $property);
            }
        }

        // Initialize ManyToMany collections with DatabaseCollection
        $manyToManyList = $this->dbMapping->getManyToMany($entityName);
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

                return;
            }

            // If already initialized, ALWAYS convert to DatabaseCollection
            $currentValue = $reflectionProperty->getValue($entity);
            if ($currentValue instanceof Collection && !($currentValue instanceof DatabaseCollection)) {
                // Filter items to ensure they are objects
                $items = $currentValue->items();
                /** @var array<object> $objectItems */
                $objectItems = array_filter($items, static fn ($item): bool => is_object($item));
                $reflectionProperty->setValue($entity, new DatabaseCollection($objectItems));

                return;
            }

            if (!($currentValue instanceof DatabaseCollection)) {
                // Initialize with empty DatabaseCollection if it's not a Collection at all
                $reflectionProperty->setValue($entity, new DatabaseCollection());
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
     * @throws ReflectionException
     */
    private function isRelationProperty(string $entityClass, string $propertyName): bool
    {
        // Check if it's a OneToOne relation
        $oneToOneList = $this->dbMapping->getOneToOne($entityClass);
        if (!empty($oneToOneList) && isset($oneToOneList[$propertyName])) {
            return true;
        }

        // Check if it's a ManyToOne relation
        $manyToOneList = $this->dbMapping->getManyToOne($entityClass);
        return !empty($manyToOneList) && isset($manyToOneList[$propertyName]);
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
        try {
            $nullable = $this->dbMapping->isNullable($entityClass, $propertyName);
            if ($nullable !== null) {
                return $nullable;
            }
        } catch (Exception) {
            // Fall through to reflection check
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
        } catch (Exception) {
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
     * @throws JsonException
     */
    public function processValue(string $entityClass, string $propertyName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // First try to use DbMapping if available
        try {
            $columnType = $this->getCachedColumnType($entityClass, $propertyName);

            if ($columnType !== null) {
                return $this->processValueByColumnType($value, $columnType);
            }
        } catch (ReflectionException) {
            // If this fails, try the reflection-based approach
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
        } catch (ReflectionException) {
            // If reflection fails, return value as-is
        }

        return $value;
    }
}
