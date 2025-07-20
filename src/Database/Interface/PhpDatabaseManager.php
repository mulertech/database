<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;
use PDO;
use PDOException;

/**
 * Class PhpDatabaseManager
 *
 * Enhanced PhpDatabaseManager with prepared statement caching
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PhpDatabaseManager implements PhpDatabaseInterface
{
    private ?PDO $connection = null;
    private ?DatabaseAdapter $adapter = null;
    private StatementCacheManager $cacheManager;

    /**
     * @param ConnectorInterface $connector
     * @param array<string, mixed> $parameters
     * @param CacheConfig|null $cacheConfig
     * @param DatabaseParameterParser|null $parameterParser
     */
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly array $parameters,
        ?CacheConfig $cacheConfig = null,
        private readonly ?DatabaseParameterParser $parameterParser = null
    ) {
        $this->cacheManager = new StatementCacheManager(true, $cacheConfig);
    }

    /**
     * Create instance with caching disabled
     * @param array<string, mixed> $parameters
     */
    public static function withoutCache(
        ConnectorInterface $connector,
        array $parameters,
        ?DatabaseParameterParser $parameterParser = null
    ): self {
        $instance = new self($connector, $parameters, null, $parameterParser);
        $instance->cacheManager = new StatementCacheManager(false, null);
        return $instance;
    }

    /**
     * Create instance with custom cache configuration
     * @param array<string, mixed> $parameters
     */
    public static function withCacheConfig(
        ConnectorInterface $connector,
        array $parameters,
        CacheConfig $cacheConfig,
        ?DatabaseParameterParser $parameterParser = null
    ): self {
        return new self($connector, $parameters, $cacheConfig, $parameterParser);
    }

    /**
     * Get database adapter for all database operations
     */
    public function getAdapter(): DatabaseAdapter
    {
        if (!isset($this->adapter)) {
            $operationsManager = new DatabaseOperationsManager(
                new TransactionManager(),
                new QueryExecutor($this->cacheManager)
            );
            $this->adapter = new DatabaseAdapter($this->getConnection(), $operationsManager);
        }
        return $this->adapter;
    }

    public function getConnection(): PDO
    {
        if (!isset($this->connection)) {
            $parser = $this->parameterParser ?? new DatabaseParameterParser();
            $parameters = $parser->populateParameters($this->parameters);
            $username = $parameters['user'] ?? '';
            $password = $parameters['pass'] ?? '';

            // Ensure username and password are strings
            $username = is_string($username) ? $username : '';
            $password = is_string($password) ? $password : '';

            $this->connection = $this->connector->connect(
                $parameters,
                $username,
                $password
            );
        }

        return $this->connection;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function prepare(string $query, array $options = []): Statement
    {
        try {
            if ($this->cacheManager->isEnabled()) {
                return $this->cacheManager->prepareWithCache($query, $options, $this->getConnection());
            }

            return $this->cacheManager->prepareDirect($query, $options, $this->getConnection());

        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    // Delegate all interface methods to adapter
    public function beginTransaction(): bool
    {
        return $this->getAdapter()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->getAdapter()->commit();
    }

    public function rollBack(): bool
    {
        return $this->getAdapter()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->getAdapter()->inTransaction();
    }

    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->getAdapter()->setAttribute($attribute, $value);
    }

    public function exec(string $statement): int
    {
        return $this->getAdapter()->exec($statement);
    }

    public function query(
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement {
        return $this->getAdapter()->query($query, $fetchMode, $arg3, $constructorArgs);
    }

    public function lastInsertId(?string $name = null): string
    {
        return $this->getAdapter()->lastInsertId($name);
    }

    public function errorCode(): string|int|false
    {
        return $this->getAdapter()->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->getAdapter()->errorInfo();
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        return $this->getAdapter()->quote($string, $type);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->getAdapter()->getAttribute($attribute);
    }
}
