<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;
use PDOStatement;

/**
 * A wrapper around PDOStatement providing type-safe operations and error handling.
 *
 * @author Sébastien Muler
 */
readonly class Statement
{
    public function __construct(private \PDOStatement $statement)
    {
    }

    /**
     * Get the underlying PDOStatement.
     */
    public function getPdoStatement(): \PDOStatement
    {
        return $this->statement;
    }

    /**
     * @param array<int|string, mixed>|null $params
     *
     * @throws \PDOException|\RuntimeException
     */
    public function execute(?array $params = null): bool
    {
        try {
            $result = $this->statement->execute($params);

            if (false === $result) {
                throw new \RuntimeException(sprintf('Statement execution failed. Error: %s', $this->statement->errorInfo()[2] ?? 'Unknown error'));
            }

            return true;
        } catch (\PDOException $exception) {
            throw new \PDOException($exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0,
    ): mixed {
        return $this->statement->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * @throws \RuntimeException
     */
    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null,
    ): bool {
        $result = $this->statement->bindParam($param, $var, $type, $maxLength, $driverOptions);

        if (false === $result) {
            throw new \RuntimeException(sprintf('Failed to bind parameter %s. Error: %s', $param, $this->statement->errorInfo()[2] ?? 'Unknown error'));
        }

        return true;
    }

    /**
     * @throws \RuntimeException
     */
    public function bindColumn(
        string|int $column,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null,
    ): bool {
        $result = $this->statement->bindColumn($column, $var, $type, $maxLength, $driverOptions);

        if (false === $result) {
            throw new \RuntimeException(sprintf('Failed to bind column %s. Error: %s', $column, $this->statement->errorInfo()[2] ?? 'Unknown error'));
        }

        return true;
    }

    /**
     * @throws \RuntimeException
     */
    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        $result = $this->statement->bindValue($param, $value, $type);

        if (false === $result) {
            throw new \RuntimeException(sprintf('Failed to bind value for parameter %s. Error: %s', $param, $this->statement->errorInfo()[2] ?? 'Unknown error'));
        }

        return true;
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->statement->fetchColumn($column);
    }

    /**
     * @return array<int, mixed>
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        // Validate args to ensure they are compatible with PDO
        $validatedArgs = [];
        foreach ($args as $arg) {
            if (is_callable($arg) || is_int($arg) || is_string($arg)) {
                $validatedArgs[] = $arg;
            }
        }

        return $this->statement->fetchAll($mode, ...$validatedArgs);
    }

    /**
     * @param class-string             $class
     * @param array<int|string, mixed> $constructorArgs
     */
    public function fetchObject(string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        return $this->statement->fetchObject($class, $constructorArgs);
    }

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
     * @throws \RuntimeException
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        $result = $this->statement->setAttribute($attribute, $value);

        if (false === $result) {
            throw new \RuntimeException(sprintf('Failed to set attribute %d. Error: %s', $attribute, $this->statement->errorInfo()[2] ?? 'Unknown error'));
        }

        return true;
    }

    public function getAttribute(int $name): mixed
    {
        return $this->statement->getAttribute($name);
    }

    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getColumnMeta(int $column): array|false
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * @throws \RuntimeException
     */
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return $this->statement->setFetchMode($mode, ...$args);
    }

    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    public function debugDumpParams(): string
    {
        ob_start();
        $this->statement->debugDumpParams();

        return ob_get_clean() ?: '';
    }

    /**
     * @return \Traversable<int, mixed>
     */
    public function getIterator(): \Traversable
    {
        return $this->statement;
    }

    public function getQueryString(): string
    {
        return $this->statement->queryString;
    }
}
