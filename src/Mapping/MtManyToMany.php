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
     * @param class-string|null $entity Entity class name (this will be automatically set by the ORM)
     * @param class-string|null $targetEntity Target entity class name
     * @param class-string|null $mappedBy Pivot Entity
     * @param string|null $joinTable Pivot table name
     * @param string|null $joinProperty
     * @param string|null $inverseJoinProperty
     */
    public function __construct(
        public string|null $entity = null,
        public string|null $targetEntity = null,
        public string|null $mappedBy = null,
        public string|null $joinTable = null,
        public string|null $joinProperty = null,
        public string|null $inverseJoinProperty = null,
    )
    {}
}