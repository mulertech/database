<?php

namespace MulerTech\Database\Tests\Migration;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Migration\Migration;
use MulerTech\Database\Migration\MigrationGenerator;
use MulerTech\Database\Migration\MigrationManager;
use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\Migration\Schema\SchemaDifference;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Relational\Sql\InformationSchema;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Tests\Files\Migrations\Migration202504201358;
use MulerTech\MTerm\Core\Terminal;
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
    private DbMapping $dbMapping;
    private MigrationManager $migrationManager;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $terminal = $this->createMock(Terminal::class);
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager([]),
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
        $this->dbMapping = $this->entityManager->getDbMapping();
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
        $this->assertTrue($this->migrationManager->isMigrationExecuted('20250501-1024'));
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
        )->generateMigration('202505011024');

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
        $schemaComparer = $this->getMockBuilder(\MulerTech\Database\Migration\Schema\SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new \MulerTech\Database\Migration\Schema\SchemaDifference();
        $schemaDifference->addColumnToAdd('users_test', 'status', [
            'COLUMN_TYPE' => 'enum(\'active\',\'inactive\')',
            'IS_NULLABLE' => 'NO',
            'COLUMN_DEFAULT' => 'active'
        ]);
        $schemaComparer->expects($this->once())->method('compare')->willReturn($schemaDifference);

        $migrationGenerator = new \MulerTech\Database\Migration\MigrationGenerator($schemaComparer, $this->migrationsDirectory);

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
        $schemaComparer = $this->getMockBuilder(\MulerTech\Database\Migration\Schema\SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new \MulerTech\Database\Migration\Schema\SchemaDifference();
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

        $migrationGenerator = new \MulerTech\Database\Migration\MigrationGenerator($schemaComparer, $this->migrationsDirectory);

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
     */
    public function testInitializeMigrationTableThrowsIfTableNameNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $dbMapping = $this->createMock(DbMapping::class);
        $entityManager->method('getDbMapping')->willReturn($dbMapping);
        $entityManager->method('getEmEngine')->willReturn($this->createMock(EmEngine::class));
        $dbMapping->method('getTableName')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migration history table name not found in mapping.');

        new MigrationManager($entityManager);
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
}
