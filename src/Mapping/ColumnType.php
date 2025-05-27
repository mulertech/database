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
     * @param int|null $precision Precision for decimal types
     * @param bool $unsigned Whether the type is unsigned
     * @return string
     */
    public function toSqlDefinition(?int $length = null, ?int $precision = null, bool $unsigned = false): string
    {
        $sql = $this->value;

        if ($this->requiresPrecision() && $length !== null) {
            $precision = $precision ?? 0;
            $sql .= "($length,$precision)";
        } elseif ($this->requiresLength() && $length !== null) {
            $sql .= "($length)";
        }

        if ($unsigned && $this->canBeUnsigned()) {
            $sql .= ' unsigned';
        }

        return $sql;
    }

    /**
     * Creates a ColumnType instance from a MySQL string
     *
     * @param string $sqlType SQL type (e.g: "varchar(255)", "int(11)", etc.)
     * @return self|null
     */
    public static function fromSqlDefinition(string $sqlType): ?self
    {
        // Extract base type (before parentheses and 'unsigned')
        $baseTypeRaw = preg_replace('/\s+unsigned|\(.*\)/', '', $sqlType);
        $baseType = $baseTypeRaw !== null ? strtolower($baseTypeRaw) : '';

        foreach (self::cases() as $case) {
            if ($case->value === $baseType) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Checks if the SQL type definition contains 'unsigned'
     *
     * @param string $sqlType SQL type (e.g: "int unsigned", "int(11) unsigned")
     * @return bool
     */
    public static function isUnsigned(string $sqlType): bool
    {
        return str_contains(strtolower($sqlType), 'unsigned');
    }

    /**
     * Extracts length from an SQL type definition
     *
     * @param string $sqlType SQL type (e.g: "varchar(255)", "int(11)", etc.)
     * @return int|null
     */
    public static function extractLengthFromSqlDefinition(string $sqlType): ?int
    {
        if (preg_match('/\((\d+)(?:,\d+)?\)/', $sqlType, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Extracts precision from an SQL type definition
     *
     * @param string $sqlType SQL type (e.g: "decimal(10,2)")
     * @return int|null
     */
    public static function extractPrecisionFromSqlDefinition(string $sqlType): ?int
    {
        if (preg_match('/\(\d+,(\d+)\)/', $sqlType, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }
}
