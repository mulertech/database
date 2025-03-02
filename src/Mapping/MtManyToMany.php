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
     * @param class-string|null $entity Target entity class name
     * @param string|null $mappedBy Pivot Entity
     * @param string|null $joinTable Pivot table name
     * @param string|null $joinColumn Pivot table column name
     * @param string|null $inverseJoinColumn Pivot table column name of the target entity
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