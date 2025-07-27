<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

use Attribute;

/**
 * Class MtEntity
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_CLASS)]
class MtEntity
{
    /**
     * @param class-string|null $repository
     * @param string|null $tableName
     * @param int|null $autoIncrement
     * @param string|null $engine
     * @param string|null $charset
     * @param string|null $collation
     */
    public function __construct(
        public string|null $repository = null,
        public string|null $tableName = null,
        public int|null $autoIncrement = null,
        public string|null $engine = null,
        public string|null $charset = null,
        public string|null $collation = null
    ) {
    }
}
