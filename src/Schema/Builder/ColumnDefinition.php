<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use LogicException;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Column Definition - Fluent interface for column operations
 */
class ColumnDefinition
{
    private MtColumn $mtColumn;

    public function __construct(
        private readonly string $name,
    ) {
        $this->mtColumn = new MtColumn(
            columnName: $this->name,
            columnType: ColumnType::VARCHAR,
            length: null,
            isUnsigned: false,
            isNullable: true,
            extra: null,
            columnDefault: null,
            columnKey: null,
            choices: []
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    // Integer types
    public function integer(): self
    {
        $this->mtColumn->columnType = ColumnType::INT;
        return $this;
    }

    public function tinyInt(): self
    {
        $this->mtColumn->columnType = ColumnType::TINYINT;
        return $this;
    }

    public function smallInt(): self
    {
        $this->mtColumn->columnType = ColumnType::SMALLINT;
        return $this;
    }

    public function mediumInt(): self
    {
        $this->mtColumn->columnType = ColumnType::MEDIUMINT;
        return $this;
    }

    public function bigInteger(): self
    {
        $this->mtColumn->columnType = ColumnType::BIGINT;
        return $this;
    }

    // String types
    public function string(?int $length = 255): self
    {
        $this->mtColumn->columnType = ColumnType::VARCHAR;
        $this->mtColumn->length = $length;
        return $this;
    }

    public function char(int $length): self
    {
        $this->mtColumn->columnType = ColumnType::CHAR;
        $this->mtColumn->length = $length;
        return $this;
    }

    public function text(): self
    {
        $this->mtColumn->columnType = ColumnType::TEXT;
        return $this;
    }

    public function tinyText(): self
    {
        $this->mtColumn->columnType = ColumnType::TINYTEXT;
        return $this;
    }

    public function mediumText(): self
    {
        $this->mtColumn->columnType = ColumnType::MEDIUMTEXT;
        return $this;
    }

    public function longText(): self
    {
        $this->mtColumn->columnType = ColumnType::LONGTEXT;
        return $this;
    }

    // Decimal types
    public function decimal(int $precision, int $scale): self
    {
        $this->mtColumn->columnType = ColumnType::DECIMAL;
        $this->mtColumn->length = $precision;
        $this->mtColumn->scale = $scale;
        return $this;
    }

    public function float(int $precision, int $scale): self
    {
        $this->mtColumn->columnType = ColumnType::FLOAT;
        $this->mtColumn->length = $precision;
        $this->mtColumn->scale = $scale;
        return $this;
    }

    public function double(): self
    {
        $this->mtColumn->columnType = ColumnType::DOUBLE;
        return $this;
    }

    // Date/Time types
    public function date(): self
    {
        $this->mtColumn->columnType = ColumnType::DATE;
        return $this;
    }

    public function datetime(): self
    {
        $this->mtColumn->columnType = ColumnType::DATETIME;
        return $this;
    }

    public function timestamp(): self
    {
        $this->mtColumn->columnType = ColumnType::TIMESTAMP;
        return $this;
    }

    public function time(): self
    {
        $this->mtColumn->columnType = ColumnType::TIME;
        return $this;
    }

    public function year(): self
    {
        $this->mtColumn->columnType = ColumnType::YEAR;
        return $this;
    }

    // Binary types
    public function binary(int $length): self
    {
        $this->mtColumn->columnType = ColumnType::BINARY;
        $this->mtColumn->length = $length;
        return $this;
    }

    public function varbinary(int $length): self
    {
        $this->mtColumn->columnType = ColumnType::VARBINARY;
        $this->mtColumn->length = $length;
        return $this;
    }

    // BLOB types
    public function blob(): self
    {
        $this->mtColumn->columnType = ColumnType::BLOB;
        return $this;
    }

    public function tinyBlob(): self
    {
        $this->mtColumn->columnType = ColumnType::TINYBLOB;
        return $this;
    }

    public function mediumBlob(): self
    {
        $this->mtColumn->columnType = ColumnType::MEDIUMBLOB;
        return $this;
    }

    public function longBlob(): self
    {
        $this->mtColumn->columnType = ColumnType::LONGBLOB;
        return $this;
    }

    // Special types
    public function json(): self
    {
        $this->mtColumn->columnType = ColumnType::JSON;
        return $this;
    }

    /**
     * @param array<string> $values
     */
    public function enum(array $values): self
    {
        $this->mtColumn->columnType = ColumnType::ENUM;
        $this->mtColumn->choices = $values;
        return $this;
    }

    /**
     * @param array<string> $values
     */
    public function set(array $values): self
    {
        $this->mtColumn->columnType = ColumnType::SET;
        $this->mtColumn->choices = $values;
        return $this;
    }

    // Geometry types
    public function geometry(): self
    {
        $this->mtColumn->columnType = ColumnType::GEOMETRY;
        return $this;
    }

    public function point(): self
    {
        $this->mtColumn->columnType = ColumnType::POINT;
        return $this;
    }

    public function lineString(): self
    {
        $this->mtColumn->columnType = ColumnType::LINESTRING;
        return $this;
    }

    public function polygon(): self
    {
        $this->mtColumn->columnType = ColumnType::POLYGON;
        return $this;
    }

    // Modifiers
    public function unsigned(): self
    {
        $this->mtColumn->isUnsigned = true;
        return $this;
    }

    public function notNull(): self
    {
        $this->mtColumn->isNullable = false;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->mtColumn->extra = 'auto_increment';
        return $this;
    }

    public function default(?string $value): self
    {
        $this->mtColumn->columnDefault = $value;
        return $this;
    }

    /**
     * Generate SQL for this column
     */
    public function toSql(): string
    {
        if ($this->mtColumn->columnType === null) {
            throw new LogicException("Column type must be set before generating SQL");
        }
        $sql = "`$this->name` {$this->mtColumn->columnType->value}";

        // Add length/precision
        if ($this->mtColumn->length !== null && $this->mtColumn->scale !== null) {
            $sql .= "({$this->mtColumn->length},{$this->mtColumn->scale})";
        } elseif ($this->mtColumn->length !== null) {
            $sql .= "({$this->mtColumn->length})";
        }

        // Add enum/set values
        if (!empty($this->mtColumn->choices)) {
            $values = array_map(static fn ($v) => "'" . addslashes($v) . "'", $this->mtColumn->choices);
            $sql .= "(" . implode(',', $values) . ")";
        }

        // Add unsigned
        if ($this->mtColumn->isUnsigned) {
            $sql .= " UNSIGNED";
        }

        // Add nullable
        if (!$this->mtColumn->isNullable) {
            $sql .= " NOT NULL";
        }

        // Add default
        if ($this->mtColumn->columnDefault !== null) {
            $sql .= " DEFAULT '" . addslashes($this->mtColumn->columnDefault) . "'";
        }

        // Add auto increment
        if ($this->mtColumn->extra === 'auto_increment') {
            $sql .= " AUTO_INCREMENT";
        }

        return $sql;
    }
}
