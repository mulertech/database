<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

/**
 * Class MtOneToMany.
 *
 * @author Sébastien Muler
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MtOneToMany
{
    /**
     * @param class-string|null $entity              Entity class name (this will be automatically set by the ORM)
     * @param class-string|null $targetEntity        Target entity class name
     * @param string|null       $inverseJoinProperty Property of the target entity
     */
    public function __construct(
        public ?string $entity = null,
        public ?string $targetEntity = null,
        public ?string $inverseJoinProperty = null,
    ) {
    }
}
