<?php

declare(strict_types=1);

namespace MulerTech\Database\Connection;

use MulerTech\Database\Cache\CacheConfig;
use MulerTech\Database\Cache\MemoryCache;
use PDO;
use PDOStatement;

/**
 * Specialized cache for prepared statements
 * @package MulerTech\Database\Connection
 * @author Sébastien Muler
 */
class PreparedStatementCache extends MemoryCache
{
    /**
     * @var array<string, array{query: string, count: int, last_used: int}>
     */
    private array $statementMetrics = [];

    /**
     * @var array<string, float>
     */
    private array $executionTimes = [];

    /**
     * @var PDO|null
     */
    private ?PDO $connection = null;

    /**
     * @param CacheConfig|null $config
     */
    public function __construct(?CacheConfig $config = null)
    {
        parent::__construct($config ?? new CacheConfig(
            maxSize: 100,
            ttl: 0, // No expiration for statements
            enableStats: true,
            evictionPolicy: 'lfu' // Least Frequently Used is ideal for statements
        ));
    }

    /**
     * @param PDO $connection
     * @return void
     */
    public function setConnection(PDO $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return PDOStatement|null
     */
    public function getStatement(string $query, array $options = []): ?PDOStatement
    {
        $key = $this->getStatementKey($query, $options);
        $statement = $this->get($key);

        if ($statement instanceof PDOStatement) {
            $this->updateMetrics($key, $query);
            return $statement;
        }

        return null;
    }

    /**
     * @param string $query
     * @param PDOStatement $statement
     * @param array<int|string, mixed> $options
     * @return void
     */
    public function setStatement(string $query, PDOStatement $statement, array $options = []): void
    {
        $key = $this->getStatementKey($query, $options);

        $this->set($key, $statement);
        $this->tag($key, ['statements', $this->extractTableName($query)]);

        $this->statementMetrics[$key] = [
            'query' => $query,
            'count' => 0,
            'last_used' => time(),
        ];
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return PDOStatement|false
     */
    public function prepareStatement(string $query, array $options = []): PDOStatement|false
    {
        if ($this->connection === null) {
            return false;
        }

        // Check cache first
        $statement = $this->getStatement($query, $options);
        if ($statement !== null) {
            return $statement;
        }

        // Prepare new statement
        $startTime = microtime(true);

        $statement = empty($options)
            ? $this->connection->prepare($query)
            : $this->connection->prepare($query, $options);

        $prepareTime = microtime(true) - $startTime;

        if ($statement !== false) {
            $this->setStatement($query, $statement, $options);
            $this->recordExecutionTime($query, $prepareTime);
        }

        return $statement;
    }

    /**
     * @param string $table
     * @return void
     */
    public function invalidateTableStatements(string $table): void
    {
        $this->invalidateTag(strtolower($table));
    }

    /**
     * @return void
     */
    public function invalidateAllStatements(): void
    {
        $this->invalidateTag('statements');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailedStats(): array
    {
        $baseStats = $this->getStatistics();

        // Sort metrics by usage count
        $sortedMetrics = $this->statementMetrics;
        uasort($sortedMetrics, fn ($a, $b) => $b['count'] <=> $a['count']);

        // Get top 10 most used statements
        $topStatements = array_slice($sortedMetrics, 0, 10, true);

        // Calculate average execution times
        $avgExecutionTimes = [];
        foreach ($this->executionTimes as $query => $totalTime) {
            $key = $this->getStatementKey($query, []);
            if (isset($this->statementMetrics[$key])) {
                $count = $this->statementMetrics[$key]['count'];
                $avgExecutionTimes[$query] = $count > 0 ? $totalTime / $count : 0;
            }
        }

        // Sort by slowest average execution time
        arsort($avgExecutionTimes);
        $slowestQueries = array_slice($avgExecutionTimes, 0, 10, true);

        return [
            'base_stats' => $baseStats,
            'total_unique_statements' => count($this->statementMetrics),
            'total_statement_executions' => array_sum(array_column($this->statementMetrics, 'count')),
            'top_statements' => array_map(fn ($m) => [
                'query' => $this->truncateQuery($m['query']),
                'count' => $m['count'],
                'last_used' => date('Y-m-d H:i:s', $m['last_used']),
            ], $topStatements),
            'slowest_queries' => array_map(fn ($q, $t) => [
                'query' => $this->truncateQuery($q),
                'avg_time_ms' => round($t * 1000, 2),
            ], array_keys($slowestQueries), $slowestQueries),
        ];
    }

    /**
     * @param int $days
     * @return void
     */
    public function cleanupUnusedStatements(int $days = 7): void
    {
        $threshold = time() - ($days * 86400);

        foreach ($this->statementMetrics as $key => $metrics) {
            if ($metrics['last_used'] < $threshold) {
                $this->delete($key);
                unset($this->statementMetrics[$key]);
            }
        }
    }

    /**
     * @param string $query
     * @param array<int|string, mixed> $options
     * @return string
     */
    private function getStatementKey(string $query, array $options): string
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
    private function extractTableName(string $query): string
    {
        $patterns = [
            '/FROM\s+`?(\w+)`?/i',
            '/INSERT\s+INTO\s+`?(\w+)`?/i',
            '/UPDATE\s+`?(\w+)`?/i',
            '/DELETE\s+FROM\s+`?(\w+)`?/i',
            '/INTO\s+`?(\w+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                return strtolower($matches[1]);
            }
        }

        return 'unknown';
    }

    /**
     * @param string $key
     * @param string $query
     * @return void
     */
    private function updateMetrics(string $key, string $query): void
    {
        if (!isset($this->statementMetrics[$key])) {
            $this->statementMetrics[$key] = [
                'query' => $query,
                'count' => 0,
                'last_used' => time(),
            ];
        }

        $this->statementMetrics[$key]['count']++;
        $this->statementMetrics[$key]['last_used'] = time();
    }

    /**
     * @param string $query
     * @param float $time
     * @return void
     */
    private function recordExecutionTime(string $query, float $time): void
    {
        if (!isset($this->executionTimes[$query])) {
            $this->executionTimes[$query] = 0.0;
        }

        $this->executionTimes[$query] += $time;
    }

    /**
     * @param string $query
     * @param int $maxLength
     * @return string
     */
    private function truncateQuery(string $query, int $maxLength = 100): string
    {
        $query = preg_replace('/\s+/', ' ', trim($query));

        if (empty($query)) {
            return '';
        }

        if (strlen($query) <= $maxLength) {
            return $query;
        }

        return substr($query, 0, $maxLength - 3) . '...';
    }
}
