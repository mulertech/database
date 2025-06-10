<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

/**
 * Détecteur optimisé de changements dans les entités
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final class ChangeDetector
{
    /** @var array<class-string, array<string, ReflectionProperty>> */
    private array $propertiesCache = [];

    /** @var array<class-string, array<string, string>> */
    private array $propertyTypesCache = [];

    /**
     * @param object $entity
     * @param array<string, mixed> $originalData
     * @return ChangeSet
     */
    public function computeChangeSet(object $entity, array $originalData): ChangeSet
    {
        $currentData = $this->extractCurrentData($entity);
        $changes = [];

        // Check for modified and new properties
        foreach ($currentData as $property => $currentValue) {
            $originalValue = $originalData[$property] ?? null;

            if (!$this->valuesAreEqual($originalValue, $currentValue, $property, $entity::class)) {
                $changes[$property] = new PropertyChange(
                    property: $property,
                    oldValue: $originalValue,
                    newValue: $currentValue
                );
            }
        }

        // Check for removed properties (if original had more properties)
        foreach ($originalData as $property => $originalValue) {
            if (!array_key_exists($property, $currentData)) {
                $changes[$property] = new PropertyChange(
                    property: $property,
                    oldValue: $originalValue,
                    newValue: null
                );
            }
        }

        return new ChangeSet($entity::class, $changes);
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    public function extractCurrentData(object $entity): array
    {
        $className = $entity::class;

        if (!isset($this->propertiesCache[$className])) {
            $this->cacheProperties($className);
        }

        $data = [];
        foreach ($this->propertiesCache[$className] as $propertyName => $property) {
            try {
                $data[$propertyName] = $property->getValue($entity);
            } catch (\Error $e) {
                // Handle uninitialized properties
                $data[$propertyName] = null;
            }
        }

        return $data;
    }

    /**
     * @param class-string $className
     * @return void
     */
    private function cacheProperties(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $properties = [];
        $types = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            $propertyName = $property->getName();

            $properties[$propertyName] = $property;

            // Cache property type for optimized comparison
            $type = $property->getType();
            if ($type !== null) {
                if ($type instanceof ReflectionNamedType) {
                    $types[$propertyName] = $type->getName();
                } elseif (method_exists($type, 'getTypes')) {
                    // Pour les types d'union (PHP 8.0+)
                    $types[$propertyName] = $this->getFirstTypeFromUnion($type);
                }
            }
        }

        $this->propertiesCache[$className] = $properties;
        $this->propertyTypesCache[$className] = $types;
    }

    /**
     * Extrait le premier type nommé d'un type d'union
     *
     * @param ReflectionType $type
     * @return string
     */
    private function getFirstTypeFromUnion(ReflectionType $type): string
    {
        if (method_exists($type, 'getTypes')) {
            $types = $type->getTypes();
            if (!empty($types) && $types[0] instanceof ReflectionNamedType) {
                return $types[0]->getName();
            }
        }
        return 'mixed';
    }

    /**
     * @param mixed $value1
     * @param mixed $value2
     * @param string $property
     * @param class-string|string $className
     * @return bool
     */
    private function valuesAreEqual(mixed $value1, mixed $value2, string $property, string $className): bool
    {
        // Handle identical values first (fastest path)
        if ($value1 === $value2) {
            return true;
        }

        // Handle null values
        if ($value1 === null || $value2 === null) {
            return false;
        }

        // Get property type for optimized comparison
        $propertyType = $className !== '' && isset($this->propertyTypesCache[$className][$property])
            ? $this->propertyTypesCache[$className][$property]
            : null;

        // Handle objects
        if (is_object($value1) && is_object($value2)) {
            return $this->objectsAreEqual($value1, $value2, $propertyType);
        }

        // Handle arrays
        if (is_array($value1) && is_array($value2)) {
            return $this->arraysAreEqual($value1, $value2);
        }

        // Handle floats with precision
        if (is_float($value1) && is_float($value2)) {
            return abs($value1 - $value2) < PHP_FLOAT_EPSILON;
        }

        // Handle string/numeric comparisons
        if ((is_string($value1) && is_numeric($value2)) || (is_numeric($value1) && is_string($value2))) {
            return (string)$value1 === (string)$value2;
        }

        // Handle scalars
        return $value1 == $value2;
    }

    /**
     * @param object $obj1
     * @param object $obj2
     * @param string|null $expectedType
     * @return bool
     */
    private function objectsAreEqual(object $obj1, object $obj2, ?string $expectedType): bool
    {
        // Different classes are different
        if ($obj1::class !== $obj2::class) {
            return false;
        }

        // For entities, compare by ID if possible
        if (method_exists($obj1, 'getId') && method_exists($obj2, 'getId')) {
            $id1 = $obj1->getId();
            $id2 = $obj2->getId();

            // If both have IDs, compare them
            if ($id1 !== null && $id2 !== null) {
                return $id1 === $id2;
            }
        }

        // For DateTime objects, compare timestamps with microseconds
        if ($obj1 instanceof DateTimeInterface && $obj2 instanceof DateTimeInterface) {
            return $obj1->format('Y-m-d H:i:s.u') === $obj2->format('Y-m-d H:i:s.u');
        }

        // For known value objects (based on type hint)
        if ($expectedType !== null && $this->isValueObject($expectedType)) {
            return $this->valueObjectsAreEqual($obj1, $obj2);
        }

        // For other objects, use identity comparison (same instance)
        return $obj1 === $obj2;
    }

    /**
     * @param array<mixed> $array1
     * @param array<mixed> $array2
     * @return bool
     */
    private function arraysAreEqual(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        // For indexed arrays, compare in order
        if (array_is_list($array1) && array_is_list($array2)) {
            foreach ($array1 as $index => $value) {
                if (!isset($array2[$index]) || !$this->valuesAreEqual($value, $array2[$index], '', '')) {
                    return false;
                }
            }
            return true;
        }

        // For associative arrays, compare by key
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                return false;
            }

            if (!$this->valuesAreEqual($value, $array2[$key], '', '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $className
     * @return bool
     */
    private function isValueObject(string $className): bool
    {
        // Common value object types
        $valueObjectTypes = [
            'DateTimeImmutable',
            'DateTime',
            'DateTimeInterface',
            'DateInterval',
            'Money',
            'Uuid',
        ];

        foreach ($valueObjectTypes as $type) {
            if (str_contains($className, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param object $obj1
     * @param object $obj2
     * @return bool
     */
    private function valueObjectsAreEqual(object $obj1, object $obj2): bool
    {
        // For value objects, use string representation or __toString if available
        if (method_exists($obj1, '__toString') && method_exists($obj2, '__toString')) {
            return (string)$obj1 === (string)$obj2;
        }

        // Fallback to normal object comparison
        return $obj1 == $obj2;
    }

    /**
     * @return array{cachedClasses: int, totalProperties: int, memoryUsage: int}
     */
    public function getStatistics(): array
    {
        $totalProperties = 0;
        foreach ($this->propertiesCache as $properties) {
            $totalProperties += count($properties);
        }

        return [
            'cachedClasses' => count($this->propertiesCache),
            'totalProperties' => $totalProperties,
            'memoryUsage' => memory_get_usage(true),
        ];
    }

    /**
     * @param class-string|null $className
     * @return void
     */
    public function clearCache(?string $className = null): void
    {
        if ($className === null) {
            $this->propertiesCache = [];
            $this->propertyTypesCache = [];
        } else {
            unset($this->propertiesCache[$className], $this->propertyTypesCache[$className]);
        }
    }
}
