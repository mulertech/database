<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Database\Interface\StatementCacheManager;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementCacheManager::class)]
final class StatementCacheManagerTest extends TestCase
{
    private StatementCacheManager $cacheManager;
    private PDO $mockPdo;
    private PDOStatement $mockStatement;
    private string $instanceId;

    protected function setUp(): void
    {
        $this->instanceId = 'test_instance_123';
        $this->mockPdo = $this->createMock(PDO::class);
        $this->mockStatement = $this->createMock(PDOStatement::class);
        
        $cacheConfig = new CacheConfig(
            maxSize: 50,
            ttl: 1800,
            evictionPolicy: 'lfu'
        );
        
        $this->cacheManager = new StatementCacheManager($this->instanceId, $cacheConfig);
    }

    public function testGenerateCacheKey(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL];

        $cacheKey1 = $this->cacheManager->generateCacheKey($query, $options);
        $cacheKey2 = $this->cacheManager->generateCacheKey($query, $options);

        $this->assertEquals($cacheKey1, $cacheKey2);
        $this->assertStringStartsWith('stmt:', $cacheKey1);
    }

    public function testGenerateCacheKeyWithDifferentQueries(): void
    {
        $query1 = 'SELECT * FROM users WHERE id = ?';
        $query2 = 'SELECT * FROM products WHERE id = ?';
        $options = [];

        $cacheKey1 = $this->cacheManager->generateCacheKey($query1, $options);
        $cacheKey2 = $this->cacheManager->generateCacheKey($query2, $options);

        $this->assertNotEquals($cacheKey1, $cacheKey2);
    }

    public function testGenerateCacheKeyWithDifferentOptions(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options1 = [];
        $options2 = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL];

        $cacheKey1 = $this->cacheManager->generateCacheKey($query, $options1);
        $cacheKey2 = $this->cacheManager->generateCacheKey($query, $options2);

        $this->assertNotEquals($cacheKey1, $cacheKey2);
    }

    public function testGetCachedStatementWhenNotCached(): void
    {
        $cacheKey = 'stmt:' . md5('SELECT * FROM users') . ':' . md5(serialize([]));

        $result = $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);

        $this->assertNull($result);
    }

    public function testCacheStatementAndRetrieve(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->mockPdo->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_CONNECTION_STATUS)
            ->willReturn('active');

        // Cache the statement
        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Retrieve the cached statement
        $cachedStatement = $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);

        $this->assertSame($this->mockStatement, $cachedStatement);
    }

    public function testGetCachedStatementWithConnectionLost(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        // Cache the statement first
        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Simulate connection lost
        $this->mockPdo->expects($this->once())
            ->method('getAttribute')
            ->with(PDO::ATTR_CONNECTION_STATUS)
            ->willThrowException(new PDOException('Connection lost'));

        $cachedStatement = $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);

        $this->assertNull($cachedStatement);
    }

    public function testInvalidateTableStatements(): void
    {
        $query1 = 'SELECT * FROM users WHERE id = ?';
        $query2 = 'SELECT * FROM users WHERE name = ?';
        $query3 = 'SELECT * FROM products WHERE id = ?';
        $options = [];

        $cacheKey1 = $this->cacheManager->generateCacheKey($query1, $options);
        $cacheKey2 = $this->cacheManager->generateCacheKey($query2, $options);
        $cacheKey3 = $this->cacheManager->generateCacheKey($query3, $options);

        // Cache all statements
        $this->cacheManager->cacheStatement($cacheKey1, $this->mockStatement, $query1);
        $this->cacheManager->cacheStatement($cacheKey2, $this->mockStatement, $query2);
        $this->cacheManager->cacheStatement($cacheKey3, $this->mockStatement, $query3);

        // Invalidate users table statements
        $this->cacheManager->invalidateTableStatements('users');

        // Mock PDO for connection status check
        $this->mockPdo->method('getAttribute')
            ->with(PDO::ATTR_CONNECTION_STATUS)
            ->willReturn('active');

        // Users statements should be invalidated
        $this->assertNull($this->cacheManager->getCachedStatement($cacheKey1, $this->mockPdo));
        $this->assertNull($this->cacheManager->getCachedStatement($cacheKey2, $this->mockPdo));

        // Products statement should still be cached
        $this->assertSame($this->mockStatement, $this->cacheManager->getCachedStatement($cacheKey3, $this->mockPdo));
    }

    public function testInvalidateTableStatementsWithUnknownTable(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // This should not affect any cached statements
        $this->cacheManager->invalidateTableStatements('unknown');

        $this->mockPdo->method('getAttribute')
            ->with(PDO::ATTR_CONNECTION_STATUS)
            ->willReturn('active');

        // Statement should still be cached
        $this->assertSame($this->mockStatement, $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo));
    }

    public function testCacheStatementExtractsTableFromInsertQuery(): void
    {
        $query = 'INSERT INTO users (name, email) VALUES (?, ?)';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Invalidate users table
        $this->cacheManager->invalidateTableStatements('users');

        $this->mockPdo->method('getAttribute')
            ->willReturn('active');

        // Statement should be invalidated
        $this->assertNull($this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo));
    }

    public function testCacheStatementExtractsTableFromUpdateQuery(): void
    {
        $query = 'UPDATE users SET name = ? WHERE id = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Invalidate users table
        $this->cacheManager->invalidateTableStatements('users');

        $this->mockPdo->method('getAttribute')
            ->willReturn('active');

        // Statement should be invalidated
        $this->assertNull($this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo));
    }

    public function testCacheStatementExtractsTableFromDeleteQuery(): void
    {
        $query = 'DELETE FROM users WHERE id = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Invalidate users table
        $this->cacheManager->invalidateTableStatements('users');

        $this->mockPdo->method('getAttribute')
            ->willReturn('active');

        // Statement should be invalidated
        $this->assertNull($this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo));
    }

    public function testCacheStatementWithComplexQuery(): void
    {
        $query = 'SELECT u.name, p.title FROM users u JOIN posts p ON u.id = p.user_id WHERE u.active = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Should extract 'users' as the main table
        $this->cacheManager->invalidateTableStatements('users');

        $this->mockPdo->method('getAttribute')
            ->willReturn('active');

        // Statement should be invalidated
        $this->assertNull($this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo));
    }

    public function testCacheStatementWithUnrecognizedQuery(): void
    {
        $query = 'SHOW TABLES';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        // Should not invalidate since table is 'unknown'
        $this->cacheManager->invalidateTableStatements('users');

        $this->mockPdo->method('getAttribute')
            ->willReturn('active');

        // Statement should still be cached
        $this->assertSame($this->mockStatement, $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo));
    }

    public function testConstructorWithDefaultCacheConfig(): void
    {
        $manager = new StatementCacheManager('test_instance');

        $this->assertInstanceOf(StatementCacheManager::class, $manager);

        // Test that it works with default config
        $cacheKey = $manager->generateCacheKey('SELECT 1', []);
        $this->assertIsString($cacheKey);
    }

    public function testMultipleUsageTracking(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [];
        $cacheKey = $this->cacheManager->generateCacheKey($query, $options);

        $this->cacheManager->cacheStatement($cacheKey, $this->mockStatement, $query);

        $this->mockPdo->method('getAttribute')
            ->willReturn('active');

        // Access the cached statement multiple times
        $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);
        $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);
        $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);

        // Should still return the cached statement
        $result = $this->cacheManager->getCachedStatement($cacheKey, $this->mockPdo);
        $this->assertSame($this->mockStatement, $result);
    }
}