<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use Exception;
use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use MulerTech\Database\Tests\Files\Migrations\Migration202501010001;
use MulerTech\Database\Tests\Files\Migrations\Migration202501010002;
use MulerTech\Database\Tests\Files\Migrations\Migration202501010003;
use MulerTech\Database\Tests\Files\Migrations\Migration202501010004;
use PDO;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for MigrationManager class
 */
class MigrationManagerTest extends TestCase
{
    private string $tempMigrationsDir;
    private EntityManager $realEntityManager;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function setUp(): void
    {
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $mockEmEngine = $this->createMock(EmEngine::class);
        
        // Create a real MetadataRegistry instance since it's final and cannot be mocked
        $metadataRegistry = new MetadataRegistry();
        
        $mockPdm = $this->createMock(PhpDatabaseInterface::class);

        $mockEntityManager->method('getEmEngine')->willReturn($mockEmEngine);
        $mockEntityManager->method('getMetadataRegistry')->willReturn($metadataRegistry);
        $mockEntityManager->method('getPdm')->willReturn($mockPdm);

        // Create temporary directory for migrations
        $this->tempMigrationsDir = sys_get_temp_dir() . '/migrations_test_' . uniqid();
        mkdir($this->tempMigrationsDir);

        // Create real EntityManager for integration tests
        $entitiesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $realMetadataRegistry = new MetadataRegistry($entitiesPath);
        $realMetadataRegistry->getEntityMetadata(MigrationHistory::class);
        
        $this->realEntityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $realMetadataRegistry,
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
        
        $this->assertFileExists($filePath);
        $fileContent = file_get_contents($filePath);
        $this->assertNotFalse($fileContent);
        $this->assertStringContainsString('TestMigration202401011200', $fileContent);
    }

    /**
     * Test basic reflection functionality on MigrationManager
     */
    public function testMigrationManagerReflection(): void
    {
        $reflection = new ReflectionClass(MigrationManager::class);
        
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
     * @throws ReflectionException
     */
    public function testRegisterDuplicateMigrationVersionIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);

        $migration1 = new Migration202501010001($this->realEntityManager);

        // Create a second migration with manually set version to simulate duplicate
        $migration2 = new Migration202501010004($this->realEntityManager);

        // Use reflection to override the version to create a duplicate
        $reflection = new ReflectionClass($migration2);
        $versionProperty = $reflection->getProperty('version');
        $versionProperty->setValue($migration2, '20250101-0001'); // Same as migration1

        $manager->registerMigration($migration1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration with version 20250101-0001 is already registered');

        $manager->registerMigration($migration2);
    }

    /**
     * Test registering migrations from non-existent directory (integration test)
     * @throws ReflectionException
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
     * @throws ReflectionException
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
     * @throws ReflectionException
     */
    public function testRollbackWhenNoMigrationsExecutedIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);

        $result = $manager->rollback();

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testRollbackSuccessIntegration(): void
    {
        $manager = new MigrationManager($this->realEntityManager);
        $migration = new Migration202501010003($this->realEntityManager);
        $manager->registerMigration($migration);
        $manager->executeMigration($migration);

        // Verify it was executed
        $this->assertTrue($manager->isMigrationExecuted($migration));

        // Rollback
        $result = $manager->rollback();

        $this->assertTrue($result);
        $this->assertFalse($manager->isMigrationExecuted($migration));
    }

    /**
     * @throws ReflectionException
     */
    public function testCreateMigrationHistoryTableMethodIsCalledIntegration(): void
    {
        $this->realEntityManager->getPdm()->exec("DROP TABLE IF EXISTS migration_history");
        // Verify table doesn't exist
        $this->assertFalse($this->migrationHistoryTableExists());
        new MigrationManager($this->realEntityManager);
        
        // Verify the table was created with the correct structure
        $this->assertTrue($this->migrationHistoryTableExists());
        
        // Verify the table has the expected columns
        $sql = "DESCRIBE migration_history";
        $statement = $this->realEntityManager->getPdm()->prepare($sql);
        $statement->execute();
        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_column($columns, 'Field');
        $this->assertContains('id', $columnNames);
        $this->assertContains('version', $columnNames);
        $this->assertContains('executed_at', $columnNames);
        $this->assertContains('execution_time', $columnNames);
    }

    /**
     * Helper method to check if a table exists
     */
    private function migrationHistoryTableExists(): bool
    {
        try {
            $sql = "SELECT 1 FROM migration_history LIMIT 1";
            $statement = $this->realEntityManager->getPdm()->prepare($sql);
            $statement->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
