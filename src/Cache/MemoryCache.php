<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Cache mémoire avec éviction LRU/LFU/FIFO
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
class MemoryCache implements TaggableCacheInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * @var array<string, int>
     */
    private array $expirations = [];

    /**
     * @var array<string, array<string>>
     */
    private array $tags = [];

    /**
     * @var array<string, array<string, true>>
     */
    private array $taggedKeys = [];

    /**
     * @var array<string, int>
     */
    private array $accessCount = [];

    /**
     * @var array<string, int>
     */
    private array $lastAccess = [];

    /**
     * @var array<string, int>
     */
    private array $insertOrder = [];

    /**
     * @var int
     */
    private int $insertCounter = 0;

    /**
     * @var CacheConfig
     */
    private readonly CacheConfig $config;

    /**
     * @var array{hits: int, misses: int, evictions: int}
     */
    private array $stats = ['hits' => 0, 'misses' => 0, 'evictions' => 0];

    /**
     * @param CacheConfig $config
     */
    public function __construct(CacheConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            if ($this->config->enableStats) {
                $this->stats['misses']++;
            }
            return null;
        }

        if ($this->config->enableStats) {
            $this->stats['hits']++;
        }

        $this->updateAccessMetrics($key);

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
        $this->ensureCapacity();

        $this->cache[$key] = $value;

        if ($ttl > 0) {
            $this->expirations[$key] = time() + $ttl;
        } elseif ($this->config->ttl > 0) {
            $this->expirations[$key] = time() + $this->config->ttl;
        }

        $this->insertOrder[$key] = ++$this->insertCounter;
        $this->updateAccessMetrics($key);
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        unset(
            $this->cache[$key],
            $this->expirations[$key],
            $this->accessCount[$key],
            $this->lastAccess[$key],
            $this->insertOrder[$key]
        );

        if (isset($this->tags[$key])) {
            foreach ($this->tags[$key] as $tag) {
                unset($this->taggedKeys[$tag][$key]);
                if (empty($this->taggedKeys[$tag])) {
                    unset($this->taggedKeys[$tag]);
                }
            }
            unset($this->tags[$key]);
        }
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->expirations = [];
        $this->tags = [];
        $this->taggedKeys = [];
        $this->accessCount = [];
        $this->lastAccess = [];
        $this->insertOrder = [];
        $this->insertCounter = 0;
        $this->stats = ['hits' => 0, 'misses' => 0, 'evictions' => 0];
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        if (isset($this->expirations[$key]) && time() > $this->expirations[$key]) {
            $this->delete($key);
            return false;
        }

        return true;
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

        foreach (array_keys($this->taggedKeys[$tag]) as $key) {
            $this->delete($key);
        }
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
     * @return array{hits: int, misses: int, evictions: int, hitRate: float, size: int}
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? $this->stats['hits'] / $total : 0.0;

        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'evictions' => $this->stats['evictions'],
            'hitRate' => $hitRate,
            'size' => count($this->cache),
        ];
    }

    /**
     * @param string $key
     * @return void
     */
    private function updateAccessMetrics(string $key): void
    {
        $this->lastAccess[$key] = time();
        $this->accessCount[$key] = ($this->accessCount[$key] ?? 0) + 1;
    }

    /**
     * @return void
     */
    private function ensureCapacity(): void
    {
        if (count($this->cache) < $this->config->maxSize) {
            return;
        }

        $keyToEvict = match ($this->config->evictionPolicy) {
            'lru' => $this->findLruKey(),
            'lfu' => $this->findLfuKey(),
            'fifo' => $this->findFifoKey(),
            default => throw new \RuntimeException('Invalid eviction policy'),
        };

        if ($keyToEvict !== null) {
            $this->delete($keyToEvict);
            if ($this->config->enableStats) {
                $this->stats['evictions']++;
            }
        }
    }

    /**
     * @return string|null
     */
    private function findLruKey(): ?string
    {
        if (empty($this->lastAccess)) {
            return null;
        }

        $sorted = $this->lastAccess;
        asort($sorted);

        return array_key_first($sorted);
    }

    /**
     * @return string|null
     */
    private function findLfuKey(): ?string
    {
        if (empty($this->accessCount)) {
            return null;
        }

        $minAccess = min($this->accessCount);
        foreach ($this->accessCount as $key => $count) {
            if ($count === $minAccess) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function findFifoKey(): ?string
    {
        if (empty($this->insertOrder)) {
            return null;
        }

        return array_key_first(
            array_slice($this->insertOrder, 0, 1, true)
        );
    }
}
