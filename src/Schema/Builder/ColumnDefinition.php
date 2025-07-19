<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use MulerTech\Database\Mapping\Types\ColumnType;
use InvalidArgumentException;

/**
 * Class ColumnDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ColumnDefinition
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var ColumnType
     */
    private ColumnType $type = ColumnType::VARCHAR;

    /**
     * @var int|null
     */
    private ?int $length = null;

    /**
     * @var int|null
     */
    private ?int $precision = null;

    /**
     * @var int|null
     */
    private ?int $scale = null;

    /**
     * @var bool
     */
    private bool $nullable = true;

    /**
     * @var string|null
     */
    private string|null $default = null;

    /**
     * @var bool
     */
    private bool $autoIncrement = false;

    /**
     * @var bool
     */
    private bool $unsigned = false;

    /**
     * @var string|null
     */
    private ?string $comment = null;

    /**
     * @var string|null
     */
    private ?string $after = null;

    /**
     * @var bool
     */
    private bool $first = false;

    /**
     * @var array<string>
     */
    private array $choiceValues = [];

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return ColumnType
     */
    public function getType(): ColumnType
    {
        return $this->type;
    }

    /**
     * @return int|null
     */
    public function getLength(): ?int
    {
        return $this->length;
    }

    /**
     * @return int|null
     */
    public function getPrecision(): ?int
    {
        return $this->precision;
    }

    /**
     * @return int|null
     */
    public function getScale(): ?int
    {
        return $this->scale;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return string|int|float|null
     */
    public function getDefault(): string|int|float|null
    {
        return $this->default;
    }

    /**
     * @return bool
     */
    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    /**
     * @return bool
     */
    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return string|null
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * @return bool
     */
    public function isFirst(): bool
    {
        return $this->first;
    }

    /**
     * @return array<string>
     */
    public function getChoiceValues(): array
    {
        return $this->choiceValues;
    }

    /**
     * @return self
     */
    public function integer(): self
    {
        $this->type = ColumnType::INT;
        return $this;
    }

    /**
     * @return self
     */
    public function bigInteger(): self
    {
        $this->type = ColumnType::BIGINT;
        return $this;
    }

    /**
     * @param int $length
     * @return self
     */
    public function string(int $length = 255): self
    {
        $this->type = ColumnType::VARCHAR;
        $this->length = $length;
        return $this;
    }

    /**
     * @return self
     */
    public function text(): self
    {
        $this->type = ColumnType::TEXT;
        return $this;
    }

    /**
     * @param int $precision
     * @param int $scale
     * @return self
     */
    public function decimal(int $precision = 8, int $scale = 2): self
    {
        $this->type = ColumnType::DECIMAL;
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    /**
     * @param int $precision
     * @param int $scale
     * @return self
     */
    public function float(int $precision = 8, int $scale = 2): self
    {
        $this->type = ColumnType::FLOAT;
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    /**
     * @return self
     */
    public function datetime(): self
    {
        $this->type = ColumnType::DATETIME;
        return $this;
    }

    /**
     * @return self
     */
    public function notNull(): self
    {
        $this->nullable = false;
        return $this;
    }

    /**
     * @param string|null $value
     * @return self
     */
    public function default(string|null $value): self
    {
        $this->default = $value;
        return $this;
    }

    /**
     * @return self
     */
    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    /**
     * @return self
     */
    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    /**
     * @param string $comment
     * @return self
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @param string $columnName
     * @return self
     */
    public function after(string $columnName): self
    {
        $this->after = $columnName;
        $this->first = false; // Reset first if after is set
        return $this;
    }

    /**
     * @return self
     */
    public function first(): self
    {
        $this->first = true;
        $this->after = null; // Reset after if first is set
        return $this;
    }

    /**
     * @return self
     */
    public function tinyInt(): self
    {
        $this->type = ColumnType::TINYINT;
        return $this;
    }

    /**
     * @return self
     */
    public function smallInt(): self
    {
        $this->type = ColumnType::SMALLINT;
        return $this;
    }

    /**
     * @return self
     */
    public function mediumInt(): self
    {
        $this->type = ColumnType::MEDIUMINT;
        return $this;
    }

    /**
     * @param int $length
     * @return self
     */
    public function char(int $length = 1): self
    {
        $this->type = ColumnType::CHAR;
        $this->length = $length;
        return $this;
    }

    /**
     * @return self
     */
    public function double(): self
    {
        $this->type = ColumnType::DOUBLE;
        return $this;
    }

    /**
     * @return self
     */
    public function tinyText(): self
    {
        $this->type = ColumnType::TINYTEXT;
        return $this;
    }

    /**
     * @return self
     */
    public function mediumText(): self
    {
        $this->type = ColumnType::MEDIUMTEXT;
        return $this;
    }

    /**
     * @return self
     */
    public function longText(): self
    {
        $this->type = ColumnType::LONGTEXT;
        return $this;
    }

    /**
     * @param int $length
     * @return self
     */
    public function binary(int $length): self
    {
        $this->type = ColumnType::BINARY;
        $this->length = $length;
        return $this;
    }

    /**
     * @param int $length
     * @return self
     */
    public function varbinary(int $length): self
    {
        $this->type = ColumnType::VARBINARY;
        $this->length = $length;
        return $this;
    }

    /**
     * @return self
     */
    public function blob(): self
    {
        $this->type = ColumnType::BLOB;
        return $this;
    }

    /**
     * @return self
     */
    public function tinyBlob(): self
    {
        $this->type = ColumnType::TINYBLOB;
        return $this;
    }

    /**
     * @return self
     */
    public function mediumBlob(): self
    {
        $this->type = ColumnType::MEDIUMBLOB;
        return $this;
    }

    /**
     * @return self
     */
    public function longBlob(): self
    {
        $this->type = ColumnType::LONGBLOB;
        return $this;
    }

    /**
     * @return self
     */
    public function date(): self
    {
        $this->type = ColumnType::DATE;
        return $this;
    }

    /**
     * @return self
     */
    public function timestamp(): self
    {
        $this->type = ColumnType::TIMESTAMP;
        return $this;
    }

    /**
     * @return self
     */
    public function time(): self
    {
        $this->type = ColumnType::TIME;
        return $this;
    }

    /**
     * @return self
     */
    public function year(): self
    {
        $this->type = ColumnType::YEAR;
        return $this;
    }

    /**
     * @param array<string> $values
     * @return self
     */
    public function enum(array $values): self
    {
        $this->type = ColumnType::ENUM;
        $this->choiceValues = $values;
        return $this;
    }

    /**
     * @param array<string> $values
     * @return self
     */
    public function set(array $values): self
    {
        $this->type = ColumnType::SET;
        $this->choiceValues = $values;
        return $this;
    }

    /**
     * @return self
     */
    public function json(): self
    {
        $this->type = ColumnType::JSON;
        return $this;
    }

    /**
     * @return self
     */
    public function geometry(): self
    {
        $this->type = ColumnType::GEOMETRY;
        return $this;
    }

    /**
     * @return self
     */
    public function point(): self
    {
        $this->type = ColumnType::POINT;
        return $this;
    }

    /**
     * @return self
     */
    public function linestring(): self
    {
        $this->type = ColumnType::LINESTRING;
        return $this;
    }

    /**
     * @return self
     */
    public function polygon(): self
    {
        $this->type = ColumnType::POLYGON;
        return $this;
    }
}
