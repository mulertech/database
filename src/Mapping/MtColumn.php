<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtColumn
 * @package MulerTech\Database\Mapping
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
     * @param bool $unsigned
     * @param bool $isNullable
     * @param string|null $extra
     * @param string|null $columnDefault
     * @param ColumnKey|null $columnKey
     * @param array<string> $choices
     */
    public function __construct(
        public string|null     $columnName = null,
        public ColumnType|null $columnType = null,
        public int|null        $length = null,
        public int|null        $scale = null,
        public bool            $unsigned = false,
        public bool            $isNullable = true,
        public string|null     $extra = null,
        public string|null     $columnDefault = null,
        public ColumnKey|null  $columnKey = null,
        public array           $choices = []
    ) {
    }
}
