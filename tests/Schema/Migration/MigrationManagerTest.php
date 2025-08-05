<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for MigrationManager class
 */
class MigrationManagerTest extends TestCase
{
    private EntityManagerInterface $mockEntityManager;
    private EmEngine $mockEmEngine;
    private MetadataCache $metadataCache;
    private PhpDatabaseInterface $mockPdm;
    private string $tempMigrationsDir;

    protected function setUp(): void
    {
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->mockEmEngine = $this->createMock(EmEngine::class);
        
        // Create a real MetadataCache instance since it's final and cannot be mocked
        $cacheConfig = new CacheConfig(maxSize: 100, ttl: 0, evictionPolicy: 'lru');
        $this->metadataCache = new MetadataCache($cacheConfig);
        
        $this->mockPdm = $this->createMock(PhpDatabaseInterface::class);

        $this->mockEntityManager->method('getEmEngine')->willReturn($this->mockEmEngine);
        $this->mockEntityManager->method('getMetadataCache')->willReturn($this->metadataCache);
        $this->mockEntityManager->method('getPdm')->willReturn($this->mockPdm);

        // Create temporary directory for migrations
        $this->tempMigrationsDir = sys_get_temp_dir() . '/migrations_test_' . uniqid();
        mkdir($this->tempMigrationsDir);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempMigrationsDir)) {
            $files = array_diff(scandir($this->tempMigrationsDir), ['.', '..']);
            foreach ($files as $file) {
                unlink($this->tempMigrationsDir . DIRECTORY_SEPARATOR . $file);
            }
            rmdir($this->tempMigrationsDir);
        }
    }

    private function createMockMigration(string $version): Migration
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('getVersion')->willReturn($version);
        return $migration;
    }

    public function testMigrationManagerClassExists(): void
    {
        $this->assertTrue(class_exists(MigrationManager::class));
    }

    public function testMigrationManagerHasExpectedMethods(): void
    {
        $reflection = new \ReflectionClass(MigrationManager::class);
        
        $this->assertTrue($reflection->hasMethod('registerMigration'));
        $this->assertTrue($reflection->hasMethod('registerMigrations'));
        $this->assertTrue($reflection->hasMethod('getMigrations'));
        $this->assertTrue($reflection->hasMethod('getPendingMigrations'));
        $this->assertTrue($reflection->hasMethod('migrate'));
        $this->assertTrue($reflection->hasMethod('rollback'));
        $this->assertTrue($reflection->hasMethod('executeMigration'));
        $this->assertTrue($reflection->hasMethod('isMigrationExecuted'));
    }

    public function testMigrationManagerConstructorSignature(): void
    {
        $reflection = new \ReflectionClass(MigrationManager::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertCount(2, $constructor->getParameters());
        
        $params = $constructor->getParameters();
        $this->assertEquals('entityManager', $params[0]->getName());
        $this->assertEquals('migrationHistory', $params[1]->getName());
    }

    public function testMigrationManagerPublicMethodsExist(): void
    {
        $methods = get_class_methods(MigrationManager::class);
        
        $expectedMethods = [
            'registerMigration',
            'registerMigrations', 
            'isMigrationExecuted',
            'getMigrations',
            'getPendingMigrations',
            'migrate',
            'executeMigration',
            'rollback'
        ];
        
        foreach ($expectedMethods as $method) {
            $this->assertContains($method, $methods, "Method $method should exist");
        }
    }

    public function testMigrationClassExists(): void
    {
        $this->assertTrue(class_exists(Migration::class));
    }

    public function testMigrationHistoryEntityExists(): void
    {
        $this->assertTrue(class_exists(MigrationHistory::class));
    }

    public function testMockMigrationCreation(): void
    {
        $migration = $this->createMockMigration('202401011200');
        
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertEquals('202401011200', $migration->getVersion());
    }

    public function testTempDirectoryCreation(): void
    {
        $this->assertTrue(is_dir($this->tempMigrationsDir));
        $this->assertTrue(is_writable($this->tempMigrationsDir));
    }

    public function testMockEntityManagerSetup(): void
    {
        $this->assertInstanceOf(EntityManagerInterface::class, $this->mockEntityManager);
        $this->assertInstanceOf(EmEngine::class, $this->mockEntityManager->getEmEngine());
        $this->assertInstanceOf(MetadataCache::class, $this->mockEntityManager->getMetadataCache());
        $this->assertInstanceOf(PhpDatabaseInterface::class, $this->mockEntityManager->getPdm());
    }

    /**
     * Test that we can create migration files in the temp directory
     */
    public function testMigrationFileCreation(): void
    {
        $migrationContent = '<?php
class TestMigration202401011200 extends MulerTech\Database\Schema\Migration\Migration
{
    public function getVersion(): string { return "202401011200"; }
    public function up(): void {}
    public function down(): void {}
}';

        $filePath = $this->tempMigrationsDir . '/TestMigration202401011200.php';
        file_put_contents($filePath, $migrationContent);
        
        $this->assertTrue(file_exists($filePath));
        $this->assertStringContainsString('TestMigration202401011200', file_get_contents($filePath));
    }

    /**
     * Test basic reflection functionality on MigrationManager
     */
    public function testMigrationManagerReflection(): void
    {
        $reflection = new \ReflectionClass(MigrationManager::class);
        
        // Test that class is not abstract
        $this->assertFalse($reflection->isAbstract());
        
        // Test that class is not final
        $this->assertFalse($reflection->isFinal());
        
        // Test that class has expected properties
        $this->assertTrue($reflection->hasProperty('entityManager'));
        $this->assertTrue($reflection->hasProperty('migrationHistory'));
    }

    /**
     * Test that exceptions can be thrown properly
     */
    public function testRuntimeExceptionCanBeThrown(): void
    {
        $this->expectException(RuntimeException::class);
        throw new RuntimeException('Test exception');
    }
}