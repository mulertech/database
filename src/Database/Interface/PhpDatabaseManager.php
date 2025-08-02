<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;
use PDO;
use PDOException;
use RuntimeException;

/**
 * PhpDatabaseManager with integrated component management and reduced coupling
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PhpDatabaseManager implements PhpDatabaseInterface
{
    private ?PDO $connection = null;
    private int $transactionLevel = 0;
    private ?DatabaseCacheService $cacheService = null;
    private ?DatabaseParameterParserInterface $parameterParser;
    private ?QueryExecutorInterface $queryExecutor;

    /**
     * @param ConnectorInterface $connector
     * @param array<string, mixed> $parameters
     * @param CacheConfig|null $cacheConfig
     * @param DatabaseParameterParserInterface|null $parameterParser
     * @param QueryExecutorInterface|null $queryExecutor
     */
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly array $parameters,
        private readonly ?CacheConfig $cacheConfig = null,
        ?DatabaseParameterParserInterface $parameterParser = null,
        ?QueryExecutorInterface $queryExecutor = null
    ) {
        $this->parameterParser = $parameterParser;
        $this->queryExecutor = $queryExecutor;
    }

    /**
     * @return PDO
     */
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
     * @param string $query
     * @param array<int, mixed> $options
     * @return Statement
     */
    public function prepare(string $query, array $options = []): Statement
    {
        try {
            return $this->getCacheService()->prepareWithCaching(
                $query,
                $options,
                $this->getConnection(),
                fn () => $this->prepareDirect($query, $options)
            );
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    /**
     * @param string $query
     * @param int|null $fetchMode
     * @param int|string|object $arg3
     * @param array<int, mixed>|null $constructorArgs
     * @return Statement
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

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
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

    /**
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }

    /**
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->getConnection()->setAttribute($attribute, $value);
    }

    /**
     * @param string $statement
     * @return int
     */
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

        $this->getCacheService()->invalidateCacheForStatement($statement);
        return $result;
    }

    /**
     * @param string|null $name
     * @return string
     */
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
     * @return array|null[]|string[]
     */
    public function errorInfo(): array
    {
        return $this->getConnection()->errorInfo();
    }

    /**
     * @param string $string
     * @param int $type
     * @return string
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        return $this->getConnection()->quote($string, $type);
    }

    /**
     * @param int $attribute
     * @return mixed
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->getConnection()->getAttribute($attribute);
    }

    /**
     * Prepare a statement directly without caching
     * @param string $query
     * @param array<int, mixed> $options
     * @return Statement
     */
    private function prepareDirect(string $query, array $options = []): Statement
    {
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
    }

    /**
     * @return DatabaseParameterParserInterface
     */
    private function getParameterParser(): DatabaseParameterParserInterface
    {
        if ($this->parameterParser === null) {
            $this->parameterParser = new DatabaseParameterParser();
        }

        return $this->parameterParser;
    }

    /**
     * @return QueryExecutorInterface
     */
    private function getQueryExecutor(): QueryExecutorInterface
    {
        if ($this->queryExecutor === null) {
            $this->queryExecutor = new QueryExecutorHelper();
        }

        return $this->queryExecutor;
    }

    /**
     * @return DatabaseCacheService
     */
    private function getCacheService(): DatabaseCacheService
    {
        if ($this->cacheService === null) {
            $cacheManager = null;
            if ($this->cacheConfig !== null) {
                $cacheManager = new StatementCacheManager(
                    (string)spl_object_id($this),
                    $this->cacheConfig
                );
            }
            $this->cacheService = new DatabaseCacheService($cacheManager);
        }

        return $this->cacheService;
    }
}
