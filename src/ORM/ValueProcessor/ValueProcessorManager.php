<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use JsonException;
use MulerTech\Database\Mapping\Types\ColumnType;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class ValueProcessorManager
{
    public function __construct(
        private EntityHydratorInterface $hydrator
    ) {
    }

    /**
     * @param array<mixed>|bool|float|int|object|string|null $value
     * @param ReflectionProperty|null $property
     * @param ColumnType|null $columnType
     * @return mixed
     * @throws JsonException
     * @throws ReflectionException
     */
    public function processValue(
        array|bool|float|int|object|string|null $value,
        ?ReflectionProperty $property = null,
        ?ColumnType $columnType = null
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($columnType !== null) {
            $processor = new ColumnTypeValueProcessor($columnType);
            return $processor->process($value);
        }

        if ($property !== null) {
            $processor = $this->createProcessorFromProperty($property);
            if ($processor !== null) {
                return $processor->process($value);
            }
        }

        return $value;
    }

    /**
     * @param ReflectionProperty $property
     * @return ValueProcessorInterface|null
     * @throws ReflectionException
     */
    private function createProcessorFromProperty(ReflectionProperty $property): ?ValueProcessorInterface
    {
        // Try EntityMetadata approach
        $entityClass = $property->getDeclaringClass()->getName();
        if (!class_exists($entityClass)) {
            return null;
        }
        /** @var class-string $entityClass */
        $metadata = $this->hydrator->getMetadataCache()->getEntityMetadata($entityClass);
        $columnType = $metadata->getColumnType($property->getName());
        if ($columnType !== null) {
            return new ColumnTypeValueProcessor($columnType);
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
