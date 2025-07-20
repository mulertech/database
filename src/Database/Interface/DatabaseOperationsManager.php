<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;

/**
 * Combined manager for database operations and utilities
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DatabaseOperationsManager
{
    private ?DatabaseUtilities $utilities = null;

    public function __construct(
        private readonly TransactionManager $transactionManager,
        private readonly QueryExecutor $queryExecutor
    ) {
    }

    public function beginTransaction(PDO $connection): bool
    {
        return $this->transactionManager->beginTransaction($connection);
    }

    public function commit(PDO $connection): bool
    {
        return $this->transactionManager->commit($connection);
    }

    public function rollBack(PDO $connection): bool
    {
        return $this->transactionManager->rollBack($connection);
    }

    public function executeStatement(PDO $connection, string $statement): int
    {
        return $this->queryExecutor->executeStatement($connection, $statement);
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
        return $this->queryExecutor->executeQuery(
            $connection,
            $query,
            $fetchMode,
            $arg3,
            $constructorArgs
        );
    }

    public function getUtilities(PDO $connection): DatabaseUtilities
    {
        if (!isset($this->utilities)) {
            $this->utilities = new DatabaseUtilities($connection);
        }
        return $this->utilities;
    }
}
