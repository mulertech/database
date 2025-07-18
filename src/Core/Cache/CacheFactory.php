<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use RuntimeException;

/**
 * Class CacheFactory
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CacheFactory
{
    /**
     * @var array<string, CacheInterface>
     */
    private static array $instances = [];

    /**
     * @var CacheConfig|null
     */
    private static ?CacheConfig $defaultConfig = null;

    /**
     * @var CacheInvalidator|null
     */
    private static ?CacheInvalidator $invalidator = null;

    /**
     * @param string $name
     * @param CacheConfig|null $config
     * @return MemoryCache
     */
    public static function createMemoryCache(string $name, ?CacheConfig $config = null): MemoryCache
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new MemoryCache($config ?? self::getDefaultConfig());
            self::registerWithInvalidator($name, self::$instances[$name]);
        }

        return self::$instances[$name] instanceof MemoryCache
            ? self::$instances[$name]
            : throw new RuntimeException("Cache instance is not of type MemoryCache");
    }

    /**
     * @param string $name
     * @param CacheConfig|null $config
     * @return MetadataCache
     */
    public static function createMetadataCache(string $name = 'metadata', ?CacheConfig $config = null): MetadataCache
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new MetadataCache($config);
            self::registerWithInvalidator($name, self::$instances[$name]);
        }

        return self::$instances[$name] instanceof MetadataCache
            ? self::$instances[$name]
            : throw new RuntimeException("Cache instance is not of type MetadataCache");
    }

    /**
     * @param string $name
     * @param CacheInterface|null $backend
     * @param int $compressionThreshold
     * @return ResultSetCache
     */
    public static function createResultSetCache(
        string $name = 'resultset',
        ?CacheInterface $backend = null,
        int $compressionThreshold = 1024
    ): ResultSetCache {
        if (!isset(self::$instances[$name])) {
            $backend ??= self::createMemoryCache($name . '_backend');
            self::$instances[$name] = new ResultSetCache($backend, $compressionThreshold);
            self::registerWithInvalidator($name, self::$instances[$name]);
        }

        return self::$instances[$name] instanceof ResultSetCache
            ? self::$instances[$name]
            : throw new RuntimeException("Cache instance is not of type ResultSetCache");
    }

    /**
     * @param string $name
     * @return CacheInterface|null
     */
    public static function get(string $name): ?CacheInterface
    {
        return self::$instances[$name] ?? null;
    }

    /**
     * @return CacheInvalidator
     */
    public static function getInvalidator(): CacheInvalidator
    {
        if (self::$invalidator === null) {
            self::$invalidator = new CacheInvalidator();

            // Register all existing caches
            foreach (self::$instances as $name => $cache) {
                if ($cache instanceof TaggableCacheInterface) {
                    self::$invalidator->registerCache($name, $cache);
                }
            }
        }

        return self::$invalidator;
    }

    /**
     * @return void
     */
    public static function reset(): void
    {
        self::$instances = [];
        self::$defaultConfig = null;
        self::$invalidator = null;
    }

    /**
     * @return CacheConfig
     */
    private static function getDefaultConfig(): CacheConfig
    {
        if (self::$defaultConfig === null) {
            self::$defaultConfig = new CacheConfig();
        }

        return self::$defaultConfig;
    }

    /**
     * @param string $name
     * @param CacheInterface $cache
     * @return void
     */
    private static function registerWithInvalidator(string $name, CacheInterface $cache): void
    {
        if ($cache instanceof TaggableCacheInterface) {
            self::getInvalidator()->registerCache($name, $cache);
        }
    }
}
