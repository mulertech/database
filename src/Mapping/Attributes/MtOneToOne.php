<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

/**
 * Class MtOneToOne.
 *
 * @author Sébastien Muler
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MtOneToOne
{
    /**
     * @param class-string|null $targetEntity Target entity class name
     */
    public function __construct(public ?string $targetEntity = null)
    {
    }
}
