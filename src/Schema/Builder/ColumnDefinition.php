<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

/**
 * Column Definition - Fluent interface for column operations
 */
class ColumnDefinition
{
    private string $type = 'VARCHAR(255)';
    private bool $nullable = true;
    private bool $unsigned = false;
    private bool $autoIncrement = false;
    private ?string $default = null;
    private ?int $length = null;
    private ?int $precision = null;
    private ?int $scale = null;
    /** @var array<string> */
    private array $enumValues = [];

    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    // Integer types
    public function integer(): self
    {
        $this->type = 'INT';
        return $this;
    }

    public function tinyInt(): self
    {
        $this->type = 'TINYINT';
        return $this;
    }

    public function smallInt(): self
    {
        $this->type = 'SMALLINT';
        return $this;
    }

    public function mediumInt(): self
    {
        $this->type = 'MEDIUMINT';
        return $this;
    }

    public function bigInteger(): self
    {
        $this->type = 'BIGINT';
        return $this;
    }

    // String types
    public function string(?int $length = 255): self
    {
        $this->type = 'VARCHAR';
        $this->length = $length;
        return $this;
    }

    public function char(int $length): self
    {
        $this->type = 'CHAR';
        $this->length = $length;
        return $this;
    }

    public function text(): self
    {
        $this->type = 'TEXT';
        return $this;
    }

    public function tinyText(): self
    {
        $this->type = 'TINYTEXT';
        return $this;
    }

    public function mediumText(): self
    {
        $this->type = 'MEDIUMTEXT';
        return $this;
    }

    public function longText(): self
    {
        $this->type = 'LONGTEXT';
        return $this;
    }

    // Decimal types
    public function decimal(int $precision, int $scale): self
    {
        $this->type = 'DECIMAL';
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    public function float(int $precision, int $scale): self
    {
        $this->type = 'FLOAT';
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    public function double(): self
    {
        $this->type = 'DOUBLE';
        return $this;
    }

    // Date/Time types
    public function date(): self
    {
        $this->type = 'DATE';
        return $this;
    }

    public function datetime(): self
    {
        $this->type = 'DATETIME';
        return $this;
    }

    public function timestamp(): self
    {
        $this->type = 'TIMESTAMP';
        return $this;
    }

    public function time(): self
    {
        $this->type = 'TIME';
        return $this;
    }

    public function year(): self
    {
        $this->type = 'YEAR';
        return $this;
    }

    // Binary types
    public function binary(int $length): self
    {
        $this->type = 'BINARY';
        $this->length = $length;
        return $this;
    }

    public function varbinary(int $length): self
    {
        $this->type = 'VARBINARY';
        $this->length = $length;
        return $this;
    }

    // BLOB types
    public function blob(): self
    {
        $this->type = 'BLOB';
        return $this;
    }

    public function tinyBlob(): self
    {
        $this->type = 'TINYBLOB';
        return $this;
    }

    public function mediumBlob(): self
    {
        $this->type = 'MEDIUMBLOB';
        return $this;
    }

    public function longBlob(): self
    {
        $this->type = 'LONGBLOB';
        return $this;
    }

    // Special types
    public function json(): self
    {
        $this->type = 'JSON';
        return $this;
    }

    /**
     * @param array<string> $values
     */
    public function enum(array $values): self
    {
        $this->type = 'ENUM';
        $this->enumValues = $values;
        return $this;
    }

    /**
     * @param array<string> $values
     */
    public function set(array $values): self
    {
        $this->type = 'SET';
        $this->enumValues = $values;
        return $this;
    }

    // Geometry types
    public function geometry(): self
    {
        $this->type = 'GEOMETRY';
        return $this;
    }

    public function point(): self
    {
        $this->type = 'POINT';
        return $this;
    }

    public function lineString(): self
    {
        $this->type = 'LINESTRING';
        return $this;
    }

    public function polygon(): self
    {
        $this->type = 'POLYGON';
        return $this;
    }

    // Modifiers
    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function notNull(): self
    {
        $this->nullable = false;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function default(?string $value): self
    {
        $this->default = $value;
        return $this;
    }

    /**
     * Generate SQL for this column
     */
    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";

        // Add length/precision
        if ($this->length !== null) {
            $sql .= "({$this->length})";
        } elseif ($this->precision !== null && $this->scale !== null) {
            $sql .= "({$this->precision},{$this->scale})";
        }

        // Add enum/set values
        if (!empty($this->enumValues)) {
            $values = array_map(fn ($v) => "'" . addslashes($v) . "'", $this->enumValues);
            $sql .= "(" . implode(',', $values) . ")";
        }

        // Add unsigned
        if ($this->unsigned) {
            $sql .= " UNSIGNED";
        }

        // Add nullable
        if (!$this->nullable) {
            $sql .= " NOT NULL";
        }

        // Add default
        if ($this->default !== null) {
            $sql .= " DEFAULT '" . addslashes($this->default) . "'";
        }

        // Add auto increment
        if ($this->autoIncrement) {
            $sql .= " AUTO_INCREMENT";
        }

        return $sql;
    }
}
