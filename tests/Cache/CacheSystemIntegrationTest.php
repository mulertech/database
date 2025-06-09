<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Cache;

use MulerTech\Database\Cache\CacheConfig;
use MulerTech\Database\Cache\CacheFactory;
use MulerTech\Database\Cache\CacheManager;
use MulerTech\Database\ORM\EntityHydrator;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Query\QueryCompiler;
use MulerTech\Database\Query\SelectBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the complete cache system
 * @package MulerTech\Database\Tests\Integration
 * @author Sébastien Muler
 */
class CacheSystemIntegrationTest extends TestCase
{
    /**
     * @var CacheManager
     */
    private CacheManager $cacheManager;

    /**
     * @var PhpDatabaseManager
     */
    private PhpDatabaseManager $dbManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        // Reset cache system
        CacheFactory::reset();
        CacheManager::reset();

        // Initialize cache system with test configuration
        $config = [
            'max_size' => 100,
            'ttl' => 60,
            'enable_stats' => true,
            'eviction_policy' => 'lru',
        ];

        $bootstrap = require __DIR__ . '/../../config/cache-bootstrap.php';
        $this->cacheManager = $bootstrap($config);

        // Create database manager with cache
        $this->dbManager = $this->createDatabaseManager();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        // Clear all caches
        $this->cacheManager->clearAll();
    }

    /**
     * Test statement caching
     * @return void
     */
    public function testStatementCaching(): void
    {
        $query = 'SELECT * FROM users WHERE id = ? AND status = ?';

        // First execution - cache miss
        $stmt1 = $this->dbManager->prepare($query);
        $this->assertNotNull($stmt1);

        // Second execution - cache hit
        $stmt2 = $this->dbManager->prepare($query);
        $this->assertNotNull($stmt2);

        // Check stats
        $stats = $this->dbManager->getStatementCacheStats();
        $this->assertEquals(1, $stats['cache_stats']['hits']);
        $this->assertEquals(1, $stats['cache_stats']['misses']);
        $this->assertEquals(1, $stats['unique_statements']);
    }

    /**
     * Test query compilation caching
     * @return void
     */
    public function testQueryCompilationCaching(): void
    {
        $compiler = new QueryCompiler();

        // Create a query builder
        $builder = new SelectBuilder();
        $builder->select('id', 'name', 'email')
            ->from('users')
            ->where('status = :status')
            ->orderBy('created_at', 'DESC')
            ->limit(10);

        // First compilation - cache miss
        $sql1 = $compiler->compile($builder);
        $this->assertNotEmpty($sql1);

        // Second compilation - cache hit
        $sql2 = $compiler->compile($builder);
        $this->assertEquals($sql1, $sql2);

        // Check stats
        $stats = $compiler->getCacheStats();
        $this->assertGreaterThan(0, $stats['hit_rates']['SELECT']);
    }

    /**
     * Test entity hydration caching
     * @return void
     */
    public function testEntityHydrationCaching(): void
    {
        $hydrator = new EntityHydrator();

        // Warm up cache for test entity
        $hydrator->warmUpCache(TestEntity::class);

        $data = [
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'created_at' => '2024-01-01 00:00:00',
        ];

        // First hydration
        $entity1 = $hydrator->hydrate($data, TestEntity::class);
        $this->assertInstanceOf(TestEntity::class, $entity1);

        // Second hydration - should use cached metadata
        $entity2 = $hydrator->hydrate($data, TestEntity::class);
        $this->assertInstanceOf(TestEntity::class, $entity2);

        // Check cache stats
        $stats = $hydrator->getCacheStats();
        $this->assertGreaterThan(0, $stats['metadata_cache']['size']);
    }

    /**
     * Test cache invalidation
     * @return void
     */
    public function testCacheInvalidation(): void
    {
        // Prepare some cached data
        $query = 'SELECT * FROM products WHERE category_id = ?';
        $stmt = $this->dbManager->prepare($query);

        // Compile a query that involves products table
        $compiler = new QueryCompiler();
        $builder = new SelectBuilder();
        $builder->select('*')->from('products')->where('price > 100');
        $sql = $compiler->compile($builder);

        // Now invalidate products table
        $this->cacheManager->invalidateTable('products');

        // The compiled query cache should be invalidated
        // Next compilation should be a miss
        $sql2 = $compiler->compile($builder);
        $this->assertEquals($sql, $sql2); // Same result

        // But stats should show the invalidation worked
        $stats = $compiler->getCacheStats();
        $this->assertGreaterThan(1, $stats['compilation_stats']['SELECT'] ?? 0);
    }

    /**
     * Test cache health monitoring
     * @return void
     */
    public function testCacheHealthMonitoring(): void
    {
        // Simulate various cache operations
        for ($i = 0; $i < 50; $i++) {
            $this->dbManager->prepare("SELECT * FROM table_$i WHERE id = ?");
        }

        // Get health check
        $health = $this->cacheManager->getHealthCheck();

        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('issues', $health);
        $this->assertArrayHasKey('recommendations', $health);

        // Status should be healthy or warning (not critical)
        $this->assertContains($health['status'], ['healthy', 'warning']);
    }

    /**
     * Test cache warming
     * @return void
     */
    public function testCacheWarming(): void
    {
        // Clear all caches first
        $this->cacheManager->clearAll();

        // Warm up specific caches
        $this->cacheManager->warmUp('metadata');

        // Create entity hydrator and warm it up
        $hydrator = new EntityHydrator();
        $hydrator->warmUpCache(TestEntity::class);

        // Check that metadata cache has entries
        $metadataCache = $this->cacheManager->getCache('metadata');
        $this->assertNotNull($metadataCache);

        // Verify warming worked
        $stats = $hydrator->getCacheStats();
        $this->assertGreaterThan(0, $stats['metadata_cache']['size']);
    }

    /**
     * Test cache size limits and eviction
     * @return void
     */
    public function testCacheSizeLimitsAndEviction(): void
    {
        // Create a small cache for testing eviction
        $smallCache = CacheFactory::createMemoryCache('test_eviction', new CacheConfig(
            maxSize: 5,
            ttl: 0,
            enableStats: true,
            evictionPolicy: 'lru'
        ));

        // Fill cache beyond capacity
        for ($i = 0; $i < 10; $i++) {
            $smallCache->set("key_$i", "value_$i");
        }

        // Check that size is limited
        $stats = $smallCache->getStats();
        $this->assertEquals(5, $stats['size']);
        $this->assertGreaterThan(0, $stats['evictions']);

        // Verify LRU eviction worked correctly
        $this->assertFalse($smallCache->has('key_0')); // Should be evicted
        $this->assertTrue($smallCache->has('key_9')); // Should be present
    }

    /**
     * Create a database manager for testing
     *
     * @param bool $enableCache
     * @return PhpDatabaseManager
     */
    private function createDatabaseManager(bool $enableCache = true): PhpDatabaseManager
    {
        $connector = $this->createMock(\MulerTech\Database\PhpInterface\ConnectorInterface::class);
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('getAttribute')->willReturn('Connected');
        $connector->method('connect')->willReturn($pdo);

        return new PhpDatabaseManager(
            $connector,
            ['host' => 'test', 'user' => 'test', 'pass' => 'test', 'dbname' => 'test'],
            $enableCache
        );
    }
}

/**
 * Test entity for hydration tests
 */
class TestEntity
{
    private int $id;
    private string $name;
    private string $email;
    private \DateTime $createdAt;

    public function setId(int $id): void { $this->id = $id; }
    public function setName(string $name): void { $this->name = $name; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function setCreatedAt(\DateTime $createdAt): void { $this->createdAt = $createdAt; }

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getCreatedAt(): \DateTime { return $this->createdAt; }
}