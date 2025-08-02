<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;

/**
 * Service for handling database statement caching
 * Encapsulates cache-related operations
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class DatabaseCacheService
{
    public function __construct(
        private ?StatementCacheManager $cacheManager = null
    ) {
    }

    /**
     * Prepare statement with caching if available
     *
     * @param string $query
     * @param array<int, mixed> $options
     * @param PDO $connection
     * @param callable $directPrepareCallback
     * @return Statement
     */
    public function prepareWithCaching(
        string $query,
        array $options,
        PDO $connection,
        callable $directPrepareCallback
    ): Statement {
        if ($this->cacheManager === null) {
            return $directPrepareCallback();
        }

        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);
        $cachedStatement = $this->cacheManager->getCachedStatement($cacheKey, $connection);

        if ($cachedStatement !== null) {
            return new Statement($cachedStatement);
        }

        $statement = $directPrepareCallback();
        $this->cacheManager->cacheStatement($cacheKey, $statement->getPdoStatement(), $query);

        return $statement;
    }

    /**
     * Invalidate cache for statements affecting a table
     *
     * @param string $statement
     * @return void
     */
    public function invalidateCacheForStatement(string $statement): void
    {
        if ($this->cacheManager === null) {
            return;
        }

        $tableName = $this->extractTableFromQuery($statement);
        $this->cacheManager->invalidateTableStatements($tableName);
    }

    /**
     * Extract table name from SQL query
     *
     * @param string $query
     * @return string
     */
    private function extractTableFromQuery(string $query): string
    {
        $query = strtolower(trim($query));

        if (preg_match('/(?:insert\s+into|update|delete\s+from)\s+([a-z_][a-z0-9_]*)/i', $query, $matches)) {
            return $matches[1];
        }

        if (preg_match('/from\s+([a-z_][a-z0-9_]*)/i', $query, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
