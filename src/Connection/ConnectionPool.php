<?php

declare(strict_types=1);

namespace MulerTech\Database\Connection;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Pool of database connections with automatic management
 *
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
class ConnectionPool
{
    /** @var array<string, ConnectionWrapper> */
    private array $connections = [];

    /** @var array<string, ConnectionConfig> */
    private array $configs = [];

    private readonly PreparedStatementCache $statementCache;

    /**
     * @param int $maxConnections Maximum number of connections
     * @param int $statementCacheSize Maximum cached statements
     */
    public function __construct(
        private readonly int $maxConnections = 10,
        int $statementCacheSize = 1000
    ) {
        $this->statementCache = new PreparedStatementCache($statementCacheSize);
    }

    /**
     * @param string $name Connection name
     * @param ConnectionConfig $config Connection configuration
     * @return void
     */
    public function addConnection(string $name, ConnectionConfig $config): void
    {
        $this->configs[$name] = $config;
    }

    /**
     * @param string $name Connection name
     * @return PDO
     * @throws RuntimeException
     */
    public function getConnection(string $name = 'default'): PDO
    {
        if (!isset($this->configs[$name])) {
            throw new RuntimeException("Connection '{$name}' not configured");
        }

        // Return existing connection if available and not expired
        if (isset($this->connections[$name]) && !$this->connections[$name]->isExpired()) {
            return $this->connections[$name]->getPdo();
        }

        // Clean expired connections
        $this->cleanExpiredConnections();

        // Check connection limit
        if (count($this->connections) >= $this->maxConnections) {
            throw new RuntimeException("Maximum connections ({$this->maxConnections}) reached");
        }

        // Create new connection
        $this->connections[$name] = $this->createConnection($this->configs[$name]);

        return $this->connections[$name]->getPdo();
    }

    /**
     * @param string $sql SQL query
     * @param string $connectionName Connection name
     * @return PDOStatement
     */
    public function prepare(string $sql, string $connectionName = 'default'): PDOStatement
    {
        // Try to get from cache first
        $cachedStatement = $this->statementCache->get($sql);
        if ($cachedStatement !== null) {
            return $cachedStatement;
        }

        // Prepare new statement
        $pdo = $this->getConnection($connectionName);
        $statement = $pdo->prepare($sql);

        if ($statement === false) {
            throw new RuntimeException("Failed to prepare statement: {$sql}");
        }

        // Cache the statement
        $this->statementCache->set($sql, $statement);

        return $statement;
    }

    /**
     * @param string $name Connection name
     * @return void
     */
    public function releaseConnection(string $name): void
    {
        unset($this->connections[$name]);
    }

    /**
     * @return void
     */
    public function closeAll(): void
    {
        $this->connections = [];
        $this->statementCache->clear();
    }

    /**
     * @return array{connections: int, configs: int, cache: array<string, mixed>}
     */
    public function getStats(): array
    {
        return [
            'connections' => count($this->connections),
            'configs' => count($this->configs),
            'cache' => $this->statementCache->getStats()
        ];
    }

    /**
     * @param ConnectionConfig $config Connection configuration
     * @return ConnectionWrapper
     * @throws RuntimeException
     */
    private function createConnection(ConnectionConfig $config): ConnectionWrapper
    {
        try {
            $options = $config->options;

            // Add default options
            $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
            $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;

            if ($config->persistent) {
                $options[PDO::ATTR_PERSISTENT] = true;
            }

            $pdo = new PDO($config->dsn, $config->username, $config->password, $options);

            return new ConnectionWrapper($pdo, $config);

        } catch (PDOException $e) {
            throw new RuntimeException("Failed to create connection: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return void
     */
    private function cleanExpiredConnections(): void
    {
        foreach ($this->connections as $name => $wrapper) {
            if ($wrapper->isExpired()) {
                unset($this->connections[$name]);
            }
        }
    }
}
