<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * @author Sébastien Muler
 */
class ValueProcessorManager
{
    private ?ColumnTypeValueProcessor $columnTypeProcessor = null;

    private ?PhpTypeValueProcessor $phpTypeProcessor = null;

    public function getColumnTypeProcessor(): ColumnTypeValueProcessor
    {
        if (null === $this->columnTypeProcessor) {
            $this->columnTypeProcessor = new ColumnTypeValueProcessor();
        }

        return $this->columnTypeProcessor;
    }

    public function getPhpTypeProcessor(): PhpTypeValueProcessor
    {
        if (null === $this->phpTypeProcessor) {
            $this->phpTypeProcessor = new PhpTypeValueProcessor();
        }

        return $this->phpTypeProcessor;
    }

    /**
     * @throws \JsonException
     */
    public function processValue(
        mixed $value,
        ?ColumnType $columnType,
        EntityMetadata $metadata,
        string $propertyName,
    ): mixed {
        if (null === $value) {
            return null;
        }

        // Use ColumnType directly if provided
        if (null !== $columnType) {
            return new ColumnTypeValueProcessor($columnType)->process($value);
        }

        // Fallback: try to get ColumnType from metadata
        $metadataColumnType = $metadata->getColumnType($propertyName);
        if (null !== $metadataColumnType) {
            return new ColumnTypeValueProcessor($metadataColumnType)->process($value);
        }

        // Final fallback: basic type processing
        return $this->processBasicType($value);
    }

    /**
     * @throws \JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed
    {
        return $this->getColumnTypeProcessor()->convertToColumnValue($value, $type);
    }

    /**
     * @throws \JsonException|\DateMalformedStringException
     */
    public function convertToPhpValue(mixed $value, string $phpType): mixed
    {
        return $this->getPhpTypeProcessor()->convertToPhpValue($value, $phpType);
    }

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

    public function normalizeType(string $type): string
    {
        return new PhpTypeValueProcessor()->normalizeType($type);
    }

    public function validateValue(mixed $value, string $expectedType): bool
    {
        if (null === $value) {
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

    public function getDefaultValue(string $type): mixed
    {
        return new PhpTypeValueProcessor()->getDefaultValue($type);
    }

    public function canConvert(mixed $from, string $to): bool
    {
        if (null === $from) {
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
     * @throws \JsonException
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
