<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Handles query execution operations for the database manager
 */
class QueryExecutor
{
    public function __construct(
        private readonly StatementCacheManager $cacheManager
    ) {
    }

    /**
     * @param array<int, mixed>|null $constructorArgs
     */
    public function executeQuery(
        PDO $connection,
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement {
        if ($fetchMode === null) {
            return $this->executeSimpleQuery($connection, $query);
        }

        if ($fetchMode === PDO::FETCH_CLASS) {
            return $this->executeClassQuery($connection, $query, $arg3, $constructorArgs);
        }

        if ($fetchMode === PDO::FETCH_INTO) {
            return $this->executeIntoQuery($connection, $query, $arg3);
        }

        return $this->executeWithFetchMode($connection, $query, $fetchMode);
    }

    public function executeStatement(PDO $connection, string $statement): int
    {
        $result = $connection->exec($statement);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to execute statement. Error: %s',
                    $connection->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        // Invalidate related cached statements
        if ($this->cacheManager->isEnabled()) {
            $table = $this->extractTableFromQuery($statement);
            $this->cacheManager->invalidateTable($table);
        }

        return $result;
    }

    private function executeSimpleQuery(PDO $connection, string $query): Statement
    {
        $result = $connection->query($query);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Query failed. Error: %s. Statement: %s',
                    $connection->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
    }

    /**
     * @param array<int, mixed> $constructorArgs
     */
    private function executeClassQuery(
        PDO $connection,
        string $query,
        int|string|object $arg3,
        ?array $constructorArgs
    ): Statement {
        $result = $connection->query(
            $query,
            PDO::FETCH_CLASS,
            is_string($arg3) ? $arg3 : '',
            is_array($constructorArgs) ? $constructorArgs : []
        );

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Query failed. Error: %s. Statement: %s',
                    $connection->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
    }

    private function executeIntoQuery(PDO $connection, string $query, int|string|object $arg3): Statement
    {
        if (!is_object($arg3)) {
            throw new InvalidArgumentException(
                'When using FETCH_INTO, the third argument must be an object.'
            );
        }

        $result = $connection->query($query, PDO::FETCH_INTO, $arg3);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Query failed. Error: %s. Statement: %s',
                    $connection->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
    }

    private function executeWithFetchMode(PDO $connection, string $query, int $fetchMode): Statement
    {
        $result = $connection->query($query, $fetchMode);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Query failed. Error: %s. Statement: %s',
                    $connection->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
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
