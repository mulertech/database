<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

use InvalidArgumentException;

/**
 * Configuration du cache
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
readonly class CacheConfig
{
    /**
     * @param int $maxSize
     * @param int $ttl
     * @param bool $enableStats
     * @param string $evictionPolicy
     */
    public function __construct(
        public int $maxSize = 10000,
        public int $ttl = 3600,
        public bool $enableStats = true,
        public string $evictionPolicy = 'lru'
    ) {
        if (!in_array($evictionPolicy, ['lru', 'lfu', 'fifo'], true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid eviction policy: %s. Must be one of: lru, lfu, fifo', $evictionPolicy)
            );
        }
    }
}
