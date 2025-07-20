<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

use Attribute;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Class MtColumn
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MtColumn
{
    /**
     * MtColumn constructor.
     * @param string|null $columnName
     * @param ColumnType|null $columnType
     * @param int|null $length Or precision for decimal types
     * @param int|null $scale Scale for decimal types
     * @param bool $isUnsigned
     * @param bool $isNullable
     * @param string|null $extra
     * @param string|null $columnDefault
     * @param ColumnKey|null $columnKey
     * @param array<string> $choices
     */
    public function __construct(
        public string|null $columnName = null,
        public ColumnType|null $columnType = null,
        public int|null $length = null,
        public int|null $scale = null,
        public bool $isUnsigned = false,
        public bool $isNullable = true,
        public string|null $extra = null,
        public string|null $columnDefault = null,
        public ColumnKey|null $columnKey = null,
        public array $choices = []
    ) {
    }

    /**
     * Create an unsigned column
     */
    public static function unsigned(
        ?string $columnName = null,
        ?ColumnType $columnType = null,
        ?int $length = null
    ): self {
        return new self(
            columnName: $columnName,
            columnType: $columnType,
            length: $length,
            isUnsigned: true
        );
    }

    /**
     * Create a non-nullable column
     */
    public static function notNull(
        ?string $columnName = null,
        ?ColumnType $columnType = null,
        ?int $length = null
    ): self {
        return new self(
            columnName: $columnName,
            columnType: $columnType,
            length: $length,
            isNullable: false
        );
    }

    /**
     * Create a column with default value
     */
    public static function withDefault(
        string $default,
        ?string $columnName = null,
        ?ColumnType $columnType = null
    ): self {
        return new self(
            columnName: $columnName,
            columnType: $columnType,
            columnDefault: $default
        );
    }
}
