<?php

namespace mtphp\Database\Mapping;

/**
 * Class MtColumn
 * @package mtphp\Database\Mapping
 * @author Sébastien Muler
 * @Annotation
 */
class MtColumn
{

    /**
     * @var string $columnName
     */
    public $columnName;
    /**
     * @var string $columnType
     */
    public $columnType;
    /**
     * @var bool $isNullable
     */
    public $isNullable = true;
    /**
     * @var string $extra
     */
    public $extra;
    /**
     * @var string $columnKey
     */
    public $columnKey;

    /**
     * @return string
     */
    public function getColumnName(): ?string
    {
        return $this->columnName;
    }

    /**
     * @param string $columnName
     */
    public function setColumnName(string $columnName): void
    {
        $this->columnName = $columnName;
    }

    /**
     * @return string
     */
    public function getColumnType(): ?string
    {
        return $this->columnType;
    }

    /**
     * @param string $columnType
     */
    public function setColumnType(string $columnType): void
    {
        $this->columnType = $columnType;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    /**
     * @param bool $isNullable
     */
    public function setIsNullable(bool $isNullable): void
    {
        $this->isNullable = $isNullable;
    }

    /**
     * @return string
     */
    public function getExtra(): ?string
    {
        return $this->extra;
    }

    /**
     * @param string $extra
     */
    public function setExtra(string $extra): void
    {
        $this->extra = $extra;
    }

    /**
     * @return string
     */
    public function getColumnKey(): ?string
    {
        return $this->columnKey;
    }

    /**
     * @param string $columnKey
     */
    public function setColumnKey(string $columnKey): void
    {
        $this->columnKey = $columnKey;
    }
}