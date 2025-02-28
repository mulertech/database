<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtManyToMany
 * @package MulerTech\Database\Mapping
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MtManyToMany
{
    /**
     * @param class-string|null $entity
     * @param string|null $mappedBy
     * @param string|null $joinTable
     * @param string|null $joinColumn
     * @param string|null $inverseJoinColumn
     */
    public function __construct(
        public string|null $entity = null,
        public string|null $mappedBy = null,
        public string|null $joinTable = null,
        public string|null $joinColumn = null,
        public string|null $inverseJoinColumn = null,
    )
    {}
}