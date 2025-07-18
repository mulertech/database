<?php

namespace MulerTech\Database\Tests\PhpInterface;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Database\Driver\Driver;
use MulerTech\Database\Database\Interface\ConnectorInterface;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\Interface\Statement;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PhpDatabaseManager with cache functionality
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PhpDatabaseManagerCacheTest extends TestCase
{
    /**
     * Nettoyage après chaque test pour garantir l'isolation du cache.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        \MulerTech\Database\Core\Cache\CacheFactory::reset();
    }

    /**
     * @return void
     */
    public function testStatementCachingWithRealConnection(): void
    {
        // Skip if no database is available
        if (!getenv('DATABASE_HOST')) {
            $this->markTestSkipped('Database connection not configured');
        }

        // Create a real instance with caching enabled
        $dbManager = new PhpDatabaseManager(
            $this->createConnector(),
            [],
            enableStatementCache: true,
            cacheConfig: new CacheConfig(
                                      maxSize: 10,
                                      ttl: 0,
                                      enableStats: true,
                                      evictionPolicy: 'lfu'
                                  )
        );

        // Verify cache is enabled
        $stats = $dbManager->getStatementCacheStats();
        $this->assertTrue($stats['enabled']);

        // Create test table
        try {
            $dbManager->exec('DROP TABLE IF EXISTS cache_test');
            $dbManager->exec('CREATE TABLE cache_test (id INT PRIMARY KEY, name VARCHAR(50))');

            // First prepare - should miss cache
            $stmt1 = $dbManager->prepare('SELECT * FROM cache_test WHERE id = ?');
            $this->assertInstanceOf(Statement::class, $stmt1);

            // Second prepare - should hit cache
            $stmt2 = $dbManager->prepare('SELECT * FROM cache_test WHERE id = ?');
            $this->assertInstanceOf(Statement::class, $stmt2);

            // Check stats
            $stats = $dbManager->getStatementCacheStats();
            $this->assertEquals(1, $stats['cache_stats']['hits']);
            $this->assertEquals(1, $stats['cache_stats']['misses']);
            $this->assertEquals(1, $stats['unique_statements']);

            // Clean up
            $dbManager->exec('DROP TABLE cache_test');
        } catch (\Exception $e) {
            $this->fail('Test failed: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testCacheInvalidation(): void
    {
        if (!getenv('DATABASE_HOST')) {
            $this->markTestSkipped('Database connection not configured');
        }

        $dbManager = $this->createTestDatabaseManager();

        try {
            $dbManager->exec('DROP TABLE IF EXISTS users');
            $dbManager->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(50))');

            // Prepare a statement for users table
            $stmt1 = $dbManager->prepare('SELECT * FROM users WHERE id = ?');

            // This should use cache
            $stmt2 = $dbManager->prepare('SELECT * FROM users WHERE id = ?');

            $statsBefore = $dbManager->getStatementCacheStats();
            $this->assertEquals(1, $statsBefore['cache_stats']['hits']);

            // Invalidate users table
            $dbManager->invalidateTableStatements('users');

            // This should miss cache after invalidation
            $stmt3 = $dbManager->prepare('SELECT * FROM users WHERE id = ?');

            $statsAfter = $dbManager->getStatementCacheStats();
            // Should have one more miss after invalidation
            $this->assertEquals(2, $statsAfter['cache_stats']['misses']);

            // Clean up
            $dbManager->exec('DROP TABLE users');
        } catch (\Exception $e) {
            $this->fail('Test failed: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testClearCache(): void
    {
        if (!getenv('DATABASE_HOST')) {
            $this->markTestSkipped('Database connection not configured');
        }

        $dbManager = $this->createTestDatabaseManager();

        try {
            // Prepare some statements
            $dbManager->prepare('SELECT 1');
            $dbManager->prepare('SELECT 2');
            $dbManager->prepare('SELECT 3');

            $statsBefore = $dbManager->getStatementCacheStats();
            $this->assertGreaterThan(0, $statsBefore['cache_stats']['size']);

            // Clear cache
            $dbManager->clearStatementCache();

            $statsAfter = $dbManager->getStatementCacheStats();
            $this->assertEquals(0, $statsAfter['cache_stats']['size']);
            $this->assertEquals(0, $statsAfter['total_statements_prepared']);
        } catch (\Exception $e) {
            $this->fail('Test failed: ' . $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testDisabledCache(): void
    {
        if (!getenv('DATABASE_HOST')) {
            $this->markTestSkipped('Database connection not configured');
        }

        $parameters = [
            'host' => getenv('DATABASE_HOST') ?: 'localhost',
            'port' => getenv('DATABASE_PORT') ?: 3306,
            'dbname' => getenv('DATABASE_NAME') ?: 'test',
            'user' => getenv('DATABASE_USER') ?: 'root',
            'pass' => getenv('DATABASE_PASS') ?: '',
        ];

        $dbManager = new PhpDatabaseManager(
                                  $this->createConnector(),
                                  $parameters,
            enableStatementCache: false
        );

        $stats = $dbManager->getStatementCacheStats();
        $this->assertFalse($stats['enabled']);

        // Prepare statements - should not be cached
        $stmt1 = $dbManager->prepare('SELECT 1');
        $stmt2 = $dbManager->prepare('SELECT 1');

        // Stats should still show disabled
        $stats = $dbManager->getStatementCacheStats();
        $this->assertFalse($stats['enabled']);
    }

    /**
     * @return void
     */
    public function testEvictionPolicy(): void
    {
        if (!getenv('DATABASE_HOST')) {
            $this->markTestSkipped('Database connection not configured');
        }

        // Create manager with small cache
        $dbManager = new PhpDatabaseManager(
            $this->createConnector(),
            [],
            enableStatementCache: true,
            cacheConfig: new CacheConfig(
                                      maxSize: 3,
                                      ttl: 0,
                                      enableStats: true,
                                      evictionPolicy: 'lfu'
                                  )
        );

        // Prepare 5 different statements (more than cache size)
        for ($i = 1; $i <= 5; $i++) {
            $dbManager->prepare("SELECT $i");
        }

        $stats = $dbManager->getStatementCacheStats();

        // Cache size should not exceed max size
        $this->assertLessThanOrEqual(3, $stats['cache_stats']['size']);

        // Should have evictions
        $this->assertGreaterThan(0, $stats['cache_stats']['evictions']);
    }

    /**
     * @return PhpDatabaseManager
     */
    private function createTestDatabaseManager(): PhpDatabaseManager
    {
        return new PhpDatabaseManager(
            $this->createConnector(),
            [],
            enableStatementCache: true,
            cacheConfig: new CacheConfig(
                                      maxSize: 10,
                                      ttl: 0,
                                      enableStats: true,
                                      evictionPolicy: 'lfu'
                                  )
        );
    }

    /**
     * @return ConnectorInterface
     */
    private function createConnector(): ConnectorInterface
    {
        // Use the real PdoConnector with appropriate driver
        return new PdoConnector(new Driver());
    }
}