<?php

declare(strict_types=1);

namespace MulerTech\Database\Connection;

use PDO;
use PDOStatement;

/**
 * Main connection manager with factory methods
 *
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
class ConnectionManager
{
    private static ?ConnectionPool $pool = null;

    /**
     * @param ConnectionPool|null $pool Connection pool instance
     * @return void
     */
    public static function setPool(?ConnectionPool $pool): void
    {
        self::$pool = $pool;
    }

    /**
     * @return ConnectionPool
     */
    public static function getPool(): ConnectionPool
    {
        if (self::$pool === null) {
            self::$pool = new ConnectionPool();
        }

        return self::$pool;
    }

    /**
     * @param string $name Connection name
     * @param string $dsn Database DSN
     * @param string $username Database username
     * @param string $password Database password
     * @param array<int|string, mixed> $options PDO options
     * @return void
     */
    public static function addConnection(
        string $name,
        string $dsn,
        string $username,
        string $password,
        array $options = []
    ): void {
        $config = new ConnectionConfig($dsn, $username, $password, $options);
        self::getPool()->addConnection($name, $config);
    }

    /**
     * @param string $name Connection name
     * @return PDO
     */
    public static function getConnection(string $name = 'default'): PDO
    {
        return self::getPool()->getConnection($name);
    }

    /**
     * @param string $sql SQL query
     * @param string $connectionName Connection name
     * @return PDOStatement
     */
    public static function prepare(string $sql, string $connectionName = 'default'): PDOStatement
    {
        return self::getPool()->prepare($sql, $connectionName);
    }
}
