<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtManyToOne
 * @package MulerTech\Database\Mapping
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MtManyToOne
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