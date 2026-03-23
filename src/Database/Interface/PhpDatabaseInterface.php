<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

/**
 * Interface PhpDatabaseInterface.
 *
 * Interface for database connection management and query execution.
 *
 * @author Sébastien Muler
 */
interface PhpDatabaseInterface
{
    /**
     * @param array<int|string, mixed> $options
     */
    public function prepare(string $query, array $options = []): Statement;

    public function beginTransaction(): bool;

    public function commit(): bool;

    public function rollBack(): bool;

    public function inTransaction(): bool;

    public function setAttribute(int $attribute, mixed $value): bool;

    public function exec(string $statement): int;

    /**
     * @param array<int, mixed>|null $constructorArgs
     */
    public function query(
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null,
    ): Statement;

    public function lastInsertId(?string $name = null): string;

    public function errorCode(): string|int|false;

    /**
     * @return array<int, string|null>
     */
    public function errorInfo(): array;

    public function getAttribute(int $attribute): mixed;

    public function quote(string $string, int $type = \PDO::PARAM_STR): string;
}
