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
    public function __construct(
        public string|null $columnName = null,
        public string|null $columnType = null,
        public bool $isNullable = true,
        public string|null $extra = null,
        public string|null $columnKey = null
    )
    {}
}