<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Class MtColumn.
 *
 * @author Sébastien Muler
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MtColumn
{
    /**
     * MtColumn constructor.
     *
     * @param int|null      $length  Or precision for decimal types
     * @param int|null      $scale   Scale for decimal types
     * @param array<string> $choices
     */
    public function __construct(
        public ?string $columnName = null,
        public ?ColumnType $columnType = null,
        public ?int $length = null,
        public ?int $scale = null,
        public bool $isUnsigned = false,
        public bool $isNullable = true,
        public ?string $extra = null,
        public ?string $columnDefault = null,
        public ?ColumnKey $columnKey = null,
        public array $choices = [],
    ) {
    }
}
