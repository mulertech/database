<?php

declare(strict_types=1);

namespace MulerTech\Database\Connection;

/**
 * Configuration for database connections
 *
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
readonly class ConnectionConfig
{
    /**
     * @param string $dsn Database DSN
     * @param string $username Database username
     * @param string $password Database password
     * @param array<int|string, mixed> $options PDO options
     * @param int $maxIdleTime Maximum idle time in seconds
     * @param bool $persistent Use persistent connections
     */
    public function __construct(
        public string $dsn,
        public string $username,
        public string $password,
        public array $options = [],
        public int $maxIdleTime = 3600,
        public bool $persistent = false
    ) {
    }
}
