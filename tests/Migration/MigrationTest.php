<?php

namespace MulerTech\Database\Tests\Migration;

use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\Database\Tests\Files\Migrations\Migration202504201358;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class MigrationTest extends TestCase
{
    private EntityManager $entityManager;
    private string $migrationsDirectory;
    private string $databaseName = 'db';
    private SchemaComparer $schemaComparer;
    private string $migrationDatetime = '202310011200';
    private MigrationManager $migrationManager;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            new DbMapping(
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
            )
        );
        $this->migrationsDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';

        if (!is_dir($this->migrationsDirectory)) {
            mkdir($this->migrationsDirectory, 0777, true);
        }
        // Utilisation des vraies classes pour comparer et mapping
        $this->schemaComparer = new SchemaComparer(
            new InformationSchema($this->entityManager->getEmEngine()),
            $this->entityManager->getDbMapping(),
            $this->databaseName
        );
        $this->migrationManager = new MigrationManager($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS link_user_group_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS users_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS units_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS groups_test');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS same_table_name');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS group_sub');
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS fake');
        if (is_dir($this->migrationsDirectory)) {
            $files = glob($this->migrationsDirectory . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->migrationsDirectory);
        }
    }

    /**
     * @throws ReflectionException
     */
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

    /**
     * @throws ReflectionException
     */
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
        $migrations = $this->migrationManager->getMigrations();
        $this->assertTrue($this->migrationManager->isMigrationExecuted($migrations['20250501-1024']));
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateMigrationReturnsNullWhenNoChanges(): void
    {
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getDbMapping(),
                $this->databaseName
            ),
            $this->migrationsDirectory,
        )->generateMigration('202505011025');

        $content = file_get_contents($filename);

        $this->assertStringContainsString('class Migration202505011025', $content);
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

    /**
     * @throws ReflectionException
     */
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
        )->generateMigration('202505011026');

        $this->migrationManager->registerMigrations($this->migrationsDirectory);
        $this->migrationManager->migrate();

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$sql = $schema->dropTable("fake");', $fileContent);
    }

    /**
     * @throws ReflectionException
     */
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

    /**
     * @throws ReflectionException
     */
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

    /**
     * @throws ReflectionException
     */
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

    /**
     * @throws ReflectionException
     */
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

    /**
     * @throws ReflectionException
     */
    public function testValidationThrowsExceptionForEntityWithNoColumns(): void
    {
        // Utilisation d'un mock pour SchemaComparer car on veut contrôler la sortie de compare()
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addTableToCreate('empty_table', 'EmptyEntity');
        $schemaComparer->method('compare')->willReturn($schemaDifference);

        // Mock DbMapping pour retourner aucune colonne
        $dbMapping = $this->getMockBuilder(DbMapping::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPropertiesColumns'])
            ->getMock();
        $dbMapping->method('getPropertiesColumns')->with('EmptyEntity')->willReturn([]);

        // On injecte le mock de dbMapping dans le SchemaComparer utilisé par MigrationGenerator
        if ((new \ReflectionClass($schemaComparer))->hasProperty('dbMapping')) {
            $dbMappingProperty = (new \ReflectionClass($schemaComparer))->getProperty('dbMapping');
            $dbMappingProperty->setValue($schemaComparer, $dbMapping);
        }

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'empty_table' has no columns defined.");

        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    /**
     * @throws ReflectionException
     */
    public function testValidationThrowsExceptionForIncompleteForeignKeyDefinition(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_incomplete', [
            'COLUMN_NAME' => 'unit_id',
            // Missing referenced table name
        ]);
        $schemaComparer->method('compare')->willReturn($schemaDifference);

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Foreign key 'fk_incomplete' has incomplete definition.");

        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateMigrationWithDefaultValues(): void
    {
        // Utilisation d'un mock pour SchemaComparer car on veut contrôler la sortie de compare()
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addColumnToAdd('users_test', 'status', [
            'COLUMN_TYPE' => 'enum(\'active\',\'inactive\')',
            'IS_NULLABLE' => 'NO',
            'COLUMN_DEFAULT' => 'active'
        ]);
        $schemaComparer->expects($this->once())->method('compare')->willReturn($schemaDifference);

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);

        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);

        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("status")', $fileContent);
        $this->assertStringContainsString('->default("active")', $fileContent);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateMigrationWithNullDefaults(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

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
        $schemaComparer->expects($this->once())->method('compare')->willReturn($schemaDifference);

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);

        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);

        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$schema = new SchemaBuilder();', $fileContent);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test")', $fileContent);
        $this->assertStringContainsString('->column("description")', $fileContent);
        $this->assertStringContainsString('->text()', $fileContent);
    }

    /**
     * @throws Exception
     */
    public function testMigrationThrowsExceptionWithInvalidClassName(): void
    {
        // Crée une classe anonyme qui étend Migration avec un nom de classe invalide
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migration class name format. Expected: MigrationYYYYMMDDHHMM');

        new class($entityManager) extends Migration {
            public function up(): void {}
            public function down(): void {}
        };
    }

    public function testRegisterMigration(): void
    {
        $migration = new Migration202504201358($this->entityManager);

        $this->migrationManager->registerMigration($migration);

        $this->assertSame($migration, $this->migrationManager->getMigrations()['20250420-1358']);
    }

    /**
     * @throws Exception
     */
    public function testRegisterMigrationsDuplicateVersionThrowsException(): void
    {
        $migration1 = $this->createMock(Migration::class);
        $migration1->method('getVersion')->willReturn('20230101-0000');
        $migration2 = $this->createMock(Migration::class);
        $migration2->method('getVersion')->willReturn('20230101-0000'); // Same version

        $this->migrationManager->registerMigration($migration1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration with version 20230101-0000 is already registered');

        $this->migrationManager->registerMigration($migration2);
    }

    /**
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
    public function testExecuteMigrationFailureRollsBackTransaction(): void
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('getVersion')->willReturn('20230101-0001');

        $migration->expects($this->once())
            ->method('up')
            ->willThrowException(new \Exception('Migration failed'));

        $this->migrationManager->registerMigration($migration);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 20230101-0001 failed');

        $this->migrationManager->executeMigration($migration);
    }

    /**
     * @throws Exception
     */
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration 20230101-0001 is recorded as executed but cannot be found');

        $this->migrationManager->rollback();
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryBuilderReturnsQueryBuilderInstance(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $emEngine = $this->createMock(EmEngine::class);
        $entityManager->method('getEmEngine')->willReturn($emEngine);

        $migration = new Migration202504201358($entityManager);
        $queryBuilder = $migration->createQueryBuilder();
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateMigrationDownCodeCoversIssetIsNullableFrom(): void
    {
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS users_test');
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE users_test (bio VARCHAR(100) NOT NULL DEFAULT "")'
        );
        $this->entityManager->getPdm()->exec(
            'ALTER TABLE users_test MODIFY bio VARCHAR(100) NULL DEFAULT NULL'
        );

        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $generator->generateMigration($this->migrationDatetime);

        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateDownCode');
        $schemaDifference = $this->schemaComparer->compare();
        $downCode = $method->invoke($generator, $schemaDifference);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $downCode);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users_test");', $downCode);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testInitializeMigrationTableCreatesTableIfNotExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $dbMapping = $this->createMock(DbMapping::class);
        $emEngine = $this->createMock(EmEngine::class);
        $pdm = $this->createMock(PhpDatabaseManager::class);

        $entityManager->method('getDbMapping')->willReturn($dbMapping);
        $entityManager->method('getEmEngine')->willReturn($emEngine);
        $entityManager->method('getPdm')->willReturn($pdm);
        $dbMapping->method('getTableName')->willReturn('migration_history');

        $informationSchema = $this->getMockBuilder(InformationSchema::class)
            ->disableOriginalConstructor()->getMock();
        $informationSchema->method('getTables')->willReturn([['TABLE_NAME' => 'other_table']]);

        $pdm->expects($this->exactly(2))
            ->method('exec')
            ->with($this->stringContains('CREATE TABLE `migration_history`'));

        $manager = $this->getMockBuilder(MigrationManager::class)
            ->setConstructorArgs([$entityManager])
            ->onlyMethods([])
            ->getMock();

        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('createMigrationHistoryTable');

        $method->invoke($manager, 'migration_history');
    }

    /**
     * Test parseColumnType with various column types to improve coverage
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithAllTypes(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        // Test bigint column type
        $result = $method->invoke($generator, 'bigint(20) unsigned', 'test_column', false, null, null);
        $this->assertStringContainsString('->bigInteger()', $result);
        $this->assertStringContainsString('->unsigned()', $result);
        $this->assertStringContainsString('->notNull()', $result);

        // Test decimal column type
        $result = $method->invoke($generator, 'decimal(10,2)', 'price', true, '0.00', null);
        $this->assertStringContainsString('->decimal(10, 2)', $result);
        $this->assertStringContainsString('->default("0.00")', $result);
        $this->assertStringNotContainsString('->notNull()', $result);

        // Test float column type
        $result = $method->invoke($generator, 'float(8,2)', 'rating', false, null, null);
        $this->assertStringContainsString('->float(8, 2)', $result);

        // Test datetime column type
        $result = $method->invoke($generator, 'datetime', 'created_at', false, null, null);
        $this->assertStringContainsString('->datetime()', $result);

        // Test text column type
        $result = $method->invoke($generator, 'text', 'description', true, null, null);
        $this->assertStringContainsString('->text()', $result);

        // Test empty/null column type (should default to string)
        $result = $method->invoke($generator, '', 'test_empty', false, null, null);
        $this->assertStringContainsString('->string()', $result);

        $result = $method->invoke($generator, null, 'test_null', false, null, null);
        $this->assertStringContainsString('->string()', $result);
    }

    /**
     * Test generateDownCode method with foreign keys to cover missing lines
     * @throws ReflectionException
     */
    public function testGenerateDownCodeWithForeignKeys(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        
        // Add foreign key to be added (so it gets dropped in down code)
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_test_constraint', [
            'COLUMN_NAME' => 'category_id',
            'REFERENCED_TABLE_NAME' => 'categories',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => 'CASCADE',
            'UPDATE_RULE' => 'RESTRICT'
        ]);

        $schemaComparer->method('compare')->willReturn($schemaDifference);

        $generator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateDownCode');

        $downCode = $method->invoke($generator, $schemaDifference);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $downCode);
        $this->assertStringContainsString('->dropForeignKey("fk_test_constraint");', $downCode);
    }

    /**
     * Test generateDownCode with empty differences to cover "No rollback operations" branch
     * @throws ReflectionException
     */
    public function testGenerateDownCodeWithNoDifferences(): void
    {
        $schemaDifference = new SchemaDifference();
        
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateDownCode');

        $downCode = $method->invoke($generator, $schemaDifference);

        $this->assertStringContainsString('// No rollback operations defined', $downCode);
    }

    /**
     * Test generateRestoreColumnStatement method to improve coverage
     * @throws ReflectionException
     */
    public function testGenerateRestoreColumnStatement(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateRestoreColumnStatement');

        $differences = [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'varchar(255)'
            ],
            'IS_NULLABLE' => [
                'from' => 'NO',
                'to' => 'YES'
            ],
            'COLUMN_DEFAULT' => [
                'from' => 'old_default',
                'to' => 'new_default'
            ]
        ];

        $result = $method->invoke($generator, 'test_table', 'test_column', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("test_table");', $result);
        $this->assertStringContainsString('->column("test_column")', $result);
        $this->assertStringContainsString('->string(100)', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->default("old_default")', $result);
    }

    /**
     * Test generateModifyColumnStatement with edge cases to improve coverage
     * @throws ReflectionException
     */
    public function testGenerateModifyColumnStatementEdgeCases(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateModifyColumnStatement');

        // Test with minimal differences (no IS_NULLABLE change)
        $differences = [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'varchar(255)'
            ]
        ];

        $result = $method->invoke($generator, 'test_table', 'test_column', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("test_table");', $result);
        $this->assertStringContainsString('->column("test_column")', $result);
    }

    /**
     * Test column type parsing with auto_increment to cover EXTRA handling
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithAutoIncrement(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'int(11)', 'id', false, null, 'auto_increment');
        
        $this->assertStringContainsString('->integer()', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->autoIncrement()', $result);
    }

    /**
     * Test generateRestoreColumnStatement without IS_NULLABLE differences
     * @throws ReflectionException
     */
    public function testGenerateRestoreColumnStatementWithoutNullableChange(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateRestoreColumnStatement');

        $differences = [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'varchar(255)'
            ],
            'COLUMN_DEFAULT' => [
                'from' => null,
                'to' => 'new_default'
            ]
        ];

        $result = $method->invoke($generator, 'test_table', 'test_column', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("test_table");', $result);
        $this->assertStringContainsString('->column("test_column")', $result);
    }

    /**
     * Test that migration generation handles complex column types correctly
     * @throws ReflectionException
     */
    public function testGenerateMigrationWithComplexColumnTypes(): void
    {
        // Create table with various complex column types
        $this->entityManager->getPdm()->exec('DROP TABLE IF EXISTS users_test');
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE users_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                score DECIMAL(8,2) DEFAULT 0.00,
                rating FLOAT(6,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data LONGTEXT NULL
            )'
        );

        $filename = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory)
            ->generateMigration('202505011030');

        $fileContent = file_get_contents($filename);
        
        // Test that complex types are handled correctly
        $this->assertStringContainsString('->column("username")', $fileContent);
        $this->assertStringContainsString('->string(255)', $fileContent);
        $this->assertStringContainsString('->default("John")', $fileContent);
    }

    /**
     * Test migration validation with missing referenced table
     * @throws ReflectionException
     */
    public function testValidationWithMissingReferencedTable(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_missing_ref', [
            'COLUMN_NAME' => 'category_id',
            'REFERENCED_TABLE_NAME' => null, // Missing referenced table
            'REFERENCED_COLUMN_NAME' => 'id'
        ]);
        $schemaComparer->method('compare')->willReturn($schemaDifference);

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Foreign key 'fk_missing_ref' has incomplete definition.");

        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    /**
     * Test migration with foreign key having missing column name
     * @throws ReflectionException
     */
    public function testValidationWithMissingColumnName(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_missing_col', [
            'COLUMN_NAME' => null, // Missing column name
            'REFERENCED_TABLE_NAME' => 'categories',
            'REFERENCED_COLUMN_NAME' => 'id'
        ]);
        $schemaComparer->method('compare')->willReturn($schemaDifference);

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Foreign key 'fk_missing_col' has incomplete definition.");

        $migrationGenerator->generateMigration($this->migrationDatetime);
    }

    /**
     * Test parseColumnType with unsigned bigint
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithUnsignedBigint(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'bigint(20) unsigned', 'large_id', false, null, null);
        
        $this->assertStringContainsString('->bigInteger()', $result);
        $this->assertStringContainsString('->unsigned()', $result);
        $this->assertStringContainsString('->notNull()', $result);
    }

    /**
     * Test parseColumnType with double precision
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithDouble(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'double', 'precision_value', true, null, null);
        
        $this->assertStringContainsString('->double()', $result);
        $this->assertStringNotContainsString('->notNull()', $result);
    }

    /**
     * Test parseColumnType with timestamp
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithTimestamp(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'timestamp', 'created_at', false, 'CURRENT_TIMESTAMP', null);
        
        $this->assertStringContainsString('->timestamp()', $result);
        $this->assertStringContainsString('->default("CURRENT_TIMESTAMP")', $result);
        $this->assertStringContainsString('->notNull()', $result);
    }

    /**
     * Test parseColumnType with longtext
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithLongtext(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'longtext', 'content', true, null, null);
        
        $this->assertStringContainsString('->longText()', $result);
        $this->assertStringNotContainsString('->notNull()', $result);
    }

    /**
     * Test parseColumnType with tinyint (boolean)
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithTinyint(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'tinyint(1)', 'is_active', false, '0', null);
        
        $this->assertStringContainsString('->tinyInt()', $result);
        $this->assertStringContainsString('->default("0")', $result);
        $this->assertStringContainsString('->notNull()', $result);
    }

    /**
     * Test migration manager with invalid migration file
     */
    public function testRegisterMigrationsSkipsInvalidFiles(): void
    {
        // Create invalid migration file
        $invalidFile = $this->migrationsDirectory . DIRECTORY_SEPARATOR . 'InvalidMigration.php';
        file_put_contents($invalidFile, '<?php class InvalidMigration {}');

        // Should not throw exception, just skip invalid files
        $this->migrationManager->registerMigrations($this->migrationsDirectory);
        
        // Verify that no migrations were registered from invalid file
        $this->assertEmpty($this->migrationManager->getMigrations());
    }

    /**
     * Test migration manager with empty migrations directory
     */
    public function testRegisterMigrationsWithEmptyDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'empty_migrations';
        if (!is_dir($emptyDir)) {
            mkdir($emptyDir, 0777, true);
        }

        $this->migrationManager->registerMigrations($emptyDir);
        
        $this->assertEmpty($this->migrationManager->getMigrations());
        
        rmdir($emptyDir);
    }

    /**
     * Test migration execution with successful migration
     * @throws Exception
     */
    public function testExecuteMigrationSuccess(): void
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('getVersion')->willReturn('20230101-0001');
        $migration->expects($this->once())->method('up');

        $this->migrationManager->registerMigration($migration);
        
        $this->migrationManager->executeMigration($migration);
        
        $this->assertTrue($this->migrationManager->isMigrationExecuted($migration));
    }

    /**
     * Test rollback failure when migration down() throws exception
     * @throws Exception
     */
    public function testRollbackFailureInDownMethod(): void
    {
        $reflectionClass = new ReflectionClass($this->migrationManager);
        $executedMigrationsProperty = $reflectionClass->getProperty('executedMigrations');
        $executedMigrationsProperty->setValue($this->migrationManager, ['20230101-0001']);

        $migration = $this->createMock(Migration::class);
        $migration->method('getVersion')->willReturn('20230101-0001');
        $migration->expects($this->once())
            ->method('down')
            ->willThrowException(new \Exception('Rollback failed'));

        $this->migrationManager->registerMigration($migration);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration rollback 20230101-0001 failed: Rollback failed');

        $this->migrationManager->rollback();
    }

    /**
     * Test column modification with null default value changes
     * @throws ReflectionException
     */
    public function testGenerateModifyColumnStatementWithNullDefaults(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateModifyColumnStatement');

        $differences = [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'varchar(255)'
            ],
            'COLUMN_DEFAULT' => [
                'from' => 'old_value',
                'to' => null
            ]
        ];

        $result = $method->invoke($generator, 'test_table', 'test_column', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("test_table");', $result);
        $this->assertStringContainsString('->column("test_column")', $result);
    }

    /**
     * Test generateRestoreColumnStatement with complex changes
     * @throws ReflectionException
     */
    public function testGenerateRestoreColumnStatementComplex(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('generateRestoreColumnStatement');

        $differences = [
            'COLUMN_TYPE' => [
                'from' => 'int(11)',
                'to' => 'bigint(20)'
            ],
            'IS_NULLABLE' => [
                'from' => 'YES',
                'to' => 'NO'
            ],
            'COLUMN_DEFAULT' => [
                'from' => null,
                'to' => '0'
            ]
        ];

        $result = $method->invoke($generator, 'test_table', 'test_column', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("test_table");', $result);
        $this->assertStringContainsString('->column("test_column")', $result);
        $this->assertStringContainsString('->integer()', $result);
    }

    /**
     * Test foreign key generation with all constraint rules
     * @throws ReflectionException
     */
    public function testGenerateForeignKeyWithAllRules(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_complete', [
            'COLUMN_NAME' => 'category_id',
            'REFERENCED_TABLE_NAME' => 'categories',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => 'SET NULL',
            'UPDATE_RULE' => 'CASCADE'
        ]);
        $schemaComparer->method('compare')->willReturn($schemaDifference);

        $generator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);
        $filename = $generator->generateMigration($this->migrationDatetime);

        $fileContent = file_get_contents($filename);
        $this->assertStringContainsString('->foreignKey("fk_complete")', $fileContent);
        $this->assertStringContainsString('->references("categories", "id")', $fileContent);
        $this->assertStringContainsString(
            '->onDelete(MulerTech\Database\Schema\ReferentialAction::SET_NULL)',
            $fileContent
        );
        $this->assertStringContainsString(
            '->onUpdate(MulerTech\Database\Schema\ReferentialAction::CASCADE)',
            $fileContent
        );
    }

    /**
     * Test column type parsing with json type
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithJson(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, 'json', 'metadata', true, null, null);
        
        $this->assertStringContainsString('->json()', $result);
        $this->assertStringNotContainsString('->notNull()', $result);
    }

    /**
     * Test column type parsing with enum
     * @throws ReflectionException
     */
    public function testParseColumnTypeWithEnum(): void
    {
        $generator = new MigrationGenerator($this->schemaComparer, $this->migrationsDirectory);
        $reflection = new ReflectionClass($generator);
        $method = $reflection->getMethod('parseColumnType');

        $result = $method->invoke($generator, "enum('active','inactive')", 'status', false, 'active', null);
        $this->assertStringContainsString("->enum(['active', 'inactive'])", $result);
        $this->assertStringContainsString('->default("active")', $result);
        $this->assertStringContainsString('->notNull()', $result);
    }

    /**
     * Test that schema comparer handles non-existent database gracefully
     */
    public function testSchemaComparerWithNonExistentDatabase(): void
    {
        $informationSchema = $this->getMockBuilder(InformationSchema::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Mock empty results for non-existent database
        $informationSchema->method('getTables')->willReturn([]);
        $informationSchema->method('getColumns')->willReturn([]);
        $informationSchema->method('getForeignKeys')->willReturn([]);

        $schemaComparer = new SchemaComparer(
            $informationSchema,
            $this->entityManager->getDbMapping(),
            'non_existent_db'
        );

        $diff = $schemaComparer->compare();
        
        // Should suggest creating all entity tables since database is empty
        $this->assertNotEmpty($diff->getTablesToCreate());
    }
}
