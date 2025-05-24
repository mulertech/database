<?php

namespace MulerTech\Database\PhpInterface;

use PDO;

/**
 * Interface PhpDatabaseInterface
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
interface PhpDatabaseInterface
{
    /**
     * @param string $query
     * @param array $options
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
     * @param $value
     * @return bool
     */
    public function setAttribute(int $attribute, $value): bool;

    /**
     * @param string $statement
     * @return int
     */
    public function exec(string $statement): int;

    /**
     * @param string $query
     * @param int $fetchMode
     * @param null $arg3
     * @param array $ctorargs
     * @return Statement
     */
    public function query(
        string $query,
        int $fetchMode = PDO::ATTR_DEFAULT_FETCH_MODE,
        int|string|object|null $arg3 = null,
        array|null $constructorArgs = null
    ): Statement;

    /**
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(?string $name = null): string;

    /**
     * @return mixed
     */
    public function errorCode();

    /**
     * @return array
     */
    public function errorInfo(): array;

    /**
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute);

    /**
     * @param string $string
     * @param int $type
     * @return string
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string;

}
