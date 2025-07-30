<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use InvalidArgumentException;

/**
 * Cache configuration
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class CacheConfig
{
    /**
     * @param int $maxSize
     * @param int $ttl
     * @param string $evictionPolicy
     */
    public function __construct(
        public int $maxSize = 10000,
        public int $ttl = 3600,
        public string $evictionPolicy = 'lru' // lru, lfu, fifo
    ) {
        $this->validateEvictionPolicy();
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateEvictionPolicy(): void
    {
        $validPolicies = ['lru', 'lfu', 'fifo'];

        if (!in_array($this->evictionPolicy, $validPolicies, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid eviction policy "%s". Valid policies are: %s',
                    $this->evictionPolicy,
                    implode(', ', $validPolicies)
                )
            );
        }
    }
}
