<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface;

use PDO;

/**
 * Interface PhpDatabaseInterface
 *
 * Interface for database connection management and query execution.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface PhpDatabaseInterface
{
    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return Statement
     */
    public function prepare(string $query, array $options = []): Statement;

    /**
     * @return bool
     */
    public function beginTransaction(): bool;

    /**
     * @return bool
     */
    public function commit(): bool;

    /**
     * @return bool
     */
    public function rollBack(): bool;

    /**
     * @return bool
     */
    public function inTransaction(): bool;

    /**
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute(int $attribute, mixed $value): bool;

    /**
     * @param string $statement
     * @return int
     */
    public function exec(string $statement): int;

    /**
     * @param string $query
     * @param int|null $fetchMode
     * @param int|string|object $arg3
     * @param array<int, mixed>|null $constructorArgs
     * @return Statement
     */
    public function query(
        string $query,
        int|null $fetchMode = null,
        int|string|object $arg3 = '',
        array|null $constructorArgs = null
    ): Statement;

    /**
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * @return string|int|false
     */
    public function errorCode(): string|int|false;

    /**
     * @return array<int, string|null>
     */
    public function errorInfo(): array;

    /**
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute): mixed;

    /**
     * @param string $string
     * @param int $type
     * @return string
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string;

}
