<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use DateTimeInterface;
use Error;
use Exception;
use JsonException;
use MulerTech\Database\Mapping\Types\ColumnType;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use TypeError;

/**
 * Class EntityFactory
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class EntityFactory
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /** @var array<class-string, array<string, ReflectionProperty>> */
    private array $propertiesCache = [];

    /**
     * @param EntityHydrator $hydrator
     */
    public function __construct(
        private readonly EntityHydrator $hydrator,
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @return object
     * @throws ReflectionException
     */
    public function create(string $entityClass, array $data = []): object
    {
        if (!isset($this->reflectionCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        // Create instance
        $entity = new $entityClass();

        // Hydrate properties
        if (!empty($data)) {
            $this->hydrate($entity, $data);
        }

        /** @var T $entity */
        return $entity;
    }

    /**
     * Create entity from database data with proper type conversion and hydration
     *
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $dbData
     * @return object
     * @throws ReflectionException|JsonException
     */
    public function createFromDbData(string $entityClass, array $dbData): object
    {
        // Use the hydrator to properly convert and hydrate database data
        return $this->hydrator->hydrate($dbData, $entityClass);
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @return void
     * @throws ReflectionException
     */
    public function hydrate(object $entity, array $data): void
    {
        $entityClass = $entity::class;

        if (!isset($this->propertiesCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        $properties = $this->propertiesCache[$entityClass];

        foreach ($data as $propertyName => $value) {
            if (isset($properties[$propertyName])) {
                $property = $properties[$propertyName];

                try {
                    // Convert value if needed based on property type
                    $convertedValue = $this->convertValue($value, $property, $entityClass);
                    $property->setValue($entity, $convertedValue);
                } catch (TypeError $e) {
                    // Log or handle type conversion errors
                    throw new RuntimeException(
                        sprintf(
                            'Failed to hydrate property "%s" of class "%s": %s',
                            $propertyName,
                            $entityClass,
                            $e->getMessage()
                        ),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public function extract(object $entity): array
    {
        $entityClass = $entity::class;

        if (!isset($this->propertiesCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        $properties = $this->propertiesCache[$entityClass];
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
     * @return void
     * @throws ReflectionException
     */
    private function cacheReflectionData(string $entityClass): void
    {
        $reflection = new ReflectionClass($entityClass);
        $this->reflectionCache[$entityClass] = $reflection;

        // Cache properties
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = $property;
            }
        }
        $this->propertiesCache[$entityClass] = $properties;
    }

    /**
     * Convert value using MulerTech mapping system for proper type conversion
     * @param mixed $value
     * @param ReflectionProperty $property
     * @param class-string $entityClass
     * @return mixed
     */
    private function convertValue(mixed $value, ReflectionProperty $property, string $entityClass): mixed
    {
        if ($value === null) {
            return null;
        }

        // Try to get the column type from DbMapping first
        $dbMapping = $this->hydrator->getDbMapping();
        if ($dbMapping !== null) {
            try {
                $columnType = $dbMapping->getColumnType($entityClass, $property->getName());
                if ($columnType !== null) {
                    // Type guard: only pass supported types to convertValueByColumnType
                    if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
                        return $this->convertValueByColumnType($value, $columnType);
                    }
                    // For unsupported types, fall back to reflection
                }
            } catch (Exception) {
                // Fall back to reflection-based conversion if mapping fails
            }
        }

        // Fallback to reflection-based type conversion
        return $this->convertValueByReflection($value, $property);
    }

    /**
     * Convert value based on MulerTech ColumnType
     * @param string|int|float|bool|null $value Value from database (PDO returns these types)
     * @param ColumnType $columnType
     * @return mixed
     */
    private function convertValueByColumnType(string|int|float|bool|null $value, ColumnType $columnType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($columnType) {
            // Integer types
            ColumnType::INT, ColumnType::TINYINT, ColumnType::SMALLINT,
            ColumnType::MEDIUMINT, ColumnType::BIGINT =>
                is_numeric($value) ? (int) $value : 0,

            // Float types, Decimal type
            ColumnType::FLOAT, ColumnType::DOUBLE, ColumnType::DECIMAL =>
                is_numeric($value) ? (float) $value : 0.0,

            // Date/Time types
            ColumnType::DATE, ColumnType::DATETIME, ColumnType::TIMESTAMP =>
                $this->convertToDateTime($value),

            // YEAR type
            ColumnType::YEAR =>
                is_numeric($value) ? (int) $value : (int) date('Y'),

            // JSON type
            ColumnType::JSON =>
                $this->convertJsonValue($value),

            // String types, TIME type, Enum and Set types
            ColumnType::CHAR, ColumnType::VARCHAR, ColumnType::TEXT,
            ColumnType::TINYTEXT, ColumnType::MEDIUMTEXT, ColumnType::LONGTEXT,
            ColumnType::TIME, ColumnType::ENUM, ColumnType::SET =>
                (string) $value,

            // Binary types, Geometry types, Default case
            default => $value
        };
    }

    /**
     * Fallback conversion based on PHP reflection type
     * @param mixed $value
     * @param ReflectionProperty $property
     * @return mixed
     */
    private function convertValueByReflection(mixed $value, ReflectionProperty $property): mixed
    {
        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // Handle basic type conversions with proper validation
        return match ($typeName) {
            'int' => is_numeric($value) ? (int) $value : 0,
            'float' => is_numeric($value) ? (float) $value : 0.0,
            'string' => is_scalar($value) ? (string) $value : '',
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            'DateTime', 'DateTimeImmutable' => $this->convertToDateTime($value),
            default => $value
        };
    }

    /**
     * Convert value to DateTime object
     * @param mixed $value
     * @return DateTimeInterface|DateTimeImmutable|null
     */
    private function convertToDateTime(mixed $value): null|DateTimeInterface|DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value) && !empty($value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Convert JSON value
     * @param mixed $value
     * @return mixed
     */
    private function convertJsonValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return [];
            }
        }

        return $value;
    }
}
