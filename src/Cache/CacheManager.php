<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Global cache manager for the database system
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
class CacheManager
{
    /**
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * @var CacheInvalidator
     */
    private readonly CacheInvalidator $invalidator;

    /**
     * @var array<string, CacheInterface>
     */
    private array $caches = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cacheConfigs = [];

    /**
     * @var bool
     */
    private bool $initialized = false;

    /**
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (mainly for testing)
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->invalidator = CacheFactory::getInvalidator();
        $this->initializeCaches();
    }

    /**
     * Initialize all system caches
     * @return void
     */
    private function initializeCaches(): void
    {
        if ($this->initialized) {
            return;
        }

        // Register all system caches
        $this->caches['statements'] = CacheFactory::createMemoryCache('prepared_statements', new CacheConfig(
            maxSize: 100,
            ttl: 0,
            enableStats: true,
            evictionPolicy: 'lfu' // Statements benefit from LFU
        ));

        $this->caches['metadata'] = CacheFactory::createMetadataCache();

        $this->caches['queries'] = CacheFactory::createResultSetCache('compiled_queries');

        $this->caches['results'] = CacheFactory::createResultSetCache('query_results', null, 2048);

        // Store configurations for reference
        $this->cacheConfigs = [
            'statements' => ['type' => 'memory', 'eviction' => 'lfu', 'max_size' => 100],
            'metadata' => ['type' => 'metadata', 'persistent' => true],
            'queries' => ['type' => 'resultset', 'compression' => true],
            'results' => ['type' => 'resultset', 'compression' => true, 'threshold' => 2048],
        ];

        // Register dependencies
        $this->registerDependencies();

        // Register invalidation patterns
        $this->registerPatterns();

        $this->initialized = true;
    }

    /**
     * Register cache dependencies
     * @return void
     */
    private function registerDependencies(): void
    {
        // When entities change, queries and results might be invalid
        $this->invalidator->registerDependency('entities', ['queries', 'results']);

        // When schema changes, metadata and statements are invalid
        $this->invalidator->registerDependency('schema', ['metadata', 'statements']);

        // When a table changes, related queries are invalid
        $this->invalidator->registerDependency('tables', ['queries', 'results']);
    }

    /**
     * Register invalidation patterns
     * @return void
     */
    private function registerPatterns(): void
    {
        // Pattern for temporary cache entries
        $this->invalidator->registerPattern('temp:*', function ($entity, $op, $ctx, $inv) {
            // Invalidate temporary entries older than 5 minutes
            if (isset($ctx['created']) && (time() - $ctx['created'] > 300)) {
                $inv->invalidateTag('temporary');
            }
        });

        // Pattern for schema changes
        $this->invalidator->registerPattern('schema:*', function ($entity, $op, $ctx, $inv) {
            // Clear all metadata and statement caches
            $inv->invalidateTags(['metadata', 'statements']);

            // Log schema change
            if (isset($ctx['table'])) {
                error_log("Schema change detected for table: " . $ctx['table']);
            }
        });

        // Pattern for table-specific invalidation
        $this->invalidator->registerPattern('table:*', function ($entity, $op, $ctx, $inv) {
            if (preg_match('/^table:(.+)$/', $entity, $matches)) {
                $table = $matches[1];
                $inv->invalidateTag('table:' . $table);
            }
        });
    }

    /**
     * @param string $name
     * @return CacheInterface|null
     */
    public function getCache(string $name): ?CacheInterface
    {
        return $this->caches[$name] ?? null;
    }

    /**
     * @param string $name
     * @param CacheInterface $cache
     * @param array<string, mixed> $config
     * @return void
     */
    public function registerCache(string $name, CacheInterface $cache, array $config = []): void
    {
        $this->caches[$name] = $cache;
        $this->cacheConfigs[$name] = $config;

        if ($cache instanceof TaggableCacheInterface) {
            $this->invalidator->registerCache($name, $cache);
        }
    }

    /**
     * @param string $type
     * @param array<string, mixed> $context
     * @return void
     */
    public function invalidate(string $type, array $context = []): void
    {
        $this->invalidator->invalidate($type, 'manual', $context);
    }

    /**
     * @param string $table
     * @return void
     */
    public function invalidateTable(string $table): void
    {
        $this->invalidator->invalidateTable($table);
    }

    /**
     * @param array<string> $tables
     * @return void
     */
    public function invalidateTables(array $tables): void
    {
        $this->invalidator->invalidateTables($tables);
    }

    /**
     * @return void
     */
    public function clearAll(): void
    {
        foreach ($this->caches as $cache) {
            $cache->clear();
        }
    }

    /**
     * @param string|null $cacheName
     * @return void
     */
    public function warmUp(?string $cacheName = null): void
    {
        if ($cacheName !== null) {
            // Warm up specific cache
            if (isset($this->caches[$cacheName]) && method_exists($this->caches[$cacheName], 'warmUp')) {
                $this->caches[$cacheName]->warmUp();
            }
        } else {
            // Warm up all caches that support it
            foreach ($this->caches as $name => $cache) {
                if (method_exists($cache, 'warmUp')) {
                    $cache->warmUp();
                }
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStats(): array
    {
        $stats = [];

        foreach ($this->caches as $name => $cache) {
            if ($cache instanceof MemoryCache) {
                $cacheStats = $cache->getStats();
                $stats[$name] = [
                    'type' => $this->cacheConfigs[$name]['type'] ?? 'unknown',
                    'size' => $cacheStats['size'],
                    'hits' => $cacheStats['hits'],
                    'misses' => $cacheStats['misses'],
                    'hitRate' => $cacheStats['hitRate'],
                    'evictions' => $cacheStats['evictions'],
                    'config' => $this->cacheConfigs[$name] ?? [],
                ];
            } else {
                $stats[$name] = [
                    'type' => $this->cacheConfigs[$name]['type'] ?? 'unknown',
                    'config' => $this->cacheConfigs[$name] ?? [],
                ];
            }
        }

        // Add global stats
        $stats['_global'] = $this->calculateGlobalStats($stats);

        return $stats;
    }

    /**
     * @param array<string, array<string, mixed>> $stats
     * @return array<string, mixed>
     */
    private function calculateGlobalStats(array $stats): array
    {
        $totalHits = 0;
        $totalMisses = 0;
        $totalSize = 0;
        $totalEvictions = 0;

        foreach ($stats as $name => $cacheStats) {
            if ($name === '_global') {
                continue;
            }

            $totalHits += $cacheStats['hits'] ?? 0;
            $totalMisses += $cacheStats['misses'] ?? 0;
            $totalSize += $cacheStats['size'] ?? 0;
            $totalEvictions += $cacheStats['evictions'] ?? 0;
        }

        $totalRequests = $totalHits + $totalMisses;

        return [
            'total_caches' => count($this->caches),
            'total_hits' => $totalHits,
            'total_misses' => $totalMisses,
            'total_requests' => $totalRequests,
            'global_hit_rate' => $totalRequests > 0 ? ($totalHits / $totalRequests) : 0.0,
            'total_cached_items' => $totalSize,
            'total_evictions' => $totalEvictions,
            'memory_usage_estimate' => $this->estimateMemoryUsage(),
        ];
    }

    /**
     * @return int
     */
    private function estimateMemoryUsage(): int
    {
        // Rough estimate: assume average 1KB per cached item
        $totalSize = 0;

        foreach ($this->caches as $cache) {
            if (method_exists($cache, 'getStats')) {
                $stats = $cache->getStats();
                $totalSize += ($stats['size'] ?? 0) * 1024; // 1KB average
            }
        }

        return $totalSize;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => [],
        ];

        $stats = $this->getStats();
        $globalStats = $stats['_global'];

        // Check global hit rate
        if ($globalStats['global_hit_rate'] < 0.5) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Low global hit rate: ' . round($globalStats['global_hit_rate'] * 100, 2) . '%';
            $health['recommendations'][] = 'Consider increasing cache sizes or TTL values';
        }

        // Check individual cache health
        foreach ($stats as $name => $cacheStats) {
            if ($name === '_global') {
                continue;
            }

            // Check hit rate
            if (isset($cacheStats['hitRate']) && $cacheStats['hitRate'] < 0.3) {
                $health['issues'][] = "Cache '{$name}' has low hit rate: " . round($cacheStats['hitRate'] * 100, 2) . '%';
            }

            // Check eviction rate
            if (isset($cacheStats['evictions']) && isset($cacheStats['size'])) {
                $evictionRate = $cacheStats['size'] > 0 ? ($cacheStats['evictions'] / $cacheStats['size']) : 0;
                if ($evictionRate > 0.2) {
                    $health['issues'][] = "Cache '{$name}' has high eviction rate";
                    $health['recommendations'][] = "Increase size limit for cache '{$name}'";
                }
            }
        }

        // Check memory usage
        $memoryUsage = $globalStats['memory_usage_estimate'];
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit !== '-1') {
            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
            $usagePercentage = ($memoryUsage / $memoryLimitBytes) * 100;

            if ($usagePercentage > 10) {
                $health['status'] = 'warning';
                $health['issues'][] = 'Cache memory usage is ' . round($usagePercentage, 2) . '% of memory limit';
            }
        }

        if (empty($health['issues'])) {
            $health['message'] = 'All caches are operating normally';
        } else {
            $health['status'] = count($health['issues']) > 3 ? 'critical' : 'warning';
        }

        return $health;
    }

    /**
     * @param string $memoryLimit
     * @return int
     */
    private function parseMemoryLimit(string $memoryLimit): int
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $memoryLimit,
        };
    }
}
