<?php

declare(strict_types=1);

namespace MulerTech\Database\Connection;

use PDO;

/**
 * Wrapper for PDO connection with metadata
 *
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
class ConnectionWrapper
{
    private int $lastUsed;
    private int $queryCount = 0;

    /**
     * @param PDO $pdo PDO connection instance
     * @param ConnectionConfig $config Connection configuration
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly ConnectionConfig $config
    ) {
        $this->lastUsed = time();
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        $this->lastUsed = time();
        $this->queryCount++;
        return $this->pdo;
    }

    /**
     * @return ConnectionConfig
     */
    public function getConfig(): ConnectionConfig
    {
        return $this->config;
    }

    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        return (time() - $this->lastUsed) > $this->config->maxIdleTime;
    }

    /**
     * @return int
     */
    public function getQueryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * @return int
     */
    public function getLastUsed(): int
    {
        return $this->lastUsed;
    }
}
