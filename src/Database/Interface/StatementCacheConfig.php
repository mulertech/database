<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;

/**
 * Configuration class for statement caching
 */
class StatementCacheConfig
{
    public function __construct(
        private readonly bool $enabled = true,
        private readonly ?CacheConfig $cacheConfig = null
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getCacheConfig(): ?CacheConfig
    {
        return $this->cacheConfig;
    }

    public static function disabled(): self
    {
        return new self(false);
    }

    public static function enabled(?CacheConfig $cacheConfig = null): self
    {
        return new self(true, $cacheConfig);
    }
}
