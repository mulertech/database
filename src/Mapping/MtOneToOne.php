<?php

namespace MulerTech\Database\Mapping;

use Attribute;

/**
 * Class MtOneToOne
 * @package MulerTech\Database\Mapping
 * @author Sébastien Muler
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MtOneToOne
{
    /**
     * @param class-string|null $targetEntity Target entity class name
     */
    public function __construct(public string|null $targetEntity = null)
    {
    }
}
