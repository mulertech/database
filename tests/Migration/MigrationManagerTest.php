<?php

namespace MulerTech\Database\Tests\Migration;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Migration\Migration;
use MulerTech\Database\Migration\MigrationManager;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Tests\Files\Migrations\Migration202504201358;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MigrationManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private MigrationManager $migrationManager;
    
    protected function setUp(): void
    {
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new Driver()), []),
            new DbMapping(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'),
            new EventManager()
        );

        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS migration_history');
        
        $this->migrationManager = new MigrationManager($this->entityManager);
    }
    
    public function testRegisterMigration(): void
    {
        $migration = new Migration202504201358($this->entityManager);

        $this->migrationManager->registerMigration($migration);

        $this->assertSame($migration, $this->migrationManager->getMigrations()['20250420-1358']);
    }
    
    public function testRegisterMigrationsDuplicateVersionThrowsException(): void
    {
        $migration1 = $this->createMock(Migration::class);
        $migration1->method('getVersion')->willReturn('20230101-0000');
        $migration2 = $this->createMock(Migration::class);
        $migration2->method('getVersion')->willReturn('20230101-0000'); // Same version
        
        $this->migrationManager->registerMigration($migration1);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Migration with version 20230101-0000 is already registered');
        
        $this->migrationManager->registerMigration($migration2);
    }
    
    public function testRegisterMigrations(): void
    {
        $migration1 = $this->createMock(Migration::class);
        $migration1->method('getVersion')->willReturn('20230101-0000');
        $migration2 = $this->createMock(Migration::class);
        $migration2->method('getVersion')->willReturn('20230101-0001');
        
        $migrations = [$migration1, $migration2];
        
        $this->migrationManager->registerMigrations($migrations);
        
        $registeredMigrations = $this->migrationManager->getMigrations();
        $this->assertCount(2, $registeredMigrations);
        $this->assertSame($migration1, $registeredMigrations['20230101-0000']);
        $this->assertSame($migration2, $registeredMigrations['20230101-0001']);
    }
    
    public function testGetPendingMigrations(): void
    {
        $reflectionClass = new ReflectionClass($this->migrationManager);
        $executedMigrationsProperty = $reflectionClass->getProperty('executedMigrations');
        $executedMigrationsProperty->setValue($this->migrationManager, ['20230101-0000']);
        
        $migration1 = $this->createMock(Migration::class);
        $migration1->method('getVersion')->willReturn('20230101-0000'); // Already executed
        $migration2 = $this->createMock(Migration::class);
        $migration2->method('getVersion')->willReturn('20230101-0001'); // Pending
        
        $this->migrationManager->registerMigration($migration1);
        $this->migrationManager->registerMigration($migration2);
        
        $pendingMigrations = $this->migrationManager->getPendingMigrations();
        
        $this->assertCount(1, $pendingMigrations);
        $this->assertSame($migration2, $pendingMigrations['20230101-0001']);
    }
    
    public function testMigrate(): void
    {
        $reflectionClass = new ReflectionClass($this->migrationManager);
        $executedMigrationsProperty = $reflectionClass->getProperty('executedMigrations');
        $executedMigrationsProperty->setValue($this->migrationManager, ['20230101-0000']);
        
        $migration1 = $this->createMock(Migration::class);
        $migration1->method('getVersion')->willReturn('20230101-0000'); // Already executed
        $migration1->expects($this->never())->method('up');
        $migration2 = $this->createMock(Migration::class);
        $migration2->method('getVersion')->willReturn('20230101-0001'); // Will be executed
        $migration2->expects($this->once())->method('up');
        $migration3 = $this->createMock(Migration::class);
        $migration3->method('getVersion')->willReturn('20230101-0002'); // Will be executed
        $migration3->expects($this->once())->method('up');
        
        $this->migrationManager->registerMigration($migration1);
        $this->migrationManager->registerMigration($migration2);
        $this->migrationManager->registerMigration($migration3);
        
        $result = $this->migrationManager->migrate();
        
        $this->assertEquals(2, $result);
    }
    
    public function testExecuteMigrationFailureRollsBackTransaction(): void
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('getVersion')->willReturn('20230101-0001');

        $migration->expects($this->once())
            ->method('up')
            ->willThrowException(new \Exception('Migration failed'));
        
        $this->migrationManager->registerMigration($migration);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Migration 20230101-0001 failed');
        
        $this->migrationManager->executeMigration($migration);
    }
    
    public function testRollback(): void
    {
        $reflectionClass = new ReflectionClass($this->migrationManager);
        $executedMigrationsProperty = $reflectionClass->getProperty('executedMigrations');
        $executedMigrationsProperty->setValue($this->migrationManager, ['20230101-0000', '20230101-0001']);
        
        $migration1 = $this->createMock(Migration::class);
        $migration1->method('getVersion')->willReturn('20230101-0000');
        $migration2 = $this->createMock(Migration::class);
        $migration2->method('getVersion')->willReturn('20230101-0001'); // Last migration, will be rolled back
        $migration2->expects($this->once())->method('down');
        
        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        $queryBuilder->method('delete')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('addNamedParameter')->willReturn(':namedParam1');

        $this->migrationManager->registerMigration($migration1);
        $this->migrationManager->registerMigration($migration2);
        
        $migrationManager = $this->getMockBuilder(MigrationManager::class)
            ->setConstructorArgs([$this->entityManager])
            ->onlyMethods(['removeMigrationRecord'])
            ->getMock();
            
        $migrationManager->expects($this->once())
            ->method('removeMigrationRecord')
            ->with('20230101-0001');
            
        foreach ($this->migrationManager->getMigrations() as $migration) {
            $migrationManager->registerMigration($migration);
        }
        
        $executedMigrationsProperty->setValue($migrationManager, ['20230101-0000', '20230101-0001']);
        
        $result = $migrationManager->rollback();
        $this->assertTrue($result);
    }
    
    public function testRollbackWithNoMigrationsReturnsFalse(): void
    {
        $reflectionClass = new ReflectionClass($this->migrationManager);
        $executedMigrationsProperty = $reflectionClass->getProperty('executedMigrations');
        $executedMigrationsProperty->setValue($this->migrationManager, []);
        
        $result = $this->migrationManager->rollback();
        
        $this->assertFalse($result);
    }
    
    public function testRollbackWithMissingMigrationThrowsException(): void
    {
        $reflectionClass = new ReflectionClass($this->migrationManager);
        $executedMigrationsProperty = $reflectionClass->getProperty('executedMigrations');
        $executedMigrationsProperty->setValue($this->migrationManager, ['20230101-0001']);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Migration 20230101-0001 is recorded as executed but cannot be found');
        
        $this->migrationManager->rollback();
    }
}