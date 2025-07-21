<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\MemoryCache;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Manages prepared statement caching for database connections
 */
class StatementCacheManager
{
    private ?MemoryCache $statementCache = null;
    /** @var array<string, int> */
    private array $statementUsageCount = [];

    public function __construct(
        private readonly bool $enabled,
        private readonly string $instanceId,
        ?CacheConfig $cacheConfig = null
    ) {
        if ($this->enabled) {
            $this->statementCache = CacheFactory::createMemoryCache(
                'prepared_statements_' . $this->instanceId,
                $cacheConfig ?? new CacheConfig(
                    maxSize: 100,
                    ttl: 3600,
                    evictionPolicy: 'lfu',
                )
            );
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getCachedStatement(string $cacheKey, PDO $connection): ?PDOStatement
    {
        if (!$this->enabled) {
            return null;
        }

        $this->trackUsage($cacheKey);
        $cachedStatement = $this->statementCache?->get($cacheKey);

        if ($cachedStatement instanceof PDOStatement) {
            try {
                // Test if connection is still alive
                $connection->getAttribute(PDO::ATTR_CONNECTION_STATUS);
                return $cachedStatement;
            } catch (PDOException) {
                // Connection lost, invalidate cache
                $this->statementCache?->delete($cacheKey);
                return null;
            }
        }

        return null;
    }

    public function cacheStatement(string $cacheKey, PDOStatement $statement, string $query): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->statementCache?->set($cacheKey, $statement);
        $this->statementCache?->tag($cacheKey, ['statements', $this->extractTableFromQuery($query)]);
    }

    public function invalidateTableStatements(string $tableName): void
    {
        if ($this->enabled && $tableName !== 'unknown') {
            $this->statementCache?->invalidateTag($tableName);
        }
    }

    /**
     * @param array<int, mixed> $options
     */
    public function generateCacheKey(string $query, array $options): string
    {
        return sprintf(
            'stmt:%s:%s',
            md5($query),
            md5(serialize($options))
        );
    }

    private function trackUsage(string $cacheKey): void
    {
        $this->statementUsageCount[$cacheKey] = ($this->statementUsageCount[$cacheKey] ?? 0) + 1;
    }

    private function extractTableFromQuery(string $query): string
    {
        $query = strtolower(trim($query));

        // For INSERT, UPDATE, DELETE
        if (preg_match('/(?:insert\s+into|update|delete\s+from)\s+([a-z_][a-z0-9_]*)/i', $query, $matches)) {
            return $matches[1];
        }

        // For SELECT
        if (preg_match('/from\s+([a-z_][a-z0-9_]*)/i', $query, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
