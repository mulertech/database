<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use InvalidArgumentException;
use MulerTech\Database\Core\Cache\CacheConfig;
use PDO;
use PDOException;
use RuntimeException;

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
    public const string DATABASE_URL = 'DATABASE_URL';

    private ?PDO $connection = null;
    private int $transactionLevel = 0;
    private readonly StatementCacheManager $cacheManager;

    /**
     * @param ConnectorInterface $connector
     * @param array<string, mixed> $parameters
     * @param StatementCacheConfig|null $cacheConfig
     * @param DatabaseParameterParserInterface|null $parameterParser
     */
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly array $parameters,
        ?StatementCacheConfig $cacheConfig = null,
        private readonly ?DatabaseParameterParserInterface $parameterParser = null
    ) {
        $config = $cacheConfig ?? new StatementCacheConfig();
        $this->cacheManager = new StatementCacheManager(
            $config->isEnabled(),
            (string)spl_object_id($this),
            $config->getCacheConfig()
        );
    }

    public function getConnection(): PDO
    {
        if (!isset($this->connection)) {
            $parser = $this->parameterParser ?? new DatabaseParameterParser();
            $parameters = $parser->parseParameters($this->parameters);
            $username = $this->ensureString($parameters['user'] ?? '');
            $password = $this->ensureString($parameters['pass'] ?? '');

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
            if ($this->cacheManager->isEnabled()) {
                return $this->prepareWithCache($query, $options);
            }

            return $this->prepareDirect($query, $options);
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    public function query(
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement {
        return QueryExecutorHelper::executeQuery(
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

        $this->invalidateCacheForStatement($statement);
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
     * @param array<int, mixed> $options
     */
    private function prepareWithCache(string $query, array $options = []): Statement
    {
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);
        $cachedStatement = $this->cacheManager->getCachedStatement($cacheKey, $this->getConnection());

        if ($cachedStatement !== null) {
            return new Statement($cachedStatement);
        }

        $statement = $this->prepareDirect($query, $options);
        $this->cacheManager->cacheStatement($cacheKey, $statement->getPdoStatement(), $query);

        return $statement;
    }

    /**
     * @param array<int, mixed> $options
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

    private function ensureString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function invalidateCacheForStatement(string $statement): void
    {
        $tableName = $this->extractTableFromQuery($statement);
        $this->cacheManager->invalidateTableStatements($tableName);
    }

    private function extractTableFromQuery(string $query): string
    {
        $query = strtolower(trim($query));

        if (preg_match('/(?:insert\s+into|update|delete\s+from)\s+([a-z_][a-z0-9_]*)/i', $query, $matches)) {
            return $matches[1];
        }

        if (preg_match('/from\s+([a-z_][a-z0-9_]*)/i', $query, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
