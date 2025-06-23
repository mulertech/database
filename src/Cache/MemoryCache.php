<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Class MemoryCache
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MemoryCache implements TaggableCacheInterface
{
    /** @var array<string, mixed> */
    protected array $cache = [];

    /** @var array<string, int> */
    protected array $ttl = [];

    /** @var array<string, int> */
    protected array $accessTime = [];

    /** @var array<string, int> */
    protected array $accessCount = [];

    /** @var array<string, array<string>> */
    protected array $tags = [];

    /** @var array<string, array<string, bool>> */
    protected array $taggedKeys = [];

    /** @var array<string, mixed> */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'evictions' => 0,
    ];

    /**
     * @param CacheConfig $config
     */
    public function __construct(
        protected readonly CacheConfig $config
    ) {
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            $this->stats['misses']++;
            return null;
        }

        // Check TTL
        if ($this->isExpired($key)) {
            $this->delete($key);
            $this->stats['misses']++;
            return null;
        }

        // Update access info
        $this->accessTime[$key] = time();
        $this->accessCount[$key] = ($this->accessCount[$key] ?? 0) + 1;
        $this->stats['hits']++;

        return $this->cache[$key];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        // Check if we need to evict
        if (!isset($this->cache[$key]) && count($this->cache) >= $this->config->maxSize) {
            $this->evict();
        }

        $this->cache[$key] = $value;
        $this->ttl[$key] = $ttl > 0 ? time() + $ttl : 0;
        $this->accessTime[$key] = time();
        $this->accessCount[$key] = 0;

        $this->stats['writes']++;
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        if (!isset($this->cache[$key])) {
            return;
        }

        // Remove from tags
        if (isset($this->tags[$key])) {
            foreach ($this->tags[$key] as $tag) {
                unset($this->taggedKeys[$tag][$key]);
                if (empty($this->taggedKeys[$tag])) {
                    unset($this->taggedKeys[$tag]);
                }
            }
            unset($this->tags[$key]);
        }

        unset(
            $this->cache[$key],
            $this->ttl[$key],
            $this->accessTime[$key],
            $this->accessCount[$key]
        );

        $this->stats['deletes']++;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->ttl = [];
        $this->accessTime = [];
        $this->accessCount = [];
        $this->tags = [];
        $this->taggedKeys = [];
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->cache[$key]) && !$this->isExpired($key);
    }

    /**
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $values
     * @param int $ttl
     * @return void
     */
    public function setMultiple(array $values, int $ttl = 0): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    /**
     * @param array<string> $keys
     * @return void
     */
    public function deleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    /**
     * @param string $key
     * @param array<string> $tags
     * @return void
     */
    public function tag(string $key, array $tags): void
    {
        if (!isset($this->cache[$key])) {
            return;
        }

        $this->tags[$key] = array_unique(array_merge($this->tags[$key] ?? [], $tags));

        foreach ($tags as $tag) {
            $this->taggedKeys[$tag][$key] = true;
        }
    }

    /**
     * @param string $tag
     * @return void
     */
    public function invalidateTag(string $tag): void
    {
        if (!isset($this->taggedKeys[$tag])) {
            return;
        }

        $keys = array_keys($this->taggedKeys[$tag]);
        $this->deleteMultiple($keys);

        unset($this->taggedKeys[$tag]);
    }

    /**
     * @param array<string> $tags
     * @return void
     */
    public function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->invalidateTag($tag);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $hits = (int)$this->stats['hits'];
        $misses = (int)$this->stats['misses'];
        $totalRequests = $hits + $misses;

        $hitRate = $totalRequests > 0
            ? $hits / $totalRequests
            : 0;

        return [
            ...$this->stats,
            'hit_rate' => round($hitRate * 100, 2),
            'size' => count($this->cache),
            'max_size' => $this->config->maxSize,
            'eviction_policy' => $this->config->evictionPolicy,
        ];
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isExpired(string $key): bool
    {
        if (!isset($this->ttl[$key]) || $this->ttl[$key] === 0) {
            return false;
        }

        return time() > $this->ttl[$key];
    }

    /**
     * @return void
     */
    protected function evict(): void
    {
        $keyToEvict = match ($this->config->evictionPolicy) {
            'lfu' => $this->findLfuKey(),
            'fifo' => $this->findFifoKey(),
            default => $this->findLruKey(),
        };

        if ($keyToEvict !== null) {
            $this->delete($keyToEvict);
            $this->stats['evictions']++;
        }
    }

    /**
     * @return string|null
     */
    protected function findLruKey(): ?string
    {
        $oldestTime = PHP_INT_MAX;
        $oldestKey = null;

        foreach ($this->accessTime as $key => $time) {
            if ($time < $oldestTime) {
                $oldestTime = $time;
                $oldestKey = $key;
            }
        }

        return $oldestKey;
    }

    /**
     * @return string|null
     */
    protected function findLfuKey(): ?string
    {
        $minCount = PHP_INT_MAX;
        $lfuKey = null;

        foreach ($this->accessCount as $key => $count) {
            if ($count < $minCount) {
                $minCount = $count;
                $lfuKey = $key;
            }
        }

        return $lfuKey;
    }

    /**
     * @return string|null
     */
    protected function findFifoKey(): ?string
    {
        // Simply return the first key
        $keys = array_keys($this->cache);
        return $keys[0] ?? null;
    }
}
