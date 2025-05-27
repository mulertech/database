<?php

namespace MulerTech\Database\Mapping;

/**
 * Enum representing all available MySQL column types
 */
enum ColumnType: string
{
    // Integer numeric types
    case INT = 'int';
    case TINYINT = 'tinyint';
    case SMALLINT = 'smallint';
    case MEDIUMINT = 'mediumint';
    case BIGINT = 'bigint';

    // Decimal numeric types
    case DECIMAL = 'decimal';
    case NUMERIC = 'numeric';
    case FLOAT = 'float';
    case DOUBLE = 'double';
    case REAL = 'real';

    // Fixed-length character types
    case CHAR = 'char';
    case VARCHAR = 'varchar';

    // Text types
    case TEXT = 'text';
    case TINYTEXT = 'tinytext';
    case MEDIUMTEXT = 'mediumtext';
    case LONGTEXT = 'longtext';

    // Binary types
    case BINARY = 'binary';
    case VARBINARY = 'varbinary';
    case BLOB = 'blob';
    case TINYBLOB = 'tinyblob';
    case MEDIUMBLOB = 'mediumblob';
    case LONGBLOB = 'longblob';

    // Date and time types
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIMESTAMP = 'timestamp';
    case TIME = 'time';
    case YEAR = 'year';

    // Boolean types
    case BOOLEAN = 'boolean';
    case BOOL = 'bool';

    // Enumeration and set types
    case ENUM = 'enum';
    case SET = 'set';

    // JSON type (MySQL 5.7.8+)
    case JSON = 'json';

    // Special types
    case GEOMETRY = 'geometry';
    case POINT = 'point';
    case LINESTRING = 'linestring';
    case POLYGON = 'polygon';

    /**
     * Determines if the column type can be unsigned
     *
     * @return bool
     */
    public function canBeUnsigned(): bool
    {
        return match($this) {
            self::INT, self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::BIGINT,
            self::DECIMAL, self::NUMERIC, self::FLOAT, self::DOUBLE, self::REAL => true,
            default => false
        };
    }

    /**
     * Determines if the column type requires a length
     *
     * @return bool
     */
    public function requiresLength(): bool
    {
        return match($this) {
            self::CHAR, self::VARCHAR, self::BINARY, self::VARBINARY => true,
            self::INT, self::TINYINT, self::SMALLINT, self::MEDIUMINT, self::BIGINT => true,
            self::DECIMAL, self::NUMERIC, self::FLOAT, self::DOUBLE, self::REAL => true,
            self::ENUM, self::SET => true,
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
            self::DECIMAL, self::NUMERIC, self::FLOAT, self::DOUBLE, self::REAL => true,
            default => false
        };
    }

    /**
     * Generates SQL representation of the column type with its length if necessary
     *
     * @param int|null $length Column length
     * @param int|null $scale Scale for decimal types
     * @param bool $unsigned Whether the type is unsigned
     * @return string
     */
    public function toSqlDefinition(?int $length = null, ?int $scale = null, bool $unsigned = false): string
    {
        $sql = $this->value;

        if ($this->requiresPrecision() && $length !== null) {
            $scale = $scale ?? 0;
            $sql .= "($length,$scale)";
        } elseif ($this->requiresLength() && $length !== null) {
            $sql .= "($length)";
        }

        if ($unsigned && $this->canBeUnsigned()) {
            $sql .= ' unsigned';
        }

        return $sql;
    }
}
