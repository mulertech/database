<?php

declare(strict_types=1);

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
    /**
     * @param class-string|null $repository
     * @param string|null $tableName
     * @param int|null $autoIncrement
     */
    public function __construct(
        public string|null $repository = null,
        public string|null $tableName = null,
        public int|null $autoIncrement = null
    ) {
    }
}
