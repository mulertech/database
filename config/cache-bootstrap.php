<?php

declare(strict_types=1);

use MulerTech\Database\Cache\CacheConfig;
use MulerTech\Database\Cache\CacheFactory;
use MulerTech\Database\Cache\CacheManager;

/**
 * Register custom invalidation patterns
 *
 * @param \MulerTech\Database\Cache\CacheInvalidator $invalidator
 * @param array<string, mixed> $config
 * @return void
 */
if (!function_exists('registerInvalidationPatterns')) {
    function registerInvalidationPatterns($invalidator, array $config): void
    {
        // Pattern for temporary cache entries
        $invalidator->registerPattern('temp:*', function($entity, $op, $ctx, $inv) use ($config) {
            $ttl = $config['temp_cache_ttl'] ?? 300; // 5 minutes default
            if (isset($ctx['created']) && (time() - $ctx['created'] > $ttl)) {
                $inv->invalidateTag('temporary');
            }
        });

        // Pattern for user-specific cache
        $invalidator->registerPattern('user:*', function($entity, $op, $ctx, $inv) {
            if (preg_match('/^user:(\d+)$/', $entity, $matches)) {
                $userId = $matches[1];
                // Invalidate all user-related caches
                $inv->invalidateTags([
                                         'user:' . $userId,
                                         'user_sessions:' . $userId,
                                         'user_permissions:' . $userId,
                                     ]);
            }
        });

        // Pattern for migration/schema changes
        $invalidator->registerPattern('migration:*', function($entity, $op, $ctx, $inv) {
            // Clear everything on migration
            $inv->invalidateAll();
            error_log("Cache cleared due to migration: " . ($ctx['migration'] ?? 'unknown'));
        });

        // Pattern for bulk operations
        $invalidator->registerPattern('bulk:*', function($entity, $op, $ctx, $inv) {
            if (isset($ctx['tables']) && is_array($ctx['tables'])) {
                foreach ($ctx['tables'] as $table) {
                    $inv->invalidateTable($table);
                }
            }
        });

        // Custom patterns from config
        if (isset($config['invalidation_patterns']) && is_array($config['invalidation_patterns'])) {
            foreach ($config['invalidation_patterns'] as $pattern => $callback) {
                if (is_callable($callback)) {
                    $invalidator->registerPattern($pattern, $callback);
                }
            }
        }
    }
}

/**
 * Warm up specific cache
 *
 * @param CacheManager $manager
 * @param string $cacheName
 * @param array<string, mixed> $config
 * @return void
 */
if (!function_exists('warmUpCache')) {
    function warmUpCache(CacheManager $manager, string $cacheName, array $config): void
    {
        $cache = $manager->getCache($cacheName);

        if ($cache === null) {
            return;
        }

        switch ($cacheName) {
            case 'metadata':
                // Warm up entity metadata
                if (isset($config['entities']) && is_array($config['entities'])) {
                    foreach ($config['entities'] as $entityClass) {
                        // This would be handled by EntityHydrator::warmUpCache
                        // Just a placeholder for the concept
                    }
                }
                break;

            case 'queries':
                // Pre-compile common queries
                if (isset($config['queries']) && is_array($config['queries'])) {
                    foreach ($config['queries'] as $queryPattern) {
                        // Pre-compile and cache query patterns
                    }
                }
                break;
        }
    }
}

/**
 * Set up cache monitoring
 *
 * @param CacheManager $manager
 * @param array<string, mixed> $config
 * @return void
 */
if (!function_exists('setupMonitoring')) {
    function setupMonitoring(CacheManager $manager, array $config): void
    {
        // Register shutdown function to log final stats
        register_shutdown_function(function() use ($manager, $config) {
            if ($config['log_on_shutdown'] ?? true) {
                $stats = $manager->getStats();
                $health = $manager->getHealthCheck();

                // Log to file or monitoring service
                $logFile = $config['log_file'] ?? 'cache_stats.log';
                $logData = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'stats' => $stats,
                    'health' => $health,
                ];

                error_log(json_encode($logData) . "\n", 3, $logFile);
            }
        });

        // Set up periodic stats collection if configured
        if (isset($config['stats_interval']) && $config['stats_interval'] > 0) {
            // This would typically be handled by a cron job or background process
            // Just showing the concept here
        }
    }
}

/**
 * Bootstrap configuration for the cache system
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
return function(array $config = []): CacheManager {
    // Default configuration values
    $defaults = [
        'max_size' => 10000,
        'ttl' => 3600,
        'enable_stats' => true,
        'eviction_policy' => 'lru',
        'enable_compression' => true,
        'compression_threshold' => 1024,
    ];

    // Merge with provided config
    $config = array_merge($defaults, $config);

    // Create default cache configuration
    $defaultConfig = new CacheConfig(
        maxSize: $config['max_size'],
        ttl: $config['ttl'],
        enableStats: $config['enable_stats'],
        evictionPolicy: $config['eviction_policy']
    );

    // Set as default for factory
    CacheFactory::setDefaultConfig($defaultConfig);

    // Initialize the manager (singleton)
    $manager = CacheManager::getInstance();

    // Get the invalidator for pattern registration
    $invalidator = CacheFactory::getInvalidator();

    // Register custom invalidation patterns
    registerInvalidationPatterns($invalidator, $config);

    // Register custom cache warming strategies
    if (isset($config['warm_up']) && is_array($config['warm_up'])) {
        foreach ($config['warm_up'] as $cacheName => $warmUpConfig) {
            warmUpCache($manager, $cacheName, $warmUpConfig);
        }
    }

    // Set up monitoring if enabled
    if ($config['enable_monitoring'] ?? false) {
        setupMonitoring($manager, $config['monitoring'] ?? []);
    }

    return $manager;
};

/**
 * Example usage in application bootstrap:
 *
 * $cacheManager = require 'config/cache-bootstrap.php';
 * $cacheManager([
 *     'max_size' => 5000,
 *     'ttl' => 7200,
 *     'enable_monitoring' => true,
 *     'monitoring' => [
 *         'log_file' => '/var/log/app/cache_stats.log',
 *         'stats_interval' => 300, // 5 minutes
 *     ],
 *     'warm_up' => [
 *         'metadata' => [
 *             'entities' => [
 *                 User::class,
 *                 Product::class,
 *                 Order::class,
 *             ],
 *         ],
 *     ],
 * ]);
 */