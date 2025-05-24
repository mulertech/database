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
     * @param int|null $length
     * @param bool $unsigned Indique si le type numérique est non signé
     * @param bool $isNullable
     * @param string|null $extra
     * @param string|null $columnDefault
     * @param ColumnKey|null $columnKey
     */
    public function __construct(
        public string|null     $columnName = null,
        public ColumnType|null $columnType = null,
        public int|null        $length = null,
        public bool            $unsigned = false,
        public bool            $isNullable = true,
        public string|null     $extra = null,
        public string|null     $columnDefault = null,
        public ColumnKey|null  $columnKey = null
    ) {
    }
}
