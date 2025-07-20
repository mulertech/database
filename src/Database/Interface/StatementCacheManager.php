<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\MemoryCache;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Manages prepared statement caching for database operations
 */
class StatementCacheManager
{
    private ?MemoryCache $statementCache = null;
    /** @var array<string, int> */
    private array $statementUsageCount = [];

    public function __construct(
        private readonly bool $enabled,
        ?CacheConfig $cacheConfig = null
    ) {
        if ($this->enabled) {
            $this->statementCache = CacheFactory::createMemoryCache(
                'prepared_statements_' . spl_object_id($this),
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

    /**
     * @param array<string, mixed> $options
     */
    public function prepareWithCache(string $query, array $options, PDO $connection): Statement
    {
        $cacheKey = $this->getStatementCacheKey($query, $options);

        // Track usage for analytics
        $this->statementUsageCount[$cacheKey] = ($this->statementUsageCount[$cacheKey] ?? 0) + 1;

        // Check cache first
        $cachedStatement = $this->statementCache?->get($cacheKey);

        if ($cachedStatement instanceof PDOStatement) {
            // Verify the statement is still valid
            try {
                // Test if connection is still alive
                $connection->getAttribute(PDO::ATTR_CONNECTION_STATUS);
                return new Statement($cachedStatement);
            } catch (PDOException) {
                // Connection lost, invalidate cache
                $this->statementCache?->delete($cacheKey);
            }
        }

        // Prepare new statement
        $statement = $this->prepareDirect($query, $options, $connection);

        // Cache the PDOStatement (not the wrapper)
        $this->statementCache?->set($cacheKey, $statement->getPdoStatement());

        // Tag for easy invalidation
        $this->statementCache?->tag($cacheKey, ['statements', $this->extractTableFromQuery($query)]);

        return $statement;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function prepareDirect(string $query, array $options, PDO $connection): Statement
    {
        $statement = empty($options)
            ? $connection->prepare($query)
            : $connection->prepare($query, $options);

        if ($statement === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to prepare statement. Error: %s. Query: %s',
                    $connection->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($statement);
    }

    public function invalidateTable(string $table): void
    {
        if ($this->enabled && $table !== 'unknown') {
            $this->statementCache?->invalidateTag($table);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getStatementCacheKey(string $query, array $options): string
    {
        return sprintf(
            'stmt:%s:%s',
            md5($query),
            md5(serialize($options))
        );
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
