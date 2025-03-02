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
     * @param class-string|null $entity Target entity class name
     * @param string|null $mappedBy Database column name in the target entity
     */
    public function __construct(
        public string|null $entity = null,
        public string|null $mappedBy = null,
    )
    {}
}