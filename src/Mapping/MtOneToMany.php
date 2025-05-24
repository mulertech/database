<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtOneToMany
 * @package MulerTech\Database\Mapping
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MtOneToMany
{
    /**
     * @param class-string|null $entity Entity class name (this will be automatically set by the ORM)
     * @param class-string|null $targetEntity Target entity class name
     * @param string|null $inverseJoinProperty Property of the target entity
     */
    public function __construct(
        public string|null $entity = null,
        public string|null $targetEntity = null,
        public string|null $inverseJoinProperty = null,
    ) {
    }
}
