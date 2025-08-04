<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use MulerTech\Database\Mapping\Types\ColumnType;
use TypeError;

/**
 * Processes values based on database column types
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ColumnTypeValueProcessor implements ValueProcessorInterface
{
    /**
     * @param ColumnType|null $columnType
     */
    public function __construct(private ?ColumnType $columnType = null)
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
     */
    public function process(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->columnType === null) {
            return $value;
        }

        return match ($this->columnType) {
            ColumnType::INT, ColumnType::SMALLINT, ColumnType::MEDIUMINT, ColumnType::BIGINT,
            ColumnType::YEAR => $this->convertToInt($value),
            ColumnType::TINYINT => $this->convertToBool($value),
            ColumnType::DECIMAL, ColumnType::FLOAT, ColumnType::DOUBLE => $this->convertToFloat($value),
            ColumnType::VARCHAR, ColumnType::CHAR, ColumnType::TEXT,
            ColumnType::TINYTEXT, ColumnType::MEDIUMTEXT, ColumnType::LONGTEXT,
            ColumnType::ENUM, ColumnType::SET, ColumnType::TIME => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            ColumnType::DATE, ColumnType::DATETIME, ColumnType::TIMESTAMP => $this->convertToDateString($value),
            ColumnType::JSON => is_array($value) ? $value : (function () use ($value) {
                $jsonString = is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value));
                if ($jsonString !== false) {
                    $decoded = json_decode($jsonString, true);
                    return $decoded !== null ? $decoded : [];
                }
                return [];
            })(),
            ColumnType::BINARY, ColumnType::VARBINARY, ColumnType::BLOB,
            ColumnType::TINYBLOB, ColumnType::MEDIUMBLOB, ColumnType::LONGBLOB => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            default => $value,
        };
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToColumnValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'string', 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext' => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            'int', 'integer', 'smallint', 'mediumint', 'bigint', 'year' => $this->convertToInt($value),
            'float', 'double', 'decimal' => $this->convertToFloat($value),
            'bool', 'boolean', 'tinyint' => $this->convertToBool($value),
            'date', 'datetime', 'timestamp', 'time' => $this->convertToDateString($value),
            'json' => $this->convertToJsonString($value),
            'binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob' => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            default => throw new InvalidArgumentException("Unsupported column type: $type"),
        };
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return mixed
     * @throws JsonException
     */
    public function convertToPhpValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower($type)) {
            'string', 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext' => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            'int', 'integer', 'smallint', 'mediumint', 'bigint', 'year' => $this->convertToInt($value),
            'float', 'double', 'decimal' => $this->convertToFloat($value),
            'bool', 'boolean', 'tinyint' => $this->convertToBool($value),
            'date', 'datetime', 'timestamp', 'time' => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            'json' => $this->convertToJsonString($value),
            'binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob' => is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value)),
            default => throw new InvalidArgumentException("Unsupported column type: $type"),
        };
    }

    /**
     * @param string $type
     * @return mixed
     */
    public function getDefaultValue(string $type): mixed
    {
        return match (strtolower($type)) {
            'string', 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext',
            'binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob' => '',
            'int', 'integer', 'smallint', 'mediumint', 'bigint', 'year' => 0,
            'float', 'double', 'decimal' => 0.0,
            'bool', 'boolean', 'tinyint' => false,
            'date' => '1970-01-01',
            'datetime', 'timestamp' => '1970-01-01 00:00:00',
            'time' => '00:00:00',
            'json' => '{}',
            default => null,
        };
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isValidType(string $type): bool
    {
        return in_array($type, $this->getSupportedTypes(), true);
    }

    /**
     * @return array<string>
     */
    public function getSupportedTypes(): array
    {
        return [
            'string', 'varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set',
            'int', 'integer', 'smallint', 'mediumint', 'bigint', 'year',
            'tinyint', 'bool', 'boolean',
            'float', 'double', 'decimal',
            'date', 'datetime', 'timestamp', 'time',
            'json',
            'binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob',
        ];
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function convertToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_string($value)) {
            if (strtolower($value) === 'true') {
                return 1;
            }
            if (strtolower($value) === 'false') {
                return 0;
            }
            return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        }
        return 0;
    }

    /**
     * @param mixed $value
     * @return float
     */
    private function convertToFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        return 0.0;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private function convertToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }

    /**
     * @param mixed $value
     * @return string
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function convertToJsonString(mixed $value): string
    {
        if (is_resource($value)) {
            throw new InvalidArgumentException('Invalid JSON data');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }
        if (is_string($value)) {
            return $value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid JSON data');
        }
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function convertToDateString(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value)) {
            return $value;
        }
        return new DateTime()->format('Y-m-d H:i:s');
    }

    /**
     * @param string $type
     * @return string
     */
    public function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'smallint', 'mediumint', 'bigint' => 'int',
            'double', 'decimal' => 'float',
            'boolean', 'tinyint' => 'bool',
            'varchar', 'char' => 'string',
            'tinytext', 'mediumtext', 'longtext' => 'text',
            'varbinary' => 'binary',
            'tinyblob', 'mediumblob', 'longblob' => 'blob',
            default => $type,
        };
    }
}
