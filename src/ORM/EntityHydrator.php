<?php

namespace MulerTech\Database\ORM;

use DateTime;
use Exception;
use MulerTech\Database\Mapping\ColumnType;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\Mapping\MtManyToOne;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * EntityHydrator - Hydrates entities from database results with type handling
 *
 * This class handles the conversion of database results into entity objects
 * with proper typing and sanitization.
 * @package Mulertech\Database
 * @author Sébastien Muler
 */
class EntityHydrator
{
    /**
     * @var DbMappingInterface|null
     */
    private ?DbMappingInterface $dbMapping;

    /**
     * EntityHydrator constructor.
     *
     * @param DbMappingInterface|null $dbMapping
     */
    public function __construct(?DbMappingInterface $dbMapping = null)
    {
        $this->dbMapping = $dbMapping;
    }

    /**
     * Hydrate an entity with data from database result.
     *
     * @param array<string, mixed> $data
     * @param class-string $entityName
     * @return object
     * @throws ReflectionException
     */
    public function hydrate(array $data, string $entityName): object
    {
        $entity = new $entityName();
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

        foreach ($data as $column => $value) {
            // Find the property corresponding to this column
            $propertyName = $columnToPropertyMap[$column] ?? $this->snakeToCamelCase($column);

            // Check if the property has a mapping of type MtOneToOne or MtManyToOne
            if ($this->isRelationMapping($entityName, $propertyName)) {
                continue; // Skip hydration for relational mappings
            }

            // Check if a setter method exists for this property
            $setterMethod = 'set' . ucfirst($propertyName);
            if (method_exists($entity, $setterMethod)) {
                // Process the value based on property type
                $processedValue = $this->processValue($entityName, $propertyName, $value);

                // Call the setter with the processed value
                $entity->$setterMethod($processedValue);
            }
        }

        return $entity;
    }

    /**
     * Check if a property has a relational mapping (MtOneToOne or MtManyToOne).
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    private function isRelationMapping(string $entityClass, string $propertyName): bool
    {
        try {
            $reflection = new ReflectionClass($entityClass);
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $attributes = $property->getAttributes();

                foreach ($attributes as $attribute) {
                    $attributeClass = $attribute->getName();
                    if ($attributeClass === MtOneToOne::class || $attributeClass === MtManyToOne::class) {
                        return true;
                    }
                }
            }
        } catch (ReflectionException $e) {
            // Ignore reflection errors
        }

        return false;
    }

    /**
     * Process a value based on property mapping and type information.
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
                $columnType = $this->dbMapping->getColumnType($entityClass, $propertyName);

                if ($columnType !== null) {
                    return $this->processValueByColumnType($value, $columnType);
                }
            } catch (ReflectionException $e) {
                // If this fails, try the reflection-based approach
            }
        }

        // Fall back to attribute-based approach
        try {
            $reflection = new ReflectionClass($entityClass);
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);

                // Try to use MtColumn attribute if available
                $mtColumnAttr = $property->getAttributes(MtColumn::class)[0] ?? null;
                if ($mtColumnAttr !== null) {
                    $mtColumn = $mtColumnAttr->newInstance();
                    if ($mtColumn->columnType !== null) {
                        return $this->processValueByColumnType($value, $mtColumn->columnType);
                    }
                }

                // Fall back to PHP type hints
                return $this->processValueByType($value, $property);
            }
        } catch (ReflectionException $e) {
            // Ignore reflection errors
        }

        // Default handling: sanitize as text if all else fails
        return $this->sanitizeText($value);
    }

    /**
     * Convert snake_case to camelCase.
     *
     * @param string $input
     * @return string
     */
    private function snakeToCamelCase(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }

    /**
     * Process a value based on property type.
     *
     * @param mixed $value
     * @param ReflectionProperty $property
     * @return mixed
     */
    private function processValueByType(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return null;
        }

        // Get the property type
        $type = $property->getType();
        if ($type === null) {
            return $this->sanitizeText($value); // Default to text
        }

        if (!method_exists($type, 'getName')) {
            return $this->sanitizeText($value); // Fallback for non-standard types
        }
        $typeName = $type->getName();

        switch ($typeName) {
            case 'DateTime':
                return $this->processDateTime($value);
            case 'array':
                return $this->processJson($value);
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'bool':
                return (bool)$value;
            case 'string':
                return $this->sanitizeText($value);
            default:
                // Check if it's a class that needs to be instantiated
                if (class_exists($typeName)) {
                    return $this->processObject($value, $typeName);
                }
                return $value;
        }
    }

    /**
     * Process a value based on MySQL column type.
     *
     * @param mixed $value
     * @param ColumnType $columnType
     * @return mixed
     */
    private function processValueByColumnType(mixed $value, ColumnType $columnType): mixed
    {
        switch ($columnType) {
            // Integer types
            case ColumnType::INT:
            case ColumnType::TINYINT:
            case ColumnType::SMALLINT:
            case ColumnType::MEDIUMINT:
            case ColumnType::BIGINT:
                // Handle TINYINT(1) as boolean
                if ($columnType === ColumnType::TINYINT && $this->isBoolean($value)) {
                    return (bool)$value;
                }
                return (int)$value;

                // Decimal types
            case ColumnType::DECIMAL:
            case ColumnType::NUMERIC:
            case ColumnType::FLOAT:
            case ColumnType::DOUBLE:
            case ColumnType::REAL:
                return (float)$value;

                // String, text, enum and set types
            case ColumnType::CHAR:
            case ColumnType::LONGTEXT:
            case ColumnType::MEDIUMTEXT:
            case ColumnType::TINYTEXT:
            case ColumnType::TEXT:
            case ColumnType::SET:
            case ColumnType::ENUM:
            case ColumnType::VARCHAR:
                return $this->sanitizeText($value);

                // Date and time types
            case ColumnType::DATE:
            case ColumnType::DATETIME:
            case ColumnType::TIMESTAMP:
                return $this->processDateTime($value);

                // Boolean types
            case ColumnType::BOOLEAN:
            case ColumnType::BOOL:
                return (bool)$value;

                // JSON type
            case ColumnType::JSON:
                return $this->processJson($value);

                // Default, Binary and BLOB types : return as is, possibly handle as streams or base64
            default:
                return $value;
        }
    }

    /**
     * Check if a value is likely intended to be a boolean.
     *
     * @param mixed $value
     * @return bool
     */
    private function isBoolean(mixed $value): bool
    {
        return $value === '0' || $value === '1' || $value === 0 || $value === 1 ||
               $value === true || $value === false;
    }

    /**
     * Sanitize text to prevent XSS attacks.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeText(mixed $value): string
    {
        if (!is_string($value)) {
            return (string)$value;
        }

        return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Process a value into DateTime object.
     *
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
     * Process a JSON string into an array.
     *
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
     * Process a value into an object of specified class.
     *
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
