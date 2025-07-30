<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use JsonException;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Manages value processing strategies
 */
readonly class ValueProcessorManager
{
    public function __construct(
        private EntityHydratorInterface $hydrator
    ) {
    }

    /**
     * Process a value according to its type
     *
     * @param array<mixed>|bool|float|int|object|string|null $value
     * @param ReflectionProperty|null $property
     * @param ColumnType|null $columnType
     * @return array<mixed>|bool|float|int|object|string|null
     * @throws JsonException
     */
    public function processValue(
        array|bool|float|int|object|string|null $value,
        ?ReflectionProperty $property = null,
        ?ColumnType $columnType = null
    ): array|bool|float|int|object|string|null {
        if ($value === null) {
            return null;
        }

        // Try column type first
        if ($columnType !== null) {
            $processor = new ColumnTypeValueProcessor($columnType);
            return $processor->process($value);
        }

        // Try attribute-based approach
        if ($property !== null) {
            $processor = $this->createProcessorFromProperty($property);
            if ($processor !== null) {
                return $processor->process($value);
            }
        }

        return $value;
    }

    private function createProcessorFromProperty(ReflectionProperty $property): ?ValueProcessorInterface
    {
        // Try MtColumn attribute
        $mtColumnAttrs = $property->getAttributes(MtColumn::class);
        if (!empty($mtColumnAttrs)) {
            $mtColumn = $mtColumnAttrs[0]->newInstance();
            if ($mtColumn->columnType !== null) {
                return new ColumnTypeValueProcessor($mtColumn->columnType);
            }
        }

        // Try PHP type
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            if (class_exists($typeName)) {
                return new PhpTypeValueProcessor(
                    $typeName,
                    $this->hydrator->hydrate(...)
                );
            }
        }

        return null;
    }
}
