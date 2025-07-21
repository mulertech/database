<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Types;

/**
 * Configuration class for entity loading
 */
readonly class EntityLoadingConfig
{
    public function __construct(
        public bool $recursive = true
    ) {
    }

    public static function recursive(): self
    {
        return new self(recursive: true);
    }

    public static function nonRecursive(): self
    {
        return new self(recursive: false);
    }
}
