<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Database\Interface\DatabaseParameterParser;
use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use RuntimeException;
use ReflectionException;
use Exception;
use PDO;
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
    private MigrationManager $manager;
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

        // We'll create the manager in each test to control the setup
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

    private function createManagerWithMockedTableCheck(bool $tableExists = true): void
    {
        // Mock InformationSchema getTables method to return empty array (no existing tables)
        // This way the initializeMigrationTable won't find the migration_history table and will try to create it
        // which is fine for testing since we're not actually executing SQL
        
        // For now, let's try creating a real manager and see what happens
        // The environment variables should provide the database configuration
        try {
            $this->manager = new MigrationManager($this->mockEntityManager, MigrationHistory::class);
        } catch (RuntimeException $e) {
            // If it still fails, we need a different approach
            throw new \Exception("Cannot create MigrationManager for testing: " . $e->getMessage());
        }
    }

    private function setExecutedMigrations(array $executedMigrations): void
    {
        // Use reflection to set executed migrations on the real manager object
        $reflection = new \ReflectionClass($this->manager);
        $property = $reflection->getProperty('executedMigrations');
        $property->setAccessible(true);
        $property->setValue($this->manager, $executedMigrations);
    }

    public function testConstructor(): void
    {
        $this->createManagerWithMockedTableCheck();
        $this->assertInstanceOf(MigrationManager::class, $this->manager);
    }

    public function testRegisterMigration(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration = $this->createMockMigration('202401011200');

        $result = $this->manager->registerMigration($migration);

        $this->assertSame($this->manager, $result); // Test fluent interface
        $this->assertArrayHasKey('202401011200', $this->manager->getMigrations());
    }

    public function testRegisterDuplicateMigration(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration1 = $this->createMockMigration('202401011200');
        $migration2 = $this->createMockMigration('202401011200');

        $this->manager->registerMigration($migration1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration with version 202401011200 is already registered');

        $this->manager->registerMigration($migration2);
    }

    public function testRegisterMigrationsFromDirectory(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Create a mock migration file
        $migrationContent = '<?php
class Migration202401011200 extends MulerTech\Database\Schema\Migration\Migration
{
    public function getVersion(): string { return "202401011200"; }
    public function up(): void {}
    public function down(): void {}
}';

        file_put_contents($this->tempMigrationsDir . '/Migration202401011200.php', $migrationContent);

        $result = $this->manager->registerMigrations($this->tempMigrationsDir);

        $this->assertSame($this->manager, $result);
        $migrations = $this->manager->getMigrations();
        $this->assertArrayHasKey('202401011200', $migrations);
    }

    public function testRegisterMigrationsFromNonExistentDirectory(): void
    {
        $this->createManagerWithMockedTableCheck();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration directory does not exist:');

        $this->manager->registerMigrations('/non/existent/directory');
    }

    public function testIsMigrationExecuted(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Set executed migrations using helper method
        $this->setExecutedMigrations(['202401011200', '202401011201']);

        $executedMigration = $this->createMockMigration('202401011200');
        $pendingMigration = $this->createMockMigration('202401011202');

        $this->assertTrue($this->manager->isMigrationExecuted($executedMigration));
        $this->assertFalse($this->manager->isMigrationExecuted($pendingMigration));
    }

    public function testGetMigrations(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration1 = $this->createMockMigration('202401011202');
        $migration2 = $this->createMockMigration('202401011200');
        $migration3 = $this->createMockMigration('202401011201');

        $this->manager->registerMigration($migration1);
        $this->manager->registerMigration($migration2);
        $this->manager->registerMigration($migration3);

        $migrations = $this->manager->getMigrations();

        // Should be sorted by version
        $versions = array_keys($migrations);
        $this->assertEquals(['202401011200', '202401011201', '202401011202'], $versions);
    }

    public function testGetPendingMigrations(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Set executed migrations using helper method
        $this->setExecutedMigrations(['202401011200']);

        $migration1 = $this->createMockMigration('202401011200'); // Executed
        $migration2 = $this->createMockMigration('202401011201'); // Pending
        $migration3 = $this->createMockMigration('202401011202'); // Pending

        $this->manager->registerMigration($migration1);
        $this->manager->registerMigration($migration2);
        $this->manager->registerMigration($migration3);

        $pendingMigrations = $this->manager->getPendingMigrations();

        $this->assertCount(2, $pendingMigrations);
        $this->assertArrayHasKey('202401011201', $pendingMigrations);
        $this->assertArrayHasKey('202401011202', $pendingMigrations);
        $this->assertArrayNotHasKey('202401011200', $pendingMigrations);
    }

    public function testExecuteMigration(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration = $this->createMockMigration('202401011200');
        $migration->expects($this->once())->method('up');

        $this->mockPdm->expects($this->once())->method('beginTransaction');
        $this->mockPdm->expects($this->once())->method('inTransaction')->willReturn(true);
        $this->mockPdm->expects($this->once())->method('commit');

        $this->manager->registerMigration($migration);
        $this->manager->executeMigration($migration);

        // Check that migration is now marked as executed
        $this->assertTrue($this->manager->isMigrationExecuted($migration));
    }

    public function testExecuteAlreadyExecutedMigration(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Set executed migrations using helper method
        $this->setExecutedMigrations(['202401011200']);

        $migration = $this->createMockMigration('202401011200');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 202401011200 has already been executed');

        $this->manager->executeMigration($migration);
    }

    public function testExecuteMigrationWithException(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration = $this->createMockMigration('202401011200');
        $migration->method('up')->willThrowException(new Exception('Migration failed'));

        $this->mockPdm->expects($this->once())->method('beginTransaction');
        $this->mockPdm->expects($this->once())->method('inTransaction')->willReturn(true);
        $this->mockPdm->expects($this->once())->method('rollBack');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 202401011200 failed: Migration failed');

        $this->manager->registerMigration($migration);
        $this->manager->executeMigration($migration);
    }

    public function testMigrate(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration1 = $this->createMockMigration('202401011200');
        $migration2 = $this->createMockMigration('202401011201');

        $migration1->expects($this->once())->method('up');
        $migration2->expects($this->once())->method('up');

        $this->mockPdm->method('beginTransaction');
        $this->mockPdm->method('inTransaction')->willReturn(true);
        $this->mockPdm->method('commit');

        $this->manager->registerMigration($migration1);
        $this->manager->registerMigration($migration2);

        $executed = $this->manager->migrate();

        $this->assertEquals(2, $executed);
    }

    public function testMigrateWithNoMigrations(): void
    {
        $this->createManagerWithMockedTableCheck();

        $executed = $this->manager->migrate();

        $this->assertEquals(0, $executed);
    }

    public function testRollback(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Set executed migrations using helper method
        $this->setExecutedMigrations(['202401011200', '202401011201']);

        $migration = $this->createMockMigration('202401011201');
        $migration->expects($this->once())->method('down');

        $this->mockPdm->expects($this->once())->method('beginTransaction');
        $this->mockPdm->expects($this->once())->method('commit');

        $this->manager->registerMigration($migration);

        $result = $this->manager->rollback();

        $this->assertTrue($result);
    }

    public function testRollbackWithNoExecutedMigrations(): void
    {
        $this->createManagerWithMockedTableCheck();

        $result = $this->manager->rollback();

        $this->assertFalse($result);
    }

    public function testRollbackMigrationNotFound(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Set executed migrations but don't register the migration
        $this->setExecutedMigrations(['202401011200']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 202401011200 is recorded as executed but cannot be found');

        $this->manager->rollback();
    }

    public function testRollbackWithException(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Set executed migrations using helper method
        $this->setExecutedMigrations(['202401011200']);

        $migration = $this->createMockMigration('202401011200');
        $migration->method('down')->willThrowException(new Exception('Rollback failed'));

        $this->mockPdm->expects($this->once())->method('beginTransaction');
        $this->mockPdm->expects($this->once())->method('rollBack');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration rollback 202401011200 failed: Rollback failed');

        $this->manager->registerMigration($migration);
        $this->manager->rollback();
    }

    public function testRegisterMigrationsSkipsNonMigrationClasses(): void
    {
        $this->createManagerWithMockedTableCheck();

        // Create a file that's not a migration - use different filename to avoid conflicts
        $nonMigrationContent = '<?php
class NotAMigration
{
    public function test() {}
}';

        file_put_contents($this->tempMigrationsDir . '/Migration202401011299.php', $nonMigrationContent);

        $result = $this->manager->registerMigrations($this->tempMigrationsDir);

        $this->assertSame($this->manager, $result);
        $this->assertEmpty($this->manager->getMigrations());
    }

    public function testRegisterMigrationsWithNoFiles(): void
    {
        $this->createManagerWithMockedTableCheck();

        $result = $this->manager->registerMigrations($this->tempMigrationsDir);

        $this->assertSame($this->manager, $result);
        $this->assertEmpty($this->manager->getMigrations());
    }

    private function createMockMigration(string $version): Migration
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('getVersion')->willReturn($version);
        return $migration;
    }

    public function testGetMigrationsReturnsCorrectStructure(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration1 = $this->createMockMigration('202401011200');
        $migration2 = $this->createMockMigration('202401011201');

        $this->manager->registerMigration($migration1);
        $this->manager->registerMigration($migration2);

        $migrations = $this->manager->getMigrations();

        $this->assertIsArray($migrations);
        $this->assertCount(2, $migrations);
        $this->assertArrayHasKey('202401011200', $migrations);
        $this->assertArrayHasKey('202401011201', $migrations);
        $this->assertInstanceOf(Migration::class, $migrations['202401011200']);
        $this->assertInstanceOf(Migration::class, $migrations['202401011201']);
    }

    public function testExecuteMigrationWithNoTransaction(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration = $this->createMockMigration('202401011200');
        $migration->expects($this->once())->method('up');

        $this->mockPdm->expects($this->once())->method('beginTransaction');
        $this->mockPdm->expects($this->once())->method('inTransaction')->willReturn(false);
        $this->mockPdm->expects($this->never())->method('commit');

        $this->manager->registerMigration($migration);
        $this->manager->executeMigration($migration);

        // Check that migration is now marked as executed
        $this->assertTrue($this->manager->isMigrationExecuted($migration));
    }

    public function testMigrateExecutesInOrder(): void
    {
        $this->createManagerWithMockedTableCheck();

        $migration1 = $this->createMockMigration('202401011202'); // Latest
        $migration2 = $this->createMockMigration('202401011200'); // Earliest
        $migration3 = $this->createMockMigration('202401011201'); // Middle

        // Register in random order
        $this->manager->registerMigration($migration1);
        $this->manager->registerMigration($migration2);
        $this->manager->registerMigration($migration3);

        $executionOrder = [];
        $migration1->method('up')->willReturnCallback(function() use (&$executionOrder) {
            $executionOrder[] = '202401011202';
        });
        $migration2->method('up')->willReturnCallback(function() use (&$executionOrder) {
            $executionOrder[] = '202401011200';
        });
        $migration3->method('up')->willReturnCallback(function() use (&$executionOrder) {
            $executionOrder[] = '202401011201';
        });

        $this->mockPdm->method('beginTransaction');
        $this->mockPdm->method('inTransaction')->willReturn(true);
        $this->mockPdm->method('commit');

        $this->manager->migrate();

        // Should execute in chronological order
        $this->assertEquals(['202401011200', '202401011201', '202401011202'], $executionOrder);
    }
}