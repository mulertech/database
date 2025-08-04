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
class ValueProcessorManager
{
    /**
     * @var ColumnTypeValueProcessor|null
     */
    private ?ColumnTypeValueProcessor $columnTypeProcessor = null;

    /**
     * @var PhpTypeValueProcessor|null
     */
    private ?PhpTypeValueProcessor $phpTypeProcessor = null;

    /**
     * @param EntityHydratorInterface|null $hydrator
     */
    public function __construct(
        private ?EntityHydratorInterface $hydrator = null
    ) {
    }

    /**
     * @return ColumnTypeValueProcessor
     */
    public function getColumnTypeProcessor(): ColumnTypeValueProcessor
    {
        if ($this->columnTypeProcessor === null) {
            $this->columnTypeProcessor = new ColumnTypeValueProcessor();
        }
        return $this->columnTypeProcessor;
    }

    /**
     * @return PhpTypeValueProcessor
     */
    public function getPhpTypeProcessor(): PhpTypeValueProcessor
    {
        if ($this->phpTypeProcessor === null) {
            $this->phpTypeProcessor = new PhpTypeValueProcessor();
        }
        return $this->phpTypeProcessor;
    }

    /**
     * @param mixed $value
     * @param ReflectionProperty|null $property
     * @param ColumnType|null $columnType
     * @return mixed
     * @throws JsonException
     * @throws ReflectionException
     */
    public function processValue(
        mixed $value,
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

        // Process based on value type
        return $this->processBasicType($value);
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed
    {
        return $this->getColumnTypeProcessor()->convertToColumnValue($value, $type);
    }

    /**
     * @param mixed $value
     * @param string $phpType
     * @return mixed
     * @throws JsonException
     */
    public function convertToPhpValue(mixed $value, string $phpType): mixed
    {
        return $this->getPhpTypeProcessor()->convertToPhpValue($value, $phpType);
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isValidType(string $type): bool
    {
        $columnProcessor = new ColumnTypeValueProcessor();
        $phpProcessor = new PhpTypeValueProcessor();

        return $columnProcessor->isValidType($type) || $phpProcessor->isValidType($type);
    }

    /**
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        $columnProcessor = new ColumnTypeValueProcessor();
        $phpProcessor = new PhpTypeValueProcessor();

        return array_merge(
            $columnProcessor->getSupportedTypes(),
            $phpProcessor->getSupportedTypes()
        );
    }

    /**
     * @param string $type
     * @return string
     */
    public function normalizeType(string $type): string
    {
        $phpProcessor = new PhpTypeValueProcessor();
        return $phpProcessor->normalizeType($type);
    }

    /**
     * @param mixed $value
     * @param string $expectedType
     * @return bool
     */
    public function validateValue(mixed $value, string $expectedType): bool
    {
        if ($value === null) {
            return true;
        }

        $normalizedType = $this->normalizeType($expectedType);

        return match ($normalizedType) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            default => true,
        };
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getDefaultValue(string $type): mixed
    {
        $phpProcessor = new PhpTypeValueProcessor();
        return $phpProcessor->getDefaultValue($type);
    }

    /**
     * @param mixed $from
     * @param string $to
     * @return bool
     */
    public function canConvert(mixed $from, string $to): bool
    {
        if ($from === null) {
            return true;
        }

        $fromType = gettype($from);
        $normalizedTo = $this->normalizeType($to);

        // Same type
        if ($fromType === $normalizedTo) {
            return true;
        }

        // Common conversions
        return match ($normalizedTo) {
            'string' => true, // Almost anything can be converted to string
            'int' => is_numeric($from) || is_bool($from),
            'float' => is_numeric($from),
            'bool' => is_scalar($from),
            'array' => is_string($from) || is_object($from),
            'object' => is_array($from) || is_string($from),
            default => false,
        };
    }

    /**
     * @param mixed $value
     * @param string $type
     * @param ColumnType|null $columnType
     * @return mixed
     * @throws JsonException
     */
    public function processComplexValue(mixed $value, string $type, ?ColumnType $columnType = null): mixed
    {
        if ($columnType !== null) {
            return $this->convertToColumnValue($value, $type);
        }

        return $this->convertToPhpValue($value, $type);
    }

    /**
     * @return bool
     */
    public function processorCaching(): bool
    {
        return $this->columnTypeProcessor !== null && $this->phpTypeProcessor !== null;
    }

    /**
     * @param ReflectionProperty $property
     * @return ValueProcessorInterface|null
     * @throws ReflectionException
     */
    private function createProcessorFromProperty(ReflectionProperty $property): ?ValueProcessorInterface
    {
        if ($this->hydrator === null) {
            return null;
        }

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

    /**
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    private function processBasicType(mixed $value): mixed
    {
        if (is_object($value)) {
            // Convert objects to arrays with entity information
            $result = (array) $value;
            $result['__entity__'] = get_class($value);
            return $result;
        }

        if (is_array($value)) {
            // Process arrays recursively to handle nested objects
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->processBasicType($item);
            }
            return $result;
        }

        return $value;
    }
}
