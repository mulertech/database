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
     * @var array<string, CacheInterface|QueryStructureCache>
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
            $config ??= self::getDefaultConfig();
            self::$instances[$name] = new MemoryCache($config);
            self::registerWithInvalidator($name, self::$instances[$name]);
        }

        if (!self::$instances[$name] instanceof MemoryCache) {
            throw new RuntimeException("Cache instance is not of type MemoryCache");
        }

        return self::$instances[$name];
    }

    /**
     * @param string $name
     * @param CacheConfig|null $config
     * @return MetadataCache
     */
    public static function createMetadataCache(string $name, ?CacheConfig $config = null): MetadataCache
    {
        if (!isset(self::$instances[$name])) {
            $config ??= self::getDefaultConfig();
            self::$instances[$name] = new MetadataCache($config);
            self::registerWithInvalidator($name, self::$instances[$name]);
        }

        if (!self::$instances[$name] instanceof MetadataCache) {
            throw new RuntimeException("Cache instance is not of type MetadataCache");
        }

        return self::$instances[$name];
    }

    /**
     * @param string $name
     * @param CacheConfig|null $config
     * @return ResultSetCache
     */
    public static function createResultSetCache(string $name, ?CacheConfig $config = null): ResultSetCache
    {
        if (!isset(self::$instances[$name])) {
            $config ??= self::getDefaultConfig();
            // ResultSetCache needs a CacheInterface as first parameter, not CacheConfig
            $baseCache = new MemoryCache($config);
            self::$instances[$name] = new ResultSetCache($baseCache, 1024);
            self::registerWithInvalidator($name, self::$instances[$name]);
        }

        if (!self::$instances[$name] instanceof ResultSetCache) {
            throw new RuntimeException("Cache instance is not of type ResultSetCache");
        }

        return self::$instances[$name];
    }

    /**
     * @param string $name
     * @param CacheConfig|null $config
     * @return QueryStructureCache
     */
    public static function createQueryStructureCache(string $name, ?CacheConfig $config = null): QueryStructureCache
    {
        if (!isset(self::$instances[$name])) {
            $config ??= self::getDefaultConfig();
            // QueryStructureCache expects int maxCacheSize, not CacheConfig
            self::$instances[$name] = new QueryStructureCache($config->maxSize, $config->ttl);
            // QueryStructureCache doesn't implement TaggableCacheInterface, so don't register with invalidator
        }

        if (!self::$instances[$name] instanceof QueryStructureCache) {
            throw new RuntimeException("Cache instance is not of type QueryStructureCache");
        }

        return self::$instances[$name];
    }

    /**
     * @param string $name
     * @return CacheInterface|QueryStructureCache|null
     */
    public static function get(string $name): CacheInterface|QueryStructureCache|null
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
