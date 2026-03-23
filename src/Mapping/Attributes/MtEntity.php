<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Attributes;

/**
 * Class MtEntity.
 *
 * @author Sébastien Muler
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MtEntity
{
    /**
     * @param class-string|null $repository
     */
    public function __construct(
        public ?string $repository = null,
        public ?string $tableName = null,
        public ?int $autoIncrement = null,
        public ?string $engine = null,
        public ?string $charset = null,
        public ?string $collation = null,
    ) {
    }
}
