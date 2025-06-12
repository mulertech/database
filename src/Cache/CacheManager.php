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
        // Pattern for entity-based invalidation
        $this->invalidator->registerPattern('entity:*', function ($entity, $op, $ctx, $inv) {
            if (isset($ctx['table'])) {
                $inv->invalidateTable($ctx['table']);
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
     * Warm up caches with optional context data
     *
     * @param string|null $cacheName Specific cache to warm up, or null for all
     * @param array<string, mixed> $context Context data for warming up (e.g., entityClasses)
     * @return void
     */
    public function warmUp(?string $cacheName = null, array $context = []): void
    {
        if ($cacheName !== null) {
            // Warm up specific cache
            $this->warmUpCache($cacheName, $context);
        } else {
            // Warm up all caches
            foreach ($this->caches as $name => $cache) {
                $this->warmUpCache($name, $context);
            }
        }
    }

    /**
     * Warm up a specific cache with context
     *
     * @param string $cacheName
     * @param array<string, mixed> $context
     * @return void
     */
    private function warmUpCache(string $cacheName, array $context): void
    {
        $cache = $this->caches[$cacheName] ?? null;

        if ($cache === null) {
            return;
        }

        // Special handling for MetadataCache
        if ($cacheName === 'metadata' && $cache instanceof MetadataCache) {
            $entityClasses = $context['entityClasses'] ?? [];
            if (!empty($entityClasses)) {
                $cache->warmUp($entityClasses);
            }
            return;
        }

        // Generic warmUp for other caches that support it
        if (method_exists($cache, 'warmUp')) {
            // Check if warmUp method accepts parameters
            $reflection = new \ReflectionMethod($cache, 'warmUp');
            $parameters = $reflection->getParameters();

            if (count($parameters) === 0) {
                // No parameters expected
                $cache->warmUp();
            } elseif (count($parameters) === 1 && $parameters[0]->isOptional()) {
                // Optional parameter, we can call without arguments
                $cache->warmUp();
            }
            // If the method requires parameters we don't know about, skip it
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStats(): array
    {
        $stats = [];

        foreach ($this->caches as $name => $cache) {
            if (method_exists($cache, 'getStatistics')) {
                $stats[$name] = array_merge(
                    ['type' => $this->cacheConfigs[$name]['type'] ?? 'unknown'],
                    $cache->getStatistics()
                );
            } else {
                $stats[$name] = [
                    'type' => $this->cacheConfigs[$name]['type'] ?? 'unknown',
                    'status' => 'no stats available'
                ];
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealthCheck(): array
    {
        $health = [
            'status' => 'ok',
            'caches' => [],
            'warnings' => [],
        ];

        foreach ($this->caches as $name => $cache) {
            $cacheHealth = ['name' => $name, 'status' => 'ok'];

            if (method_exists($cache, 'getStatistics')) {
                $stats = $cache->getStatistics();

                // Check hit rate
                if (isset($stats['hit_rate'])) {
                    $cacheHealth['hit_rate'] = $stats['hit_rate'];
                    if ($stats['hit_rate'] < 50) {
                        $health['warnings'][] = "Low hit rate for cache '$name': {$stats['hit_rate']}%";
                        $cacheHealth['status'] = 'warning';
                    }
                }

                // Check size
                if (isset($stats['size'], $stats['max_size'])) {
                    $usage = ($stats['size'] / $stats['max_size']) * 100;
                    $cacheHealth['usage'] = round($usage, 2);
                    if ($usage > 90) {
                        $health['warnings'][] = "Cache '$name' is nearly full: {$usage}%";
                        $cacheHealth['status'] = 'warning';
                    }
                }
            }

            $health['caches'][] = $cacheHealth;
        }

        if (!empty($health['warnings'])) {
            $health['status'] = 'warning';
        }

        return $health;
    }

    /**
     * @return CacheInvalidator
     */
    public function getInvalidator(): CacheInvalidator
    {
        return $this->invalidator;
    }

    /**
     * @return array<string>
     */
    public function getAvailableCaches(): array
    {
        return array_keys($this->caches);
    }
}
