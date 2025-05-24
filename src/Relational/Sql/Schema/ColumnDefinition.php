<?php

namespace MulerTech\Database\Relational\Sql\Schema;

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
     * @var DataType
     */
    private DataType $type;

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
     * @var mixed
     */
    private mixed $default = null;

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
     * @return DataType
     */
    public function getType(): DataType
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
     * @return mixed
     */
    public function getDefault(): mixed
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
     * @return self
     */
    public function integer(): self
    {
        $this->type = DataType::INT;
        return $this;
    }

    /**
     * @return self
     */
    public function bigInteger(): self
    {
        $this->type = DataType::BIGINT;
        return $this;
    }

    /**
     * @param int $length
     * @return self
     */
    public function string(int $length = 255): self
    {
        $this->type = DataType::VARCHAR;
        $this->length = $length;
        return $this;
    }

    /**
     * @return self
     */
    public function text(): self
    {
        $this->type = DataType::TEXT;
        return $this;
    }

    /**
     * @param int $precision
     * @param int $scale
     * @return self
     */
    public function decimal(int $precision = 8, int $scale = 2): self
    {
        $this->type = DataType::DECIMAL;
        $this->precision = $precision;
        $this->scale = $scale;
        return $this;
    }

    /**
     * @return self
     */
    public function datetime(): self
    {
        $this->type = DataType::DATETIME;
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
     * @param mixed $value
     * @return self
     */
    public function default(mixed $value): self
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
        return $this;
    }
}
