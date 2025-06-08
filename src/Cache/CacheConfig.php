<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Configuration du cache
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
class CacheConfig
{
    /**
     * @param int $maxSize
     * @param int $ttl
     * @param bool $enableStats
     * @param string $evictionPolicy
     */
    public function __construct(
        public readonly int $maxSize = 10000,
        public readonly int $ttl = 3600,
        public readonly bool $enableStats = true,
        public readonly string $evictionPolicy = 'lru'
    ) {
        if (!in_array($evictionPolicy, ['lru', 'lfu', 'fifo'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid eviction policy: %s. Must be one of: lru, lfu, fifo', $evictionPolicy)
            );
        }
    }
}
