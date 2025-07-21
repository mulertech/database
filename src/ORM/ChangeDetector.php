<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeInterface;
use InvalidArgumentException;
use MulerTech\Collections\Collection;
use ReflectionClass;

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
            $data[$propertyName] = $this->processValue($value);
        }

        return $data;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $originalData
     * @return ChangeSet
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

        $type1 = $this->getValueType($value1);
        $type2 = $this->getValueType($value2);

        if ($type1 !== $type2) {
            return false;
        }

        return match ($type1) {
            'scalar', 'array' => $value1 === $value2,
            'entity' => $this->compareEntityReferences(
                $this->castToEntityArray($value1),
                $this->castToEntityArray($value2)
            ),
            'object' => $this->compareObjectReferences(
                $this->castToObjectArray($value1),
                $this->castToObjectArray($value2)
            ),
            'collection' => $this->compareCollections(
                $this->castToCollectionArray($value1),
                $this->castToCollectionArray($value2)
            ),
            default => $value1 == $value2,
        };
    }

    /**
     * Process a value and return its serialized representation
     */
    private function processValue(mixed $value): mixed
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $this->processDateTime($value),
            $value instanceof Collection => $this->processCollection($value),
            is_object($value) && method_exists($value, 'getId') => $this->processEntity($value),
            is_object($value) => $this->processObject($value),
            default => $value,
        };
    }

    /**
     * Process DateTime objects
     */
    private function processDateTime(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }

    /**
     * Process entity objects with getId method
     * @return array{__entity__: class-string, __id__: mixed, __hash__: int}
     */
    private function processEntity(object $value): array
    {
        if (!method_exists($value, 'getId')) {
            throw new InvalidArgumentException('Entity must have getId method');
        }

        return [
            '__entity__' => $value::class,
            '__id__' => $value->getId(),
            '__hash__' => spl_object_id($value),
        ];
    }

    /**
     * Process Collection objects
     * @param Collection<int|string, mixed> $value
     * @return array{__collection__: bool, __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>}
     */
    private function processCollection(Collection $value): array
    {
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
        return [
            '__collection__' => true,
            '__items__' => $items,
        ];
    }

    /**
     * Process generic objects
     * @return array{__object__: class-string, __hash__: int}
     */
    private function processObject(object $value): array
    {
        return [
            '__object__' => $value::class,
            '__hash__' => spl_object_id($value),
        ];
    }

    /**
     * Get the type of value for comparison purposes
     */
    private function getValueType(mixed $value): string
    {
        return match (true) {
            is_scalar($value) => 'scalar',
            is_array($value) && isset($value['__entity__']) => 'entity',
            is_array($value) && isset($value['__object__']) => 'object',
            is_array($value) && isset($value['__collection__']) => 'collection',
            is_array($value) => 'array',
            default => 'other',
        };
    }

    /**
     * Compare entity references
     * @param array{__entity__: class-string, __id__: mixed, __hash__: int} $value1
     * @param array{__entity__: class-string, __id__: mixed, __hash__: int} $value2
     * @return bool
     */
    private function compareEntityReferences(array $value1, array $value2): bool
    {
        // First check if classes are the same
        if ($value1['__entity__'] !== $value2['__entity__']) {
            return false;
        }

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
        // Both IDs are null, compare by object hash
        return $value1['__hash__'] === $value2['__hash__'];
    }

    /**
     * Compare object references
     * @param array{__object__: class-string, __hash__: int} $value1
     * @param array{__object__: class-string, __hash__: int} $value2
     * @return bool
     */
    private function compareObjectReferences(array $value1, array $value2): bool
    {
        if ($value1['__object__'] !== $value2['__object__']) {
            return false;
        }

        return $value1['__hash__'] === $value2['__hash__'];
    }

    /**
     * Compare collections
     * @param array{__collection__: bool, __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>} $value1
     * @param array{__collection__: bool, __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>} $value2
     * @return bool
     */
    private function compareCollections(array $value1, array $value2): bool
    {
        return $this->collectionsAreEqual($value1['__items__'], $value2['__items__']);
    }

    /**
     * @param array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}> $items1
     * @param array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}> $items2
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

    /**
     * Cast mixed value to entity array type
     * @param mixed $value
     * @return array{__entity__: class-string, __id__: mixed, __hash__: int}
     */
    private function castToEntityArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Value is not an array');
        }

        if (!isset($value['__entity__']) || !is_string($value['__entity__'])) {
            throw new InvalidArgumentException('Missing or invalid __entity__ key');
        }

        if (!array_key_exists('__id__', $value)) {
            throw new InvalidArgumentException('Missing __id__ key');
        }

        if (!isset($value['__hash__']) || !is_int($value['__hash__'])) {
            throw new InvalidArgumentException('Missing or invalid __hash__ key');
        }

        // Verify that __entity__ is a valid class string
        if (!class_exists($value['__entity__'])) {
            throw new InvalidArgumentException('Invalid class name in __entity__');
        }

        /** @var class-string $entityClass */
        $entityClass = $value['__entity__'];

        return [
            '__entity__' => $entityClass,
            '__id__' => $value['__id__'],
            '__hash__' => $value['__hash__'],
        ];
    }

    /**
     * Cast mixed value to object array type
     * @param mixed $value
     * @return array{__object__: class-string, __hash__: int}
     */
    private function castToObjectArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Value is not an array');
        }

        if (!isset($value['__object__']) || !is_string($value['__object__'])) {
            throw new InvalidArgumentException('Missing or invalid __object__ key');
        }

        if (!isset($value['__hash__']) || !is_int($value['__hash__'])) {
            throw new InvalidArgumentException('Missing or invalid __hash__ key');
        }

        // Verify that __object__ is a valid class string
        if (!class_exists($value['__object__'])) {
            throw new InvalidArgumentException('Invalid class name in __object__');
        }

        /** @var class-string $objectClass */
        $objectClass = $value['__object__'];

        return [
            '__object__' => $objectClass,
            '__hash__' => $value['__hash__'],
        ];
    }

    /**
     * Cast mixed value to collection array type
     * @param mixed $value
     * @return array{__collection__: bool, __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>}
     */
    private function castToCollectionArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('Value is not an array');
        }

        if (!isset($value['__collection__']) || !is_bool($value['__collection__'])) {
            throw new InvalidArgumentException('Missing or invalid __collection__ key');
        }

        if (!isset($value['__items__']) || !is_array($value['__items__'])) {
            throw new InvalidArgumentException('Missing or invalid __items__ key');
        }

        // Validate each item in the collection
        $validatedItems = [];
        foreach ($value['__items__'] as $index => $item) {
            if (!is_int($index)) {
                throw new InvalidArgumentException('Collection items must have integer indices');
            }

            if (!is_array($item)) {
                throw new InvalidArgumentException('Collection item must be an array');
            }

            if (!isset($item['__entity__'], $item['__hash__'])
                || !is_string($item['__entity__'])
                || !array_key_exists('__id__', $item)
                || !is_int($item['__hash__'])
            ) {
                throw new InvalidArgumentException('Invalid collection item structure');
            }

            if (!class_exists($item['__entity__'])) {
                throw new InvalidArgumentException('Invalid class name in collection item');
            }

            /** @var class-string $entityClass */
            $entityClass = $item['__entity__'];

            $validatedItems[$index] = [
                '__entity__' => $entityClass,
                '__id__' => $item['__id__'],
                '__hash__' => $item['__hash__'],
            ];
        }

        return [
            '__collection__' => $value['__collection__'],
            '__items__' => $validatedItems,
        ];
    }
}
