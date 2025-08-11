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
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
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
    private EntityManager $realEntityManager;

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

        // Create real EntityManager for integration tests
        $entitiesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $realMetadataCache = new MetadataCache(null, $entitiesPath);
        $realMetadataCache->getEntityMetadata(MigrationHistory::class);
        
        $this->realEntityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $realMetadataCache,
        );
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
        $fileContent = file_get_contents($filePath);
        $this->assertNotFalse($fileContent);
        $this->assertStringContainsString('TestMigration202401011200', $fileContent);
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

    /**
     * Test duplicate migration version registration (integration test)
     */
    public function testRegisterDuplicateMigrationVersionIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);
        
        $migration1 = new Migration202501010001($this->realEntityManager);
        
        // Create a second migration with manually set version to simulate duplicate
        $migration2 = new Migration202501010004($this->realEntityManager);
        
        // Use reflection to override the version to create a duplicate
        $reflection = new \ReflectionClass($migration2);
        $versionProperty = $reflection->getProperty('version');
        $versionProperty->setAccessible(true);
        $versionProperty->setValue($migration2, '20250101-0001'); // Same as migration1
        
        $manager->registerMigration($migration1);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration with version 20250101-0001 is already registered');
        
        $manager->registerMigration($migration2);
    }

    /**
     * Test registering migrations from non-existent directory (integration test)
     */
    public function testRegisterMigrationsFromNonExistentDirectoryIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration directory does not exist');
        
        $manager->registerMigrations('/non/existent/directory');
    }

    /**
     * Test executing already executed migration (integration test)
     */
    public function testExecuteAlreadyExecutedMigrationIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);
        
        $migration = new Migration202501010002($this->realEntityManager);
        $manager->registerMigration($migration);
        
        // Execute the migration first
        $manager->executeMigration($migration);
        
        // Try to execute it again
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 20250101-0002 has already been executed');
        
        $manager->executeMigration($migration);
    }

    /**
     * Test rollback functionality when no migrations executed (integration test)
     */
    public function testRollbackWhenNoMigrationsExecutedIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);
        
        $result = $manager->rollback();
        
        $this->assertFalse($result);
    }

    /**
     * Test rollback success (integration test)
     */
    public function testRollbackSuccessIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);
        
        $migration = new Migration202501010003($this->realEntityManager);
        $manager->registerMigration($migration);
        
        // Execute the migration first
        $manager->executeMigration($migration);
        
        // Verify it was executed
        $this->assertTrue($manager->isMigrationExecuted($migration));
        
        // Rollback
        $result = $manager->rollback();
        
        $this->assertTrue($result);
        $this->assertFalse($manager->isMigrationExecuted($migration));
    }

    /**
     * Test creating migration history table exists (integration test)
     */
    public function testCreateMigrationHistoryTableExistsIntegration(): void
    {
        // Just creating a MigrationManager should create the table
        $manager = new MigrationManager($this->realEntityManager);
        
        // Check that migration_history table exists by directly querying the database
        $sql = "SELECT COUNT(*) FROM migration_history";
        $statement = $this->realEntityManager->getPdm()->prepare($sql);
        $statement->execute();
        
        // If we get here without exception, the table exists
        $result = $statement->fetchColumn();
        $this->assertIsNumeric($result);
    }
}

// Test migration classes for integration tests
class Migration202501010001 extends Migration
{
    public function up(): void
    {
        // Simple test migration
    }

    public function down(): void
    {
        // Simple rollback
    }
}

class Migration202501010004 extends Migration
{
    public function up(): void
    {
        // Migration that will be modified for duplicate test
    }

    public function down(): void
    {
        // Rollback for duplicate test migration
    }
}

class Migration202501010002 extends Migration
{
    public function up(): void
    {
        // Another test migration
    }

    public function down(): void
    {
        // Another rollback
    }
}

class Migration202501010003 extends Migration
{
    public function up(): void
    {
        // Test migration for rollback
    }

    public function down(): void
    {
        // Test rollback
    }
}