<?php

namespace MulerTech\Database\PhpInterface;

use Iterator;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Traversable;

/**
 * Class Statement
 *
 * A wrapper around PDOStatement providing type-safe operations and error handling.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Statement
{
    /**
     * @param PDOStatement $statement The PDO statement to wrap
     */
    public function __construct(private PDOStatement $statement)
    {
    }

    /**
     * Executes a prepared statement.
     *
     * @param array<int|string, mixed>|null $params An array of values with as many elements as there are bound parameters
     * @return bool True on success
     * @throws PDOException|RuntimeException If execution fails
     */
    public function execute(?array $params = null): bool
    {
        try {
            if (false === $result = $this->statement->execute($params)) {
                throw new RuntimeException('Class : Statement, function : execute. The execute action was failed.');
            }
            return $result;
        } catch (PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * Fetches the next row from a result set.
     *
     * @param int $mode Controls how the next row will be returned
     * @param int $cursorOrientation The cursor orientation
     * @param int $cursorOffset The cursor offset
     * @return mixed The fetched row or false if no more rows
     */
    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->statement->fetch(...func_get_args());
    }

    /**
     * Binds a parameter to the specified variable name.
     *
     * @param string|int $param Parameter identifier
     * @param mixed $var Variable to bind
     * @param int $type Data type
     * @param int $maxLength Length of the data type
     * @param mixed|null $driverOptions Driver options
     * @return bool True on success
     * @throws RuntimeException If binding fails
     */
    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        if (false === $result = $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions)) {
            throw new RuntimeException(
                'Class : Statement, function : bindParam. The bindParam action was failed'
            );
        }

        return $result;
    }

    /**
     * Binds a column to a PHP variable.
     *
     * @param string|int $column Column identifier
     * @param mixed $var Variable to bind
     * @param int $type Data type
     * @param int $maxLength Length of the data type
     * @param mixed|null $driverOptions Driver options
     * @return bool True on success
     * @throws RuntimeException If binding fails
     */
    public function bindColumn(
        string|int $column,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        if (false === $result = $this->statement->bindColumn($column, $var, $type, $maxLength, $driverOptions)) {
            throw new RuntimeException('Class : Statement, function : bindColumn. The bindColumn action was failed');
        }
        return $result;
    }

    /**
     * Binds a value to a parameter.
     *
     * @param int|string $param Parameter identifier
     * @param mixed $value The value to bind
     * @param int $type Data type
     * @return bool True on success
     * @throws RuntimeException If binding fails
     */
    public function bindValue(int|string $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        if (false === $result = $this->statement->bindValue(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : bindValue. The bindValue action was failed');
        }
        return $result;
    }

    /**
     * Returns the number of rows affected by the last executed statement.
     *
     * @return int Row count
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * Returns a single column from the next row of a result set.
     *
     * @param int $column The 0-indexed column number to retrieve
     * @return mixed Returns a single column or false if no more rows
     */
    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    /**
     * Returns an array containing all of the result set rows.
     *
     * @param int $mode The fetch mode
     * @param mixed ...$args Additional mode-specific arguments
     * @return array<int|string, mixed> An array containing all of the remaining rows in the result set
     * @throws RuntimeException If fetch fails
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->statement->fetchAll(...func_get_args());
    }

    /**
     * Fetches the next row and returns it as an object.
     *
     * @param class-string $class Name of the created class
     * @param array<int|string, mixed> $constructorArgs Elements of this array are passed to the constructor
     * @return object|false The instance of the required class or false on failure
     * @throws RuntimeException If fetch fails
     */
    public function fetchObject(string $class = "stdClass", array $constructorArgs = []): object|false
    {
        if (false === $result = $this->statement->fetchObject($class, $constructorArgs)) {
            throw new RuntimeException('Class : Statement, function : fetchObject. The fetchObject action was failed');
        }
        return $result;
    }

    /**
     * Fetch the SQLSTATE error code.
     *
     * @return string|null The error code as a string, or null if no error occurred
     */
    public function errorCode(): ?string
    {
        return $this->statement->errorCode();
    }

    /**
     * Fetch extended error information.
     *
     * @return array<int, string|int|null> Error information array
     */
    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    /**
     * Set an attribute.
     *
     * @param int $attribute The attribute identifier
     * @param mixed $value The value for the attribute
     * @return bool True on success
     * @throws RuntimeException If setting attribute fails
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        if (false === $result = $this->statement->setAttribute($attribute, $value)) {
            throw new RuntimeException(
                'Class : Statement, function : setAttribute. The setAttribute action was failed'
            );
        }

        return $result;
    }

    /**
     * Get an attribute.
     *
     * @param int $name The attribute identifier
     * @return mixed The attribute value
     */
    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    /**
     * Returns the number of columns in the result set.
     *
     * @return int The number of columns
     */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * Returns metadata for a column in a result set.
     *
     * @param int $column The 0-indexed column number
     * @return array<string, mixed>|false An associative array or false if the column doesn't exist
     */
    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * Returns an iterator for traversing the result set.
     *
     * @return Traversable<mixed, array<int|string, mixed>> The iterator
     */
    public function getIterator(): Traversable
    {
        return $this->statement->getIterator();
    }

    /**
     * Sets the default fetch mode for this statement.
     *
     * @param int $mode The fetch mode
     * @param mixed ...$params Additional parameters for the fetch mode
     * @return bool True on success
     * @throws RuntimeException If setting fetch mode fails
     */
    public function setFetchMode(int $mode = PDO::FETCH_DEFAULT, mixed ...$params): bool
    {
        if (false === $result = $this->statement->setFetchMode(...func_get_args())) {
            throw new RuntimeException(
                'Class : Statement, function : setFetchMode. The setFetchMode action was failed'
            );
        }
        return $result;
    }

    /**
     * Advances to the next rowset in a multi-rowset statement handle.
     *
     * @return bool True on success or false if there are no more rowsets
     */
    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    /**
     * Closes the cursor, enabling the statement to be executed again.
     *
     * @return bool True on success
     */
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /**
     * Dumps the information contained by a prepared statement directly on the output.
     *
     * @return void
     */
    public function debugDumpParams(): void
    {
        $this->statement->debugDumpParams();
    }

    /**
     * Returns the query string that was prepared.
     *
     * @return string The query string
     */
    public function getQueryString(): string
    {
        return $this->statement->queryString;
    }
}
