<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Processor;

use DateTimeInterface;
use InvalidArgumentException;
use MulerTech\Collections\Collection;

/**
 * Processes different types of values for change detection
 */
class ValueProcessor
{
    /**
     * Process a value and return its serialized representation
     */
    public function processValue(mixed $value): mixed
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
    public function processDateTime(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s');
    }

    /**
     * Process entity objects with getId method
     * @return array{__entity__: class-string, __id__: mixed, __hash__: int}
     */
    public function processEntity(object $value): array
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
     * @return array{
     *     __collection__: bool,
     *     __items__: array<int, array{__entity__: class-string, __id__: mixed, __hash__: int}>
     *     }
     */
    public function processCollection(Collection $value): array
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
    public function processObject(object $value): array
    {
        return [
            '__object__' => $value::class,
            '__hash__' => spl_object_id($value),
        ];
    }

    /**
     * Get the type of value for comparison purposes
     */
    public function getValueType(mixed $value): string
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
}
