<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Validator;

use InvalidArgumentException;

/**
 * Validator for array structures used in change detection
 */
class ArrayValidator
{
    /**
     * Validate entity array structure
     * @param mixed $value
     * @return array{__entity__: class-string, __id__: mixed, __hash__: int}
     */
    public function validateEntityArray(mixed $value): array
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
     * Validate object array structure
     * @param mixed $value
     * @return array{__object__: class-string, __hash__: int}
     */
    public function validateObjectArray(mixed $value): array
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
     * Validate collection array structure
     * @param mixed $value
     * @return array{__collection__: bool, __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>}
     */
    public function validateCollectionArray(mixed $value): array
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

        return [
            '__collection__' => $value['__collection__'],
            '__items__' => $this->validateCollectionItems($value['__items__']),
        ];
    }

    /**
     * Validate collection items
     * @param array<mixed> $items
     * @return array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>
     */
    private function validateCollectionItems(array $items): array
    {
        $validatedItems = [];

        foreach ($items as $index => $item) {
            $this->validateCollectionItemIndex($index);
            $validatedItem = $this->validateCollectionItemStructure($item);

            $validatedItems[$index] = $validatedItem;
        }

        return $validatedItems;
    }

    private function validateCollectionItemIndex(mixed $index): void
    {
        if (!is_int($index)) {
            throw new InvalidArgumentException('Collection items must have integer indices');
        }
    }

    /**
     * @param mixed $item
     * @return array{__entity__: class-string, __id__: mixed, __hash__: int}
     */
    private function validateCollectionItemStructure(mixed $item): array
    {
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

        return [
            '__entity__' => $entityClass,
            '__id__' => $item['__id__'],
            '__hash__' => $item['__hash__'],
        ];
    }
}
