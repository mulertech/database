<?php

namespace MulerTech\Database\Tests\Migration;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Migration\Migration;
use MulerTech\Database\Migration\MigrationGenerator;
use MulerTech\Database\Migration\MigrationManager;
use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\Migration\Schema\SchemaDifference;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Relational\Sql\InformationSchema;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\Database\Tests\Files\Migrations\Migration202504201358;
use MulerTech\MTerm\Core\Terminal;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class MigrationTest extends TestCase
{
    private Terminal $terminal;
    private EntityManager $entityManager;
    private string $migrationsDirectory;
    private string $databaseName = 'db';
    private SchemaComparer $schemaComparer;
    private string $migrationDatetime = '202310011200';
    private DbMapping $dbMapping;
    private MigrationManager $migrationManager;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new Driver()), []),
            new DbMapping(
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
            )
        );
        $this->migrationsDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';

        $this->command = new MigrationGenerateCommand(
            $this->terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        if (!is_dir($this->migrationsDirectory)) {
            mkdir($this->migrationsDirectory, 0777, true);
        }
        $this->schemaComparer = $this->createMock(SchemaComparer::class);
        $this->dbMapping = $this->createMock(DbMapping::class);
        $this->migrationManager = new MigrationManager($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS link_user_group_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS users_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS units_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS groups_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS sametablename');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS groupsub');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS fake');
        if (is_dir($this->migrationsDirectory)) {
            $files = glob($this->migrationsDirectory . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->migrationsDirectory);
        }
    }

    public function testGenerateMigrationWithInvalidDatetime(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid datetime format. Expected: YYYYMMDDHHMM');

        new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )
            ->generateMigration('invalid-datetime');
    }

    public function testConstructorThrowsExceptionWhenDirectoryDoesNotExist(): void
    {
        $nonExistentDir = '/root/non_existent';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Migration directory does not exist: $nonExistentDir");

        new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $nonExistentDir,
        );
    }

    public function testGenerateMigrationAndMigrate(): void
    {
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
//            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Migrations'
        )->generateMigration('202505011024');

        $fileContent = file_get_contents($filename);

        // Test file creation
        $this->assertStringContainsString('class Migration202505011024', $fileContent);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->createTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("id")', $fileContent);
        $this->assertStringContainsString('->integer()', $fileContent);
        $this->assertStringContainsString('->unsigned()', $fileContent);
        $this->assertStringContainsString('->notNull()', $fileContent);
        $this->assertStringContainsString('->autoIncrement()', $fileContent);
        $this->assertStringContainsString('->primaryKey("id")', $fileContent);
        $this->assertStringContainsString('->column("username")', $fileContent);
        $this->assertStringContainsString('->string(255)', $fileContent);
        $this->assertStringContainsString('->default("John")', $fileContent);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$sql = $schema->dropTable("users_test");', $fileContent);

        // Test migration execution
        $this->migrationManager->registerMigrations($this->migrationsDirectory);
        $this->migrationManager->migrate();
        $this->assertTrue($this->migrationManager->isMigrationExecuted('20250501-1024'));
    }

    public function testGenerateMigrationReturnsNullWhenNoChanges(): void
    {
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011024');

        $content = file_get_contents($filename);

        $this->assertStringContainsString('class Migration202505011024', $content);
        $this->migrationManager->registerMigrations($this->migrationsDirectory);
        $this->migrationManager->migrate();

        $this->assertNull(new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011506'));
    }

    public function testGenerateMigrationWithTableDrop(): void
    {
        // Create fake table to be dropped
        $this->entityManager->getPdm()->exec('CREATE TABLE fake (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)');

        // Generate migration file
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011024');

        $this->migrationManager->registerMigrations($this->migrationsDirectory);
        $this->migrationManager->migrate();

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$sql = $schema->dropTable("fake");', $fileContent);
    }

    public function testGenerateMigrationWithColumnAddition(): void
    {
        // Create users_test table with only id
        $this->entityManager->getPdm()->exec('CREATE TABLE users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY)');

        // Create migration file
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011024');

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("username")', $fileContent);
        $this->assertStringContainsString('->string(255)', $fileContent);
        $this->assertStringContainsString('->notNull()', $fileContent);
        $this->assertStringContainsString('->dropColumn("username");', $fileContent, 'into down method');
    }

    public function testGenerateMigrationWithColumnModification(): void
    {
        // Create users_test table with only id and username with varchar(100)
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE users_test (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) DEFAULT NULL)'
        );

        // Create migration file
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011024');

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("username")', $fileContent);
        $this->assertStringContainsString('->string(255)', $fileContent);
        $this->assertStringContainsString('->notNull()', $fileContent);
        $this->assertStringContainsString('->default("John")', $fileContent);
    }

    public function testGenerateMigrationWithColumnDrop(): void
    {
        // Create users_test table with one more column
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE users_test (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, fake VARCHAR(100) DEFAULT NULL)'
        );

        // Create migration file
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011024');

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->dropColumn("fake");', $fileContent);
    }

    public function testGenerateMigrationWithForeignKeyDrop(): void
    {
        // Create users_test table with fake table with foreign key into users_test table
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE fake (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) DEFAULT NULL)'
        );
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE users_test (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, fake_id INT DEFAULT NULL, CONSTRAINT fk_test FOREIGN KEY (fake_id) REFERENCES fake(id))'
        );

        // Create migration file
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011024');

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->dropForeignKey("fk_test");', $fileContent);
    }

    public function testValidationThrowsExceptionForEntityWithNoColumns(): void
    {
        // Create a schema difference with a table that has no columns
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addTableToCreate('empty_table', 'EmptyEntity');
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        // Make dbMapping return empty columns for this entity
        $this->dbMapping->method('getPropertiesColumns')
            ->with('EmptyEntity')
            ->willReturn([]);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'empty_table' has no columns defined.");
        
        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    public function testValidationThrowsExceptionForIncompleteForeignKeyDefinition(): void
    {
        $schemaDifference = new SchemaDifference();
        
        // Add foreign key with incomplete definition
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_incomplete', [
            'COLUMN_NAME' => 'unit_id',
            // Missing referenced table name
        ]);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Foreign key 'fk_incomplete' has incomplete definition.");
        
        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    public function testValidationThrowsExceptionForNonExistingColumn(): void
    {
        $schemaDifference = new SchemaDifference();
        
        // Add foreign key that references a column that doesn't exist
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_missing_column', [
            'COLUMN_NAME' => 'non_existent_column',
            'REFERENCED_TABLE_NAME' => 'units_test',
            'REFERENCED_COLUMN_NAME' => 'id'
        ]);
        
        // Add referenced table to avoid that validation error
        $schemaDifference->addTableToCreate('units_test', Unit::class);
        
        $this->dbMapping->method('getPropertiesColumns')
            ->willReturnCallback(function($class) {
                if ($class === Unit::class) {
                    return [
                        'id' => 'id',
                        'name' => 'name'
                    ];
                }
                return [];
            });
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot add foreign key 'fk_missing_column': Column 'users_test.non_existent_column' does not exist.");
        
        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    public function testGenerateMigrationWithDefaultValues(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addColumnToAdd('users_test', 'status', [
            'COLUMN_TYPE' => 'enum(\'active\',\'inactive\')',
            'IS_NULLABLE' => 'NO',
            'COLUMN_DEFAULT' => 'active'
        ]);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("status")', $fileContent);
        $this->assertStringContainsString('->default("active")', $fileContent);
    }

    public function testGenerateMigrationWithNullDefaults(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addColumnToModify('users_test', 'description', [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'text'
            ],
            'IS_NULLABLE' => [
                'from' => 'NO',
                'to' => 'YES'
            ],
            'COLUMN_DEFAULT' => [
                'from' => '',
                'to' => null
            ]
        ]);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("description")', $fileContent);
        $this->assertStringContainsString('->text()', $fileContent);
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
