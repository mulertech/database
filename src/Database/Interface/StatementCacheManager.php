<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\MemoryCache;
use PDOStatement;
use RuntimeException;

/**
 * Manages prepared statement caching
 */
final class StatementCacheManager
{
    private MemoryCache $cache;
    /** @var array<string, int> */
    private array $usageCount = [];

    public function __construct(?CacheConfig $config = null)
    {
        $this->cache = CacheFactory::createMemoryCache(
            'prepared_statements_' . spl_object_id($this),
            $config ?? new CacheConfig(
                maxSize: 100,
                ttl: 3600,
                evictionPolicy: 'lfu',
            )
        );
    }

    public function get(string $key): ?PDOStatement
    {
        $statement = $this->cache->get($key);

        if ($statement instanceof PDOStatement) {
            $this->usageCount[$key] = ($this->usageCount[$key] ?? 0) + 1;
            return $statement;
        }

        return null;
    }

    /**
     * @param array<string> $tags
     */
    public function set(string $key, PDOStatement $statement, array $tags = []): void
    {
        $this->cache->set($key, $statement);

        if (!empty($tags)) {
            $this->cache->tag($key, $tags);
        }

        $this->usageCount[$key] = 1;
    }

    /**
     * @param array<int|string, mixed> $options
     */
    public function generateKey(string $query, array $options): string
    {
        return sprintf(
            'stmt_%s_%s',
            md5($query),
            md5(serialize($options))
        );
    }

    public function invalidateTable(string $table): void
    {
        $this->cache->invalidateTag($table);
    }

    public function getUsageCount(string $key): int
    {
        return $this->usageCount[$key] ?? 0;
    }

    public function clear(): void
    {
        $this->cache->clear();
        $this->usageCount = [];
    }
}
