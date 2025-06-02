<?php

declare(strict_types=1);

namespace MulerTech\Database\Connection;

use PDOStatement;

/**
 * Cache for prepared statements with LRU eviction
 *
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
class PreparedStatementCache
{
    /** @var array<string, PDOStatement> */
    private array $statements = [];

    /** @var array<string, int> */
    private array $accessOrder = [];

    private int $accessCounter = 0;

    /**
     * @param int $maxSize Maximum number of cached statements
     */
    public function __construct(
        private readonly int $maxSize = 1000
    ) {
    }

    /**
     * @param string $sql SQL query string
     * @return PDOStatement|null
     */
    public function get(string $sql): ?PDOStatement
    {
        $key = $this->generateKey($sql);

        if (!isset($this->statements[$key])) {
            return null;
        }

        // Update access order for LRU
        $this->accessOrder[$key] = ++$this->accessCounter;

        return $this->statements[$key];
    }

    /**
     * @param string $sql SQL query string
     * @param PDOStatement $statement Prepared statement
     * @return void
     */
    public function set(string $sql, PDOStatement $statement): void
    {
        $key = $this->generateKey($sql);

        // Evict least recently used if at capacity
        if (count($this->statements) >= $this->maxSize && !isset($this->statements[$key])) {
            $this->evictLru();
        }

        $this->statements[$key] = $statement;
        $this->accessOrder[$key] = ++$this->accessCounter;
    }

    /**
     * @param string $sql SQL query string
     * @return bool
     */
    public function has(string $sql): bool
    {
        return isset($this->statements[$this->generateKey($sql)]);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->statements = [];
        $this->accessOrder = [];
        $this->accessCounter = 0;
    }

    /**
     * @return array{hits: int, misses: int, size: int, maxSize: int}
     */
    public function getStats(): array
    {
        static $hits = 0, $misses = 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'size' => count($this->statements),
            'maxSize' => $this->maxSize
        ];
    }

    /**
     * @param string $sql SQL query string
     * @return string
     */
    private function generateKey(string $sql): string
    {
        return md5(trim($sql));
    }

    /**
     * @return void
     */
    private function evictLru(): void
    {
        if (empty($this->accessOrder)) {
            return;
        }

        $lruKey = array_key_first($this->accessOrder);
        $minAccess = $this->accessOrder[$lruKey];

        foreach ($this->accessOrder as $key => $access) {
            if ($access < $minAccess) {
                $lruKey = $key;
                $minAccess = $access;
            }
        }

        unset($this->statements[$lruKey], $this->accessOrder[$lruKey]);
    }
}
