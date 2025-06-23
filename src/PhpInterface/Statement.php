<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Traversable;

/**
 * A wrapper around PDOStatement providing type-safe operations and error handling
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
readonly class Statement
{
    /**
     * @param PDOStatement $statement
     */
    public function __construct(private PDOStatement $statement)
    {
    }

    /**
     * Get the underlying PDOStatement
     * @return PDOStatement
     */
    public function getPdoStatement(): PDOStatement
    {
        return $this->statement;
    }

    /**
     * @param array<int|string, mixed>|null $params
     * @return bool
     * @throws PDOException|RuntimeException
     */
    public function execute(?array $params = null): bool
    {
        try {
            $result = $this->statement->execute($params);

            if ($result === false) {
                throw new RuntimeException(
                    sprintf(
                        'Statement execution failed. Error: %s',
                        $this->statement->errorInfo()[2] ?? 'Unknown error'
                    )
                );
            }

            return true;
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    /**
     * @param int $mode
     * @param int $cursorOrientation
     * @param int $cursorOffset
     * @return mixed
     */
    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @param string|int $param
     * @param mixed $var
     * @param int $type
     * @param int $maxLength
     * @param mixed $driverOptions
     * @return bool
     * @throws RuntimeException
     */
    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $result = $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to bind parameter %s. Error: %s',
                    $param,
                    $this->statement->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        return true;
    }

    /**
     * @param string|int $column
     * @param mixed $var
     * @param int $type
     * @param int $maxLength
     * @param mixed $driverOptions
     * @return bool
     * @throws RuntimeException
     */
    public function bindColumn(
        string|int $column,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $result = $this->statement->bindColumn($column, $var, $type, $maxLength, $driverOptions);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to bind column %s. Error: %s',
                    $column,
                    $this->statement->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        return true;
    }

    /**
     * @param string|int $param
     * @param mixed $value
     * @param int $type
     * @return bool
     * @throws RuntimeException
     */
    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $result = $this->statement->bindValue($param, $value, $type);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to bind value for parameter %s. Error: %s',
                    $param,
                    $this->statement->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        return true;
    }

    /**
     * @return int
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * @param int $column
     * @return mixed
     */
    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    /**
     * @param int $mode
     * @param mixed ...$args
     * @return array<int, mixed>
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->statement->fetchAll($mode, ...$args);
    }

    /**
     * @param class-string $class
     * @param array<int|string, mixed> $constructorArgs
     * @return object|false
     */
    public function fetchObject(string $class = "stdClass", array $constructorArgs = []): object|false
    {
        return $this->statement->fetchObject($class, $constructorArgs);
    }

    /**
     * @return string|null
     */
    public function errorCode(): ?string
    {
        return $this->statement->errorCode();
    }

    /**
     * @return array<int, string|int|null>
     */
    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    /**
     * @param int $attribute
     * @param mixed $value
     * @return bool
     * @throws RuntimeException
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        $result = $this->statement->setAttribute($attribute, $value);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to set attribute %d. Error: %s',
                    $attribute,
                    $this->statement->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        return true;
    }

    /**
     * @param int $name
     * @return mixed
     */
    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    /**
     * @return int
     */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * @param int $column
     * @return array<string, mixed>|false
     */
    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * @param int $mode
     * @param mixed ...$params
     * @return bool
     * @throws RuntimeException
     */
    public function setFetchMode(int $mode = PDO::FETCH_DEFAULT, mixed ...$params): bool
    {
        $result = $this->statement->setFetchMode(...func_get_args());

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to set fetch mode %d. Error: %s',
                    $mode,
                    $this->statement->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    /**
     * @return bool
     */
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /**
     * @return string
     */
    public function debugDumpParams(): string
    {
        ob_start();
        $this->statement->debugDumpParams();
        return ob_get_clean() ?: '';
    }

    /**
     * @return Traversable<int, mixed>
     */
    public function getIterator(): Traversable
    {
        return $this->statement;
    }

    /**
     * @return string
     */
    public function getQueryString(): string
    {
        return $this->statement->queryString;
    }
}
