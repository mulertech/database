<?php

declare(strict_types=1);

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
     * @param class-string|null $targetEntity Target entity class name
     */
    public function __construct(
        public string|null $targetEntity = null,
    ) {
    }
}
