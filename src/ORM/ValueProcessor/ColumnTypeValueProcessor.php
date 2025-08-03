<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use DateTime;
use Exception;
use JsonException;
use MulerTech\Database\Mapping\Types\ColumnType;
use TypeError;

/**
 * Processes values based on database column types
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class ColumnTypeValueProcessor implements ValueProcessorInterface
{
    public function __construct(private ColumnType $columnType)
    {
    }

    /**
     * @param mixed $typeInfo
     * @return bool
     */
    public function canProcess(mixed $typeInfo): bool
    {
        return $typeInfo instanceof ColumnType;
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    public function process(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->columnType) {
            ColumnType::INT, ColumnType::SMALLINT, ColumnType::MEDIUMINT, ColumnType::BIGINT,
            ColumnType::YEAR => $this->processInt($value),
            ColumnType::TINYINT => $this->processBool($value),
            ColumnType::DECIMAL, ColumnType::FLOAT, ColumnType::DOUBLE => $this->processFloat($value),
            ColumnType::VARCHAR, ColumnType::CHAR, ColumnType::TEXT,
            ColumnType::TINYTEXT, ColumnType::MEDIUMTEXT, ColumnType::LONGTEXT,
            ColumnType::ENUM, ColumnType::SET, ColumnType::TIME => $this->processString($value),
            ColumnType::DATE, ColumnType::DATETIME, ColumnType::TIMESTAMP => $this->processDateTime($value),
            ColumnType::JSON => $this->processJson($value),
            default => $value,
        };
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function processInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function processBool(mixed $value): bool
    {
        return (bool) $value;
    }

    /**
     * @param mixed $value
     * @return float
     */
    private function processFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        return 0.0;
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function processString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return match (true) {
            is_null($value) => '',
            is_scalar($value) => (string) $value,
            default => throw new TypeError('Value cannot be converted to string')
        };
    }

    /**
     * @param mixed $value
     * @return DateTime
     */
    private function processDateTime(mixed $value): DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        try {
            $dateString = match (true) {
                is_string($value) => $value,
                is_null($value) => 'now',
                is_scalar($value) => (string) $value,
                default => throw new TypeError('Value cannot be converted to date string')
            };

            return new DateTime($dateString);
        } catch (Exception) {
            return new DateTime(); // Default to current time
        }
    }

    /**
     * @return array<int|string, mixed>
     * @throws JsonException
     */
    private function processJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return []; // Default empty array for invalid JSON
    }
}
