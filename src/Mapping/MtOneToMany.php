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
     * @param class-string|null $entity
     * @param string|null $mappedBy
     */
    public function __construct(
        public string|null $entity = null,
        public string|null $mappedBy = null,
    )
    {}
}