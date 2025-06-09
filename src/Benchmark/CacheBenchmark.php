<?php

declare(strict_types=1);

namespace MulerTech\Database\Benchmark;

use MulerTech\Database\Cache\CacheConfig;
use MulerTech\Database\Cache\CacheFactory;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\ORM\EntityHydrator;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Tests\Files\Entity\User;

/**
 * Benchmark script to measure cache system performance improvements
 * @package MulerTech\Database\Benchmark
 * @author Sébastien Muler
 */
class CacheBenchmark
{
    /**
     * @var int
     */
    private readonly int $iterations;

    /**
     * @var array<string, array<string, float|array<string, float|int>>>
     */
    private array $results = [];

    /**
     * @param int $iterations
     */
    public function __construct(int $iterations = 1000)
    {
        $this->iterations = $iterations;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        echo "=== MulerTech Database Cache Benchmark ===\n";
        echo "Iterations: {$this->iterations}\n\n";

        // Run benchmarks
        $this->benchmarkPreparedStatements();
        $this->benchmarkEntityHydration();
        $this->benchmarkQueryCompilation();
        $this->benchmarkCacheEviction();

        // Display results
        $this->displayResults();
    }

    /**
     * @return void
     */
    private function benchmarkPreparedStatements(): void
    {
        echo "1. Benchmarking Prepared Statements...\n";

        $parameters = [PhpDatabaseManager::DATABASE_URL => 'mysql://user:password@db:3306/db?serverVersion=5.7'];

        // Create users_test table for testing
        $dbManager = new PhpDatabaseManager(new PdoConnector(new Driver()), $parameters, false);
        $createTableQuery = 'CREATE TABLE IF NOT EXISTS users_test ('
            . 'id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, '
            . 'username VARCHAR(255), '
            . 'size INT, '
            . 'unit_id INT UNSIGNED, '
            . 'manager INT UNSIGNED)';
        $dbManager->exec($createTableQuery);

        // Insert test data
        $insertQuery = 'INSERT INTO users_test (username, size, unit_id, manager) VALUES (?, ?, ?, ?)';
        $stmt = $dbManager->prepare($insertQuery);
        for ($i = 0; $i < $this->iterations; $i++) {
            $stmt->execute(['user_' . $i, rand(1, 100), rand(1, 10), rand(1, 10)]);
        }

        // Without cache
        $dbManagerNoCache = new PhpDatabaseManager(
            new PdoConnector(new Driver()),
            $parameters,
            false
        );
        $startTime = microtime(true);

        for ($i = 0; $i < $this->iterations; $i++) {
            $query = 'SELECT * FROM users_test WHERE id = ?';
            $dbManagerNoCache->prepare($query);
        }

        $timeNoCache = microtime(true) - $startTime;

        // With cache
        $dbManagerWithCache = new PhpDatabaseManager(
            new PdoConnector(new Driver()),
            $parameters,
            true
        );
        $startTimeWithCache = microtime(true);

        for ($i = 0; $i < $this->iterations; $i++) {
            $query = 'SELECT * FROM users_test WHERE id = ?';
            $dbManagerWithCache->prepare($query);
        }

        $timeWithCache = microtime(true) - $startTimeWithCache;

        $this->results['prepared_statements'] = [
            'without_cache' => $timeNoCache,
            'with_cache' => $timeWithCache,
            'improvement' => (($timeNoCache - $timeWithCache) / $timeNoCache) * 100,
        ];

        $stats = $dbManagerWithCache->getStatementCacheStats();
        echo "  Cache stats: Hit rate = " . ($stats['cache_stats']['hitRate'] * 100) . "%\n\n";
    }

    /**
     * @return void
     */
    private function benchmarkEntityHydration(): void
    {
        echo "2. Benchmarking Entity Hydration...\n";

        $entitiesPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';

        // Without cache
        $hydratorNoCache = new EntityHydrator(new DbMapping($entitiesPath));
        $startTime = microtime(true);

        for ($i = 1; $i < $this->iterations; $i++) {
            $testData = $this->generateEntityData($i);
            $hydratorNoCache->hydrate($testData, User::class);
        }

        $timeNoCache = microtime(true) - $startTime;

        // With cache
        $hydratorWithCache = new EntityHydrator(new DbMapping($entitiesPath));
        // Warm up cache
        $hydratorWithCache->warmUpCache(User::class);

        $startTimeWithCache = microtime(true);

        for ($i = 1; $i < $this->iterations; $i++) {
            $testData = $this->generateEntityData($i);
            $hydratorWithCache->hydrate($testData, User::class);
        }

        $timeWithCache = microtime(true) - $startTimeWithCache;

        $this->results['entity_hydration'] = [
            'without_cache' => $timeNoCache,
            'with_cache' => $timeWithCache,
            'improvement' => (($timeNoCache - $timeWithCache) / $timeNoCache) * 100,
        ];

        $stats = $hydratorWithCache->getCacheStats();
        echo "  Cached properties: " . $stats['total_cached_properties'] . "\n\n";
    }

    /**
     * @return void
     */
    private function benchmarkQueryCompilation(): void
    {
        echo "3. Benchmarking Query Compilation...\n";

        // Create different query patterns
        $queries = [];
        for ($i = 0; $i < 100; $i++) {
            $queries[] = $this->generateRandomQuery($i);
        }

        $cache = CacheFactory::createResultSetCache('benchmark_queries');

        // Without cache
        $startTime = microtime(true);

        for ($i = 0; $i < $this->iterations; $i++) {
            $query = $queries[$i % 100];
            // Simulate query compilation
            $compiled = $this->compileQuery($query);
        }

        $timeNoCache = microtime(true) - $startTime;

        // With cache
        $startTimeWithCache = microtime(true);

        for ($i = 0; $i < $this->iterations; $i++) {
            $query = $queries[$i % 100];
            $cacheKey = 'query:' . md5($query);

            $cached = $cache->get($cacheKey);
            if ($cached === null) {
                $compiled = $this->compileQuery($query);
                $cache->set($cacheKey, $compiled);
            }
        }

        $timeWithCache = microtime(true) - $startTimeWithCache;

        $this->results['query_compilation'] = [
            'without_cache' => $timeNoCache,
            'with_cache' => $timeWithCache,
            'improvement' => (($timeNoCache - $timeWithCache) / $timeNoCache) * 100,
        ];
    }

    /**
     * @return void
     */
    private function benchmarkCacheEviction(): void
    {
        echo "4. Benchmarking Cache Eviction Policies...\n";

        $testData = range(1, $this->iterations * 2);
        $cacheSize = 100;

        // Test each eviction policy
        $policies = ['lru', 'lfu', 'fifo'];
        $results = [];

        foreach ($policies as $policy) {
            $config = new CacheConfig(
                maxSize: $cacheSize,
                ttl: 0,
                enableStats: true,
                evictionPolicy: $policy
            );

            $cache = CacheFactory::createMemoryCache('eviction_test_' . $policy, $config);

            $startTime = microtime(true);
            $evictions = 0;

            // Fill cache beyond capacity
            foreach ($testData as $value) {
                $cache->set('key_' . $value, $value);
            }

            $stats = $cache->getStats();
            $evictions = $stats['evictions'];
            $time = microtime(true) - $startTime;

            $results[$policy] = [
                'time' => $time,
                'evictions' => $evictions,
                'final_size' => $stats['size'],
            ];
        }

        $this->results['eviction_policies'] = $results;
    }

    /**
     * @return void
     */
    private function displayResults(): void
    {
        echo "\n=== BENCHMARK RESULTS ===\n\n";

        // Prepared Statements
        echo "1. Prepared Statements:\n";
        $ps = $this->results['prepared_statements'];
        echo "   Without cache: " . number_format((float)$ps['without_cache'], 4) . "s\n";
        echo "   With cache:    " . number_format((float)$ps['with_cache'], 4) . "s\n";
        echo "   Improvement:   " . number_format((float)$ps['improvement'], 1) . "%\n\n";

        // Entity Hydration
        echo "2. Entity Hydration:\n";
        $eh = $this->results['entity_hydration'];
        echo "   Without cache: " . number_format((float)$eh['without_cache'], 4) . "s\n";
        echo "   With cache:    " . number_format((float)$eh['with_cache'], 4) . "s\n";
        echo "   Improvement:   " . number_format((float)$eh['improvement'], 1) . "%\n\n";

        // Query Compilation
        echo "3. Query Compilation:\n";
        $qc = $this->results['query_compilation'];
        echo "   Without cache: " . number_format((float)$qc['without_cache'], 4) . "s\n";
        echo "   With cache:    " . number_format((float)$qc['with_cache'], 4) . "s\n";
        echo "   Improvement:   " . number_format((float)$qc['improvement'], 1) . "%\n\n";

        // Eviction Policies
        echo "4. Eviction Policies Performance:\n";
        foreach ($this->results['eviction_policies'] as $policy => $data) {
            if (is_array($data)) {
                echo "   " . strtoupper($policy) . ":\n";
                echo "     Time:       " . number_format((float)$data['time'], 4) . "s\n";
                echo "     Evictions:  " . (isset($data['evictions']) ? $data['evictions'] : 'N/A') . "\n";
                echo "     Final size: " . (isset($data['final_size']) ? $data['final_size'] : 'N/A') . "\n";
            } else {
                // If $data is a float or int, it means it's a single value
                echo "   " . strtoupper($policy) . ": " . $data . "\n";
            }
        }

        echo "\n=== SUMMARY ===\n";
        $improvements = [
            isset($ps['improvement']) ? (float)$ps['improvement'] : 0.0,
            isset($eh['improvement']) ? (float)$eh['improvement'] : 0.0,
            isset($qc['improvement']) ? (float)$qc['improvement'] : 0.0,
        ];
        $avgImprovement = array_sum($improvements) / count($improvements);
        echo "Average Performance Improvement: " . number_format($avgImprovement, 1) . "%\n";

        if ($avgImprovement > 50) {
            echo "🎉 Excellent! The cache system provides significant performance gains.\n";
        } elseif ($avgImprovement > 30) {
            echo "✅ Good! The cache system provides noticeable performance improvements.\n";
        } else {
            echo "⚠️  Moderate improvements. Consider tuning cache configuration.\n";
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateEntityData(int $id): array
    {
        return [
            'id' => $id,
            'username' => 'test_user_' . $id,
            'size' => rand(1, 200),
            'unit_id' => rand(0, 10),
        ];
    }

    /**
     * @param int $index
     * @return string
     */
    private function generateRandomQuery(int $index): string
    {
        $tables = ['users', 'posts', 'comments', 'orders', 'products'];
        $table = $tables[$index % count($tables)];

        $conditions = [
            'id = ?',
            'status = ? AND created_at > ?',
            'name LIKE ? AND active = ?',
            'price BETWEEN ? AND ?',
            'category_id IN (?, ?, ?)',
        ];

        $condition = $conditions[$index % count($conditions)];

        return "SELECT * FROM {$table} WHERE {$condition}";
    }

    /**
     * @param string $query
     * @return string
     */
    private function compileQuery(string $query): string
    {
        // Simulate query compilation with some processing
        usleep(100); // 0.1ms to simulate compilation work
        return str_replace('?', ':param', $query);
    }
}


// Run benchmark if executed directly : just enter the number of iterations as a command line argument
// e.g. `php CacheBenchmark.php 1000`
if (PHP_SAPI === 'cli' && basename($_SERVER['argv'][0] ?? '') === basename(__FILE__)) {
    // Include necessary files
    include_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    $iterations = (int) ($_SERVER['argv'][1] ?? 1000);
    $benchmark = new CacheBenchmark($iterations);
    $benchmark->run();
}
