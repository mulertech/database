<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface;

use InvalidArgumentException;
use MulerTech\Database\Cache\CacheConfig;
use MulerTech\Database\Cache\CacheFactory;
use MulerTech\Database\Cache\MemoryCache;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Enhanced PhpDatabaseManager with prepared statement caching
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
class PhpDatabaseManager implements PhpDatabaseInterface
{
    public const string DATABASE_URL = 'DATABASE_URL';

    /**
     * @var PDO|null
     */
    private ?PDO $connection = null;

    /**
     * @var MemoryCache|null
     */
    private ?MemoryCache $statementCache = null;

    /**
     * @var int
     */
    private int $transactionLevel = 0;

    /**
     * @var bool
     */
    private readonly bool $enableStatementCache;

    /**
     * @var array<string, int>
     */
    private array $statementUsageCount = [];

    /**
     * @param ConnectorInterface $connector
     * @param array<string, mixed> $parameters
     * @param bool $enableStatementCache
     * @param CacheConfig|null $cacheConfig
     */
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly array $parameters,
        bool $enableStatementCache = true,
        ?CacheConfig $cacheConfig = null
    ) {
        $this->enableStatementCache = $enableStatementCache;

        // Initialize statement cache
        if ($this->enableStatementCache) {
            $this->statementCache = CacheFactory::createMemoryCache(
                'prepared_statements_' . spl_object_id($this),
                $cacheConfig ?? new CacheConfig(
                    maxSize: 100,
                    ttl: 3600,
                    enableStats: true,
                    evictionPolicy: 'lfu' // Least Frequently Used for statements
                )
            );
        }
    }

    /**
     * @return PDO
     * @throws RuntimeException
     */
    public function getConnection(): PDO
    {
        if (!isset($this->connection)) {
            $parameters = self::populateParameters($this->parameters);
            $this->connection = $this->connector->connect(
                $parameters,
                $parameters['user'],
                $parameters['pass']
            );
        }

        return $this->connection;
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return Statement
     */
    public function prepare(string $query, array $options = []): Statement
    {
        try {
            if ($this->enableStatementCache) {
                return $this->prepareWithCache($query, $options);
            }

            // Fallback to direct preparation
            return $this->prepareDirect($query, $options);

        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return Statement
     */
    private function prepareWithCache(string $query, array $options = []): Statement
    {
        $cacheKey = $this->getStatementCacheKey($query, $options);

        // Track usage for analytics
        $this->statementUsageCount[$cacheKey] = ($this->statementUsageCount[$cacheKey] ?? 0) + 1;

        // Check cache first
        $cachedStatement = $this->statementCache?->get($cacheKey);

        if ($cachedStatement instanceof PDOStatement) {
            // Verify the statement is still valid
            try {
                // Test if connection is still alive
                $this->getConnection()->getAttribute(PDO::ATTR_CONNECTION_STATUS);

                // Return wrapped cached statement
                return new Statement($cachedStatement);
            } catch (PDOException $e) {
                // Connection lost, invalidate cache and reconnect
                $this->statementCache?->delete($cacheKey);
                $this->connection = null;
            }
        }

        // Prepare new statement
        $statement = $this->prepareDirect($query, $options);

        // Cache the PDOStatement (not the wrapper)
        $this->statementCache?->set(
            $cacheKey,
            $statement->getPdoStatement(),
            0 // No TTL, use cache eviction policy
        );

        // Tag for easy invalidation
        $this->statementCache?->tag($cacheKey, ['statements', $this->extractTableFromQuery($query)]);

        return $statement;
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
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
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return string
     */
    private function getStatementCacheKey(string $query, array $options): string
    {
        return sprintf(
            'stmt:%s:%s',
            md5($query),
            md5(serialize($options))
        );
    }

    /**
     * @param string $query
     * @return string
     */
    private function extractTableFromQuery(string $query): string
    {
        // Simple regex to extract main table name
        $patterns = [
            '/FROM\s+`?(\w+)`?/i',
            '/INSERT\s+INTO\s+`?(\w+)`?/i',
            '/UPDATE\s+`?(\w+)`?/i',
            '/DELETE\s+FROM\s+`?(\w+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                return strtolower($matches[1]);
            }
        }

        return 'unknown';
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

        // Nested transaction - just increment level
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

        // Nested transaction - just decrement level
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

        // Invalidate related cached statements
        if ($this->enableStatementCache) {
            $table = $this->extractTableFromQuery($statement);
            if ($table !== 'unknown') {
                $this->statementCache?->invalidateTag($table);
            }
        }

        return $result;
    }

    /**
     * @param string $query SQL query
     * @param int|null $fetchMode Fetch mode
     * @param int|string|object $arg3 Additional argument
     * @param array<int, mixed>|null $constructorArgs Constructor arguments
     * @return Statement
     */
    public function query(
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement {
        $pdo = $this->getConnection();

        if ($fetchMode === null) {
            $result = $pdo->query($query);
        } elseif ($fetchMode === PDO::FETCH_CLASS) {
            $result = $pdo->query(
                $query,
                $fetchMode,
                is_string($arg3) ? $arg3 : '',
                is_array($constructorArgs) ? $constructorArgs : []
            );
        } elseif ($fetchMode === PDO::FETCH_INTO) {
            if (!is_object($arg3)) {
                throw new InvalidArgumentException(
                    'When using FETCH_INTO, the third argument must be an object.'
                );
            }

            $result = $pdo->query($query, $fetchMode, $arg3);
        } else {
            $result = $pdo->query($query, $fetchMode);
        }

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Query failed. Error: %s. Statement: %s',
                    $this->getConnection()->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
    }

    /**
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(?string $name = null): string
    {
        $result = $this->getConnection()->lastInsertId($name);

        if ($result === false) {
            throw new RuntimeException(
                'Failed to get last insert ID'
            );
        }

        return $result;
    }

    /**
     * @return string|int|false
     */
    public function errorCode(): string|int|false
    {
        return $this->getConnection()->errorCode();
    }

    /**
     * @return array<int, string|null>
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
     * @return array<string, mixed>
     */
    public function getStatementCacheStats(): array
    {
        if (!$this->enableStatementCache) {
            return ['enabled' => false];
        }

        $stats = $this->statementCache?->getStatistics();

        // Add usage analytics
        $topStatements = [];
        arsort($this->statementUsageCount);
        $topStatements = array_slice($this->statementUsageCount, 0, 10, true);

        return [
            'enabled' => true,
            'cache_stats' => $stats,
            'total_statements_prepared' => array_sum($this->statementUsageCount),
            'unique_statements' => count($this->statementUsageCount),
            'top_statements' => $topStatements,
        ];
    }

    /**
     * Clear the statement cache
     * @return void
     */
    public function clearStatementCache(): void
    {
        if ($this->enableStatementCache) {
            $this->statementCache?->clear();
            $this->statementUsageCount = [];
        }
    }

    /**
     * Invalidate cached statements for a specific table
     * @param string $table
     * @return void
     */
    public function invalidateTableStatements(string $table): void
    {
        if ($this->enableStatementCache) {
            $this->statementCache?->invalidateTag(strtolower($table));
        }
    }

    /**
     * Parse DATABASE_URL or individual environment variables
     *
     * @param array<string, mixed> $parameters Input parameters
     * @return array<string, mixed>
     */
    public static function populateParameters(array $parameters = []): array
    {
        if (!empty($parameters[self::DATABASE_URL])) {
            $url = $parameters[self::DATABASE_URL];
            $parsedUrl = parse_url($url);
            if ($parsedUrl === false) {
                throw new RuntimeException('Invalid DATABASE_URL format.');
            }

            $parsedParams = self::decodeUrl($parsedUrl);
            if (isset($parsedParams['path'])) {
                $parsedParams['dbname'] = substr($parsedParams['path'], 1);
            }
            if (isset($parsedParams['query'])) {
                parse_str($parsedParams['query'], $parsedQuery);
                $parsedParams = array_merge($parsedParams, $parsedQuery);
            }
            $parsedParams = array_combine(
                array_map('strval', array_keys($parsedParams)),
                array_values($parsedParams)
            );
            /** @var array<string, mixed> $parsedParams */
            return $parsedParams;
        }

        return self::populateEnvParameters($parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private static function populateEnvParameters(array $parameters = []): array
    {
        $envMappings = [
            'DATABASE_SCHEME' => 'scheme',
            'DATABASE_HOST' => 'host',
            'DATABASE_PORT' => 'port',
            'DATABASE_USER' => 'user',
            'DATABASE_PASS' => 'pass',
            'DATABASE_PATH' => 'path',
            'DATABASE_QUERY' => 'query',
            'DATABASE_FRAGMENT' => 'fragment',
        ];

        foreach ($envMappings as $envKey => $paramKey) {
            $value = getenv($envKey);
            if ($value !== false) {
                if ($paramKey === 'port') {
                    $parameters[$paramKey] = (int)$value;
                } else {
                    $parameters[$paramKey] = $value;
                }
            }
        }

        // Special handling for database name from path
        if (isset($parameters['path'])) {
            $parameters['dbname'] = substr($parameters['path'], 1);
        }

        // Parse query string
        if (isset($parameters['query'])) {
            parse_str($parameters['query'], $parsedQuery);
            $parameters = array_merge($parameters, $parsedQuery);
        }

        /** @var array<string, mixed> $parameters */
        return $parameters;
    }

    /**
     * @param array<string, mixed> $url Parsed URL components
     * @return array<string, mixed>
     */
    private static function decodeUrl(array $url): array
    {
        array_walk($url, static function (&$urlPart) {
            if (is_string($urlPart)) {
                $urlPart = urldecode($urlPart);
            }
        });
        return $url;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        // Log cache statistics on destruction if enabled
        if ($this->statementCache !== null && $this->statementCache->getStatistics()['hits'] > 0) {
            // You can log these stats to your monitoring system
            $stats = $this->getStatementCacheStats();
            // Example: error_log('Statement cache stats: ' . json_encode($stats));
        }
    }
}
