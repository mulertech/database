<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;
use PDOException;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PhpDatabaseManager implements PhpDatabaseInterface
{
    private ?PDO $connection = null;
    private int $transactionLevel = 0;
    private ?DatabaseParameterParserInterface $parameterParser;
    private ?QueryExecutorInterface $queryExecutor;

    /**
     * @param ConnectorInterface $connector
     * @param array<string, mixed> $parameters
     * @param DatabaseParameterParserInterface|null $parameterParser
     * @param QueryExecutorInterface|null $queryExecutor
     */
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly array $parameters,
        ?DatabaseParameterParserInterface $parameterParser = null,
        ?QueryExecutorInterface $queryExecutor = null
    ) {
        $this->parameterParser = $parameterParser;
        $this->queryExecutor = $queryExecutor;
    }

    public function getConnection(): PDO
    {
        if (!isset($this->connection)) {
            $parameters = $this->getParameterParser()->parseParameters($this->parameters);
            $username = is_string($parameters['user']) ? $parameters['user'] : '';
            $password = is_string($parameters['pass']) ? $parameters['pass'] : '';

            $this->connection = $this->connector->connect($parameters, $username, $password);
        }

        return $this->connection;
    }

    /**
     * @param array<int, mixed> $options
     */
    public function prepare(string $query, array $options = []): Statement
    {
        try {
            $statement = empty($options)
                ? $this->getConnection()->prepare($query)
                : $this->getConnection()->prepare($query, $options);

            if ($statement === false) {
                throw new RuntimeException(
                    sprintf(
                        'Failed to prepare statement. Error: %s. Query: %s',
                        $this->getConnection()->errorInfo()[2] ?? 'Unknown error',
                        $query
                    )
                );
            }

            return new Statement($statement);
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
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
        return $this->getQueryExecutor()->executeQuery(
            $this->getConnection(),
            $query,
            $fetchMode,
            $arg3,
            $constructorArgs
        );
    }

    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            try {
                $this->transactionLevel = 1;
                return $this->getConnection()->beginTransaction();
            } catch (PDOException $exception) {
                throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
            }
        }

        ++$this->transactionLevel;
        return true;
    }

    public function commit(): bool
    {
        if ($this->transactionLevel === 1) {
            try {
                $result = $this->getConnection()->commit();
                $this->transactionLevel = 0;
                return $result;
            } catch (PDOException $exception) {
                throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
            }
        }

        if ($this->transactionLevel > 0) {
            --$this->transactionLevel;
        }

        return true;
    }

    public function rollBack(): bool
    {
        try {
            $result = $this->getConnection()->rollBack();
            $this->transactionLevel = 0;
            return $result;
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->getConnection()->setAttribute($attribute, $value);
    }

    public function exec(string $statement): int
    {
        $result = $this->getConnection()->exec($statement);

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Failed to execute statement. Error: %s',
                    $this->getConnection()->errorInfo()[2] ?? 'Unknown error'
                )
            );
        }

        return $result;
    }

    public function lastInsertId(?string $name = null): string
    {
        $result = $this->getConnection()->lastInsertId($name);

        if ($result === false) {
            throw new RuntimeException('Failed to get last insert ID');
        }

        return $result;
    }

    public function errorCode(): string|int|false
    {
        $errorCode = $this->getConnection()->errorCode();
        return $errorCode ?? false;
    }

    /**
     * @return array<int, string|null>
     */
    public function errorInfo(): array
    {
        return $this->getConnection()->errorInfo();
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        return $this->getConnection()->quote($string, $type);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->getConnection()->getAttribute($attribute);
    }

    /**
     * Lazy loading for parameter parser
     */
    private function getParameterParser(): DatabaseParameterParserInterface
    {
        if ($this->parameterParser === null) {
            $this->parameterParser = new DatabaseParameterParser();
        }

        return $this->parameterParser;
    }

    /**
     * Lazy loading for query executor
     */
    private function getQueryExecutor(): QueryExecutorInterface
    {
        if ($this->queryExecutor === null) {
            $this->queryExecutor = new QueryExecutorHelper();
        }

        return $this->queryExecutor;
    }
}
