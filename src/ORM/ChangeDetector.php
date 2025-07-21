<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeInterface;
use MulerTech\Collections\Collection;
use ReflectionClass;
use ReflectionException;

/**
 * Detects changes in entities for ORM tracking
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ChangeDetector
{
    /**
     * @param object $entity
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public function extractCurrentData(object $entity): array
    {
        $reflection = new ReflectionClass($entity);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();

            // Skip uninitialized properties
            if (!$property->isInitialized($entity)) {
                $data[$propertyName] = null;
                continue;
            }

            $value = $property->getValue($entity);

            // Handle different types of values
            if ($value === null) {
                $data[$propertyName] = null;
            } elseif (is_scalar($value)) {
                $data[$propertyName] = $value;
            } elseif ($value instanceof DateTimeInterface) {
                $data[$propertyName] = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value) && method_exists($value, 'getId')) {
                // For entities, store a serialized reference
                $id = $value->getId();
                $data[$propertyName] = [
                    '__entity__' => $value::class,
                    '__id__' => $id,
                    '__hash__' => spl_object_id($value), // Add object hash for better tracking
                ];
            } elseif ($value instanceof Collection) {
                // For collections, store a simplified representation
                $items = [];
                foreach ($value as $item) {
                    if (is_object($item) && method_exists($item, 'getId')) {
                        $items[] = [
                            '__entity__' => $item::class,
                            '__id__' => $item->getId(),
                            '__hash__' => spl_object_id($item),
                        ];
                    }
                }
                $data[$propertyName] = [
                    '__collection__' => true,
                    '__items__' => $items,
                ];
            } elseif (is_array($value)) {
                $data[$propertyName] = $value;
            } elseif (is_object($value)) {
                // For other objects, store class name and object hash
                $data[$propertyName] = [
                    '__object__' => $value::class,
                    '__hash__' => spl_object_id($value),
                ];
            } else {
                $data[$propertyName] = $value;
            }
        }

        return $data;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $originalData
     * @return ChangeSet
     * @throws ReflectionException
     */
    public function computeChangeSet(object $entity, array $originalData): ChangeSet
    {
        $currentData = $this->extractCurrentData($entity);
        $changes = [];

        // Check for changes in current data vs original
        foreach ($currentData as $property => $currentValue) {
            $originalValue = $originalData[$property] ?? null;

            if (!$this->valuesAreEqual($originalValue, $currentValue)) {
                $changes[$property] = new PropertyChange(
                    property: $property,
                    oldValue: $originalValue,
                    newValue: $currentValue
                );
            }
        }

        // Check for properties that were removed (exist in original but not in current)
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
     * @param mixed $value1
     * @param mixed $value2
     * @return bool
     */
    private function valuesAreEqual(mixed $value1, mixed $value2): bool
    {
        // Handle null comparisons
        if ($value1 === null && $value2 === null) {
            return true;
        }
        if ($value1 === null || $value2 === null) {
            return false;
        }

        // Handle scalar values
        if (is_scalar($value1) && is_scalar($value2)) {
            return $value1 === $value2;
        }

        // Handle entity references (serialized format)
        if (is_array($value1) && is_array($value2)) {
            // Both are entity references
            if (isset($value1['__entity__'], $value2['__entity__'])) {
                // First check if classes are the same
                if ($value1['__entity__'] !== $value2['__entity__']) {
                    return false;
                }

                // Check if both have ID keys and compare their values (including null)
                if (array_key_exists('__id__', $value1) && array_key_exists('__id__', $value2)) {
                    $id1 = $value1['__id__'];
                    $id2 = $value2['__id__'];

                    // If both IDs are not null, compare them
                    if ($id1 !== null && $id2 !== null) {
                        return $id1 === $id2;
                    }
                    // If one ID is null and the other is not, they're different
                    if (($id1 === null) !== ($id2 === null)) {
                        return false;
                    }
                    // Both IDs are null, continue to hash comparison
                }

                // If IDs are both null or missing, compare by object hash if available
                if (isset($value1['__hash__'], $value2['__hash__'])) {
                    return $value1['__hash__'] === $value2['__hash__'];
                }

                // Fallback: if no hash and both IDs are null, consider them equal if same class
                return true;
            }

            // Both are object references without entity info
            if (isset($value1['__object__'], $value2['__object__'])) {
                if ($value1['__object__'] !== $value2['__object__']) {
                    return false;
                }

                // Compare by hash if available
                if (isset($value1['__hash__'], $value2['__hash__'])) {
                    return $value1['__hash__'] === $value2['__hash__'];
                }

                return true;
            }

            // Both are collections
            if (isset($value1['__collection__'], $value2['__collection__'])) {
                return $this->collectionsAreEqual($value1['__items__'] ?? [], $value2['__items__'] ?? []);
            }

            // Regular array comparison
            return $value1 === $value2;
        }

        // Handle different types - they're not equal
        if (gettype($value1) !== gettype($value2)) {
            return false;
        }

        // For other complex types, try basic comparison
        return $value1 == $value2;
    }

    /**
     * @param array<int|string, mixed> $items1
     * @param array<int|string, mixed> $items2
     * @return bool
     */
    private function collectionsAreEqual(array $items1, array $items2): bool
    {
        if (count($items1) !== count($items2)) {
            return false;
        }

        // Sort both arrays by entity class and ID for comparison
        $sort = static function ($a, $b) {
            $classCompare = strcmp($a['__entity__'] ?? '', $b['__entity__'] ?? '');
            if ($classCompare !== 0) {
                return $classCompare;
            }
            return ($a['__id__'] ?? 0) <=> ($b['__id__'] ?? 0);
        };

        usort($items1, $sort);
        usort($items2, $sort);

        return $items1 === $items2;
    }
}
