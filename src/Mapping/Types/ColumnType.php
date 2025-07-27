<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Types;

/**
 * Enum representing all available MySQL column types
 */
enum ColumnType: string
{
    // Integer numeric types
    case INT = 'INT';
    case TINYINT = 'TINYINT';
    case SMALLINT = 'SMALLINT';
    case MEDIUMINT = 'MEDIUMINT';
    case BIGINT = 'BIGINT';

    // Decimal numeric types
    case DECIMAL = 'DECIMAL'; // or NUMERIC
    case FLOAT = 'FLOAT';
    case DOUBLE = 'DOUBLE'; // (synonym for REAL in MySQL)

    // Fixed-length character types
    case CHAR = 'CHAR';
    case VARCHAR = 'VARCHAR';

    // Text types
    case TEXT = 'TEXT';
    case TINYTEXT = 'TINYTEXT';
    case MEDIUMTEXT = 'MEDIUMTEXT';
    case LONGTEXT = 'LONGTEXT';

    // Binary types
    case BINARY = 'BINARY';
    case VARBINARY = 'VARBINARY';
    case BLOB = 'BLOB';
    case TINYBLOB = 'TINYBLOB';
    case MEDIUMBLOB = 'MEDIUMBLOB';
    case LONGBLOB = 'LONGBLOB';

    // Date and time types
    case DATE = 'DATE';
    case DATETIME = 'DATETIME';
    case TIMESTAMP = 'TIMESTAMP';
    case TIME = 'TIME';
    case YEAR = 'YEAR';

    // Boolean types are often represented as TINYINT(1) in MySQL

    // Enumeration and set types
    case ENUM = 'ENUM';
    case SET = 'SET';

    // JSON type (MySQL 5.7.8+)
    case JSON = 'JSON';

    // Special types
    case GEOMETRY = 'GEOMETRY';
    case POINT = 'POINT';
    case LINESTRING = 'LINESTRING';
    case POLYGON = 'POLYGON';

    /**
     * Determines if the column type can be unsigned
     *
     * @return bool
     */
    public function canBeUnsigned(): bool
    {
        return match($this) {
            self::INT, self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::BIGINT,
            self::DECIMAL, self::FLOAT, self::DOUBLE => true,
            default => false
        };
    }

    /**
     * Determines if the column type requires a length
     *
     * @return bool
     */
    public function isTypeWithLength(): bool
    {
        return match($this) {
            self::CHAR, self::VARCHAR, self::BINARY, self::VARBINARY => true,
            default => false
        };
    }

    /**
     * Determines if the column type requires precision (for decimal types)
     *
     * @return bool
     */
    public function requiresPrecision(): bool
    {
        return match($this) {
            self::DECIMAL, self::FLOAT, self::DOUBLE => true,
            default => false
        };
    }

    /**
     * Determines if the column type requires choices (for ENUM and SET types)
     *
     * @return bool
     */
    public function requiresChoices(): bool
    {
        return match($this) {
            self::ENUM, self::SET => true,
            default => false
        };
    }


    /**
     * Generates SQL representation of the column type with its length if necessary
     *
     * @param int|null $length Column length
     * @param int|null $scale Scale for decimal types
     * @param bool $isUnsigned Whether the type is unsigned
     * @param array<string> $choices Choices for ENUM or SET types
     * @return string
     */
    public function toSqlDefinition(
        ?int $length = null,
        ?int $scale = null,
        bool $isUnsigned = false,
        array $choices = []
    ): string {
        $sql = $this->value;

        if (null !== $length && $this->requiresPrecision()) {
            $scale ??= 0;
            $sql .= "($length,$scale)";
        } elseif (null !== $length && $this->isTypeWithLength()) {
            $sql .= "($length)";
        } elseif (!empty($choices) && $this->requiresChoices()) {
            $sql .= "('" . implode("','", $choices) . "')";
        }

        if ($isUnsigned && $this->canBeUnsigned()) {
            $sql .= ' unsigned';
        }

        return $sql;
    }
}
