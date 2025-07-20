<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;

/**
 * Database adapter that handles all database operations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DatabaseAdapter
{
    private ?DatabaseUtilities $utilities = null;

    public function __construct(
        private readonly PDO $connection,
        private readonly DatabaseOperationsManager $operationsManager
    ) {
    }

    public function beginTransaction(): bool
    {
        return $this->operationsManager->beginTransaction($this->connection);
    }

    public function commit(): bool
    {
        return $this->operationsManager->commit($this->connection);
    }

    public function rollBack(): bool
    {
        return $this->operationsManager->rollBack($this->connection);
    }

    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->connection->setAttribute($attribute, $value);
    }

    public function exec(string $statement): int
    {
        return $this->operationsManager->executeStatement($this->connection, $statement);
    }

    /**
     * @param array<int, mixed>|null $constructorArgs
     */
    public function query(
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement {
        return $this->operationsManager->executeQuery(
            $this->connection,
            $query,
            $fetchMode,
            $arg3,
            $constructorArgs
        );
    }

    public function lastInsertId(?string $name = null): string
    {
        $result = $this->connection->lastInsertId($name);
        if ($result === false) {
            throw new \RuntimeException('Failed to get last insert ID');
        }
        return $result;
    }

    public function errorCode(): string|int|false
    {
        return $this->getUtilities()->errorCode();
    }

    /**
     * @return array<int, string|null>
     */
    public function errorInfo(): array
    {
        return $this->getUtilities()->errorInfo();
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        return $this->getUtilities()->quote($string, $type);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->getUtilities()->getAttribute($attribute);
    }

    private function getUtilities(): DatabaseUtilities
    {
        if (!isset($this->utilities)) {
            $this->utilities = new DatabaseUtilities($this->connection);
        }
        return $this->utilities;
    }
}
