<?php

namespace MulerTech\Database\Cache;

/**
 * Specialized cache for compiled SQL queries with LRU eviction
 *
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
class QueryCache
{
    /**
     * @var array<string, array{sql: string, timestamp: int, hits: int}>
     */
    private array $cache = [];

    /**
     * @var array<string, int>
     */
    private array $accessOrder = [];

    /**
     * @var int
     */
    private int $maxSize;

    /**
     * @var int
     */
    private int $ttl;

    /**
     * @var int
     */
    private int $accessCounter = 0;

    /**
     * @var array<string, int>
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'evictions' => 0,
        'invalidations' => 0
    ];

    /**
     * @param int $maxSize
     * @param int $ttl
     */
    public function __construct(int $maxSize = 10000, int $ttl = 3600)
    {
        if ($maxSize <= 0) {
            throw new \InvalidArgumentException('Max size must be positive');
        }

        if ($ttl <= 0) {
            throw new \InvalidArgumentException('TTL must be positive');
        }

        $this->maxSize = $maxSize;
        $this->ttl = $ttl;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function get(string $key): ?string
    {
        if (!isset($this->cache[$key])) {
            $this->stats['misses']++;
            return null;
        }

        $entry = $this->cache[$key];

        // Check TTL
        if (time() - $entry['timestamp'] > $this->ttl) {
            $this->delete($key);
            $this->stats['misses']++;
            return null;
        }

        // Update access order and hit count
        $this->accessOrder[$key] = ++$this->accessCounter;
        $this->cache[$key]['hits']++;
        $this->stats['hits']++;

        return $entry['sql'];
    }

    /**
     * @param string $key
     * @param string $sql
     * @return void
     */
    public function set(string $key, string $sql): void
    {
        // Remove old entry if exists
        if (isset($this->cache[$key])) {
            unset($this->accessOrder[$key]);
        }

        // Evict least recently used items if cache is full
        if (count($this->cache) >= $this->maxSize) {
            $this->evictLeastRecentlyUsed();
        }

        // Add new entry
        $this->cache[$key] = [
            'sql' => $sql,
            'timestamp' => time(),
            'hits' => 0
        ];
        $this->accessOrder[$key] = ++$this->accessCounter;
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

        // Check TTL
        $entry = $this->cache[$key];
        if (time() - $entry['timestamp'] > $this->ttl) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        unset($this->cache[$key], $this->accessOrder[$key]);
        $this->stats['invalidations']++;
    }

    /**
     * @param string $pattern
     * @return int
     */
    public function clearByPattern(string $pattern): int
    {
        $cleared = 0;
        $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';

        foreach (array_keys($this->cache) as $key) {
            if (preg_match($regex, $key)) {
                $this->delete($key);
                $cleared++;
            }
        }

        return $cleared;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $count = count($this->cache);
        $this->cache = [];
        $this->accessOrder = [];
        $this->accessCounter = 0;
        $this->stats['invalidations'] += $count;
    }

    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->cache);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;

        return [
            'size' => $this->size(),
            'max_size' => $this->maxSize,
            'hit_rate' => round($hitRate, 2),
            'memory_usage' => $this->getMemoryUsage(),
            'stats' => $this->stats
        ];
    }

    /**
     * @return void
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'evictions' => 0,
            'invalidations' => 0
        ];
    }

    /**
     * @param int $limit
     * @return array<string, array{sql: string, hits: int, timestamp: int}>
     */
    public function getHotQueries(int $limit = 10): array
    {
        $queries = $this->cache;

        // Sort by hit count descending
        uasort($queries, function ($a, $b) {
            return $b['hits'] <=> $a['hits'];
        });

        return array_slice($queries, 0, $limit, true);
    }

    /**
     * @param int $limit
     * @return array<string, array{sql: string, hits: int, timestamp: int}>
     */
    public function getColdQueries(int $limit = 10): array
    {
        $queries = $this->cache;

        // Sort by hit count ascending
        uasort($queries, function ($a, $b) {
            return $a['hits'] <=> $b['hits'];
        });

        return array_slice($queries, 0, $limit, true);
    }

    /**
     * @return void
     */
    public function cleanup(): void
    {
        $now = time();
        $removedCount = 0;

        foreach ($this->cache as $key => $entry) {
            if ($now - $entry['timestamp'] > $this->ttl) {
                unset($this->cache[$key], $this->accessOrder[$key]);
                $removedCount++;
            }
        }

        if ($removedCount > 0) {
            $this->stats['evictions'] += $removedCount;
        }
    }

    /**
     * @param int $percentage
     * @return int
     */
    public function evictColdQueries(int $percentage = 25): int
    {
        if (empty($this->cache) || $percentage <= 0 || $percentage > 100) {
            return 0;
        }

        $count = (int) ceil(count($this->cache) * ($percentage / 100));
        $coldQueries = $this->getColdQueries($count);

        foreach (array_keys($coldQueries) as $key) {
            $this->delete($key);
        }

        return count($coldQueries);
    }

    /**
     * @return void
     */
    private function evictLeastRecentlyUsed(): void
    {
        if (empty($this->accessOrder)) {
            return;
        }

        // Find the least recently used key
        $lruKey = array_search(min($this->accessOrder), $this->accessOrder, true);

        if ($lruKey !== false) {
            unset($this->cache[$lruKey], $this->accessOrder[$lruKey]);
            $this->stats['evictions']++;
        }
    }

    /**
     * @return int
     */
    private function getMemoryUsage(): int
    {
        return strlen(serialize($this->cache));
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailedStats(): array
    {
        $stats = $this->getStats();

        $stats['queries'] = [
            'total' => count($this->cache),
            'hot_queries' => count($this->getHotQueries(5)),
            'cold_queries' => count($this->getColdQueries(5)),
        ];

        $stats['performance'] = [
            'avg_hits_per_query' => count($this->cache) > 0
                ? array_sum(array_column($this->cache, 'hits')) / count($this->cache)
                : 0,
            'cache_efficiency' => $this->calculateCacheEfficiency(),
        ];

        return $stats;
    }

    /**
     * @return float
     */
    private function calculateCacheEfficiency(): float
    {
        if (empty($this->cache)) {
            return 0.0;
        }

        $totalHits = array_sum(array_column($this->cache, 'hits'));
        $totalQueries = count($this->cache);

        // Efficiency = (total hits) / (total queries * theoretical max hits per query)
        // This gives us a measure of how well the cache is being utilized
        return ($totalHits / ($totalQueries * 10)) * 100;
    }

    /**
     * @param callable|null $filter
     * @return array<string, array{sql: string, hits: int, timestamp: int}>
     */
    public function filter(?callable $filter = null): array
    {
        if ($filter === null) {
            return $this->cache;
        }

        return array_filter($this->cache, $filter, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return bool
     */
    public function isFull(): bool
    {
        return count($this->cache) >= $this->maxSize;
    }

    /**
     * @return float
     */
    public function getUsagePercentage(): float
    {
        return (count($this->cache) / $this->maxSize) * 100;
    }

    /**
     * @param int $newSize
     * @return void
     */
    public function resize(int $newSize): void
    {
        if ($newSize <= 0) {
            throw new \InvalidArgumentException('Cache size must be positive');
        }

        $this->maxSize = $newSize;

        // If current cache is larger than new size, evict oldest entries
        while (count($this->cache) > $this->maxSize) {
            $this->evictLeastRecentlyUsed();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'cache' => $this->cache,
            'access_order' => $this->accessOrder,
            'access_counter' => $this->accessCounter,
            'stats' => $this->stats,
            'config' => [
                'max_size' => $this->maxSize,
                'ttl' => $this->ttl
            ]
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    public function import(array $data): void
    {
        if (!is_array($data['cache'] ?? null)) {
            throw new \InvalidArgumentException('Invalid cache data format');
        }

        $this->cache = $data['cache'];
        $this->accessOrder = $data['access_order'] ?? [];
        $this->accessCounter = $data['access_counter'] ?? 0;

        if (isset($data['stats']) && is_array($data['stats'])) {
            $this->stats = array_merge($this->stats, $data['stats']);
        }

        // Validate imported data
        $this->validateCacheIntegrity();
    }

    /**
     * @return void
     */
    private function validateCacheIntegrity(): void
    {
        // Ensure cache and access order are in sync
        foreach (array_keys($this->cache) as $key) {
            if (!isset($this->accessOrder[$key])) {
                $this->accessOrder[$key] = ++$this->accessCounter;
            }
        }

        // Remove orphaned access order entries
        foreach (array_keys($this->accessOrder) as $key) {
            if (!isset($this->cache[$key])) {
                unset($this->accessOrder[$key]);
            }
        }

        // Enforce size limit
        while (count($this->cache) > $this->maxSize) {
            $this->evictLeastRecentlyUsed();
        }
    }

    /**
     * @param int $maxEntries
     * @return array<string, array{sql: string, hits: int, last_access: int}>
     */
    public function getAccessLog(int $maxEntries = 100): array
    {
        $log = [];
        $sortedByAccess = $this->accessOrder;
        arsort($sortedByAccess); // Sort by access time descending

        $count = 0;
        foreach (array_keys($sortedByAccess) as $key) {
            if ($count >= $maxEntries) {
                break;
            }

            if (isset($this->cache[$key])) {
                $log[$key] = [
                    'sql' => strlen($this->cache[$key]['sql']) > 100
                        ? substr($this->cache[$key]['sql'], 0, 100) . '...'
                        : $this->cache[$key]['sql'],
                    'hits' => $this->cache[$key]['hits'],
                    'last_access' => $this->accessOrder[$key]
                ];
                $count++;
            }
        }

        return $log;
    }

    /**
     * @return bool
     */
    public function isHealthy(): bool
    {
        // Check if cache is in a healthy state
        return count($this->cache) === count($this->accessOrder) &&
            $this->accessCounter >= 0 &&
            count($this->cache) <= $this->maxSize;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnose(): array
    {
        return [
            'healthy' => $this->isHealthy(),
            'cache_size' => count($this->cache),
            'access_order_size' => count($this->accessOrder),
            'max_size' => $this->maxSize,
            'access_counter' => $this->accessCounter,
            'memory_usage_bytes' => $this->getMemoryUsage(),
            'oldest_entry_age' => $this->getOldestEntryAge(),
            'avg_hits_per_entry' => $this->getAverageHitsPerEntry()
        ];
    }

    /**
     * @return int
     */
    private function getOldestEntryAge(): int
    {
        if (empty($this->cache)) {
            return 0;
        }

        $now = time();
        $oldest = min(array_column($this->cache, 'timestamp'));

        return $now - $oldest;
    }

    /**
     * @return float
     */
    private function getAverageHitsPerEntry(): float
    {
        if (empty($this->cache)) {
            return 0.0;
        }

        $totalHits = array_sum(array_column($this->cache, 'hits'));

        return $totalHits / count($this->cache);
    }

    /**
     * @param string $key
     * @return array{sql: string, timestamp: int, hits: int}|null
     */
    public function getEntry(string $key): ?array
    {
        return $this->cache[$key] ?? null;
    }

    /**
     * @return array<string>
     */
    public function getKeys(): array
    {
        return array_keys($this->cache);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->cache);
    }

    /**
     * @return int
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * @param int $ttl
     * @return void
     */
    public function setTtl(int $ttl): void
    {
        if ($ttl <= 0) {
            throw new \InvalidArgumentException('TTL must be positive');
        }

        $this->ttl = $ttl;
    }
}
