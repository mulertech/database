<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtEntity
 * @package MulerTech\Database\Mapping
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MtEntity
{
    public function __construct(
        public string|null $repository = null,
        public string|null $tableName = null,
        public int|null $autoIncrement = null
    )
    {}
}