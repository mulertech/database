<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface;

use InvalidArgumentException;
use MulerTech\Database\Connection\ConnectionManager;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Enhanced PhpDatabaseManager with connection pooling and caching
 *
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
class PhpDatabaseManager implements PhpDatabaseInterface
{
    public const string DATABASE_URL = 'DATABASE_URL';

    /** @var array<int|string, mixed> */
    private readonly array $parameters;

    /** @var string */
    private readonly string $connectionName;

    /** @var int */
    private int $transactionLevel = 0;

    /**
     * @param array<string, mixed> $parameters Connection parameters
     * @param string $connectionName Connection pool name
     */
    public function __construct(
        array $parameters,
        string $connectionName = 'default'
    ) {
        $this->parameters = self::populateParameters($parameters);
        $this->connectionName = $connectionName;
        $this->initializeConnection();
    }

    /**
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return ConnectionManager::getConnection($this->connectionName);
    }

    /**
     * @param string $query SQL query
     * @param array<int|string, mixed> $options PDO options
     * @return Statement
     */
    public function prepare(string $query, array $options = []): Statement
    {
        try {
            // Use connection pool's prepared statement cache
            $statement = ConnectionManager::prepare($query, $this->connectionName);
            return new Statement($statement);

        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
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
     * @param int $attribute PDO attribute
     * @param mixed $value Attribute value
     * @return bool
     */
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return $this->getConnection()->setAttribute($attribute, $value);
    }

    /**
     * @param string $statement SQL statement
     * @return int
     */
    public function exec(string $statement): int
    {
        $result = $this->getConnection()->exec($statement);
        if ($result === false) {
            throw new RuntimeException('Class: PhpDatabaseManager, function: exec. The function exec failed.');
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
                    'Class: PhpDatabaseManager, function: query. The query failed. Message: %s. Statement: %s.',
                    $this->getConnection()->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
    }

    /**
     * @param string|null $name Sequence name
     * @return string
     */
    public function lastInsertId(?string $name = null): string
    {
        $result = $this->getConnection()->lastInsertId($name);
        if ($result === false) {
            throw new RuntimeException('Class: PhpDatabaseManager, function: lastInsertId. The function lastInsertId failed.');
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
     * @param int $attribute PDO attribute
     * @return mixed
     */
    public function getAttribute(int $attribute): mixed
    {
        return $this->getConnection()->getAttribute($attribute);
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableDrivers(): array
    {
        return PDO::getAvailableDrivers();
    }

    /**
     * @param string $string String to quote
     * @param int $type Parameter type
     * @return string
     */
    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        return $this->getConnection()->quote($string, $type);
    }

    /**
     * @return array{pool: array<string, mixed>, connection: string, parameters: array<string, mixed>}
     */
    public function getStats(): array
    {
        // Filtrer pour ne garder que les clés string
        $filteredParameters = array_filter(
            $this->parameters,
            fn ($key) => is_string($key) && !in_array($key, ['password', 'pass']),
            ARRAY_FILTER_USE_KEY
        );
        /** @var array<string, mixed> $filteredParameters */
        return [
            'pool' => ConnectionManager::getPool()->getStats(),
            'connection' => $this->connectionName,
            'parameters' => $filteredParameters
        ];
    }

    /**
     * Initialize connection in the pool
     *
     * @return void
     */
    private function initializeConnection(): void
    {
        if (!isset($this->parameters['scheme'])) {
            throw new RuntimeException('Database scheme is required');
        }

        $dsn = $this->parameters['scheme'] . ':';
        $dsnParts = [];

        foreach (['host', 'port', 'dbname', 'unix_socket', 'charset'] as $key) {
            if (isset($this->parameters[$key]) && $this->parameters[$key] !== '') {
                $dsnParts[] = $key . '=' . $this->parameters[$key];
            }
        }

        $dsn .= implode(';', $dsnParts);

        ConnectionManager::addConnection(
            $this->connectionName,
            $dsn,
            $this->parameters['user'] ?? '',
            $this->parameters['pass'] ?? '',
            $this->getDefaultOptions()
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function getDefaultOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }

    /**
     * Parse DATABASE_URL or individual environment variables
     *
     * @param array<string, mixed> $parameters Input parameters
     * @return array<int|string, mixed>
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
            return $parsedParams;
        }

        return self::populateEnvParameters($parameters);
    }

    /**
     * @param array<string, mixed> $parameters Input parameters
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

        // Forcer toutes les clés à être string
        $parameters = array_combine(
            array_map('strval', array_keys($parameters)),
            array_values($parameters)
        );

        /** @var array<string, mixed> $parameters */
        return $parameters;
    }

    /**
     * @param array<string, mixed> $url Parsed URL components
     * @return array<string, mixed>
     */
    private static function decodeUrl(array $url): array
    {
        array_walk_recursive($url, static function (&$urlPart) {
            if (is_string($urlPart)) {
                $urlPart = urldecode($urlPart);
            }
        });
        return $url;
    }
}
