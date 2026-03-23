<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

/**
 * Class MtManyToMany.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MtManyToMany
{
    /**
     * @param class-string|null $entity       Entity class name (this will be automatically set by the ORM)
     * @param class-string|null $targetEntity Target entity class name
     * @param class-string|null $mappedBy     Pivot Entity
     */
    public function __construct(
        public ?string $entity = null,
        public ?string $targetEntity = null,
        public ?string $mappedBy = null,
        public ?string $joinProperty = null,
        public ?string $inverseJoinProperty = null,
    ) {
    }
}
