<?php

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\Database\Schema\Migration\MigrationManager;
use PHPUnit\Framework\TestCase;
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
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        // Create MetadataCache with automatic entity loading from test directory
        $entitiesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $metadataCache = new MetadataCache(null, $entitiesPath);
        // Also load system entities like MigrationHistory
        $metadataCache->getEntityMetadata(MigrationHistory::class);
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache,
        );
        $this->migrationsDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';

        if (!is_dir($this->migrationsDirectory)) {
            mkdir($this->migrationsDirectory, 0777, true);
        }
        // Utilisation des vraies classes pour comparer et mapping
        $this->schemaComparer = new SchemaComparer(
            new InformationSchema($this->entityManager->getEmEngine()),
            $this->entityManager->getMetadataCache(),
            $this->databaseName
        );
        // Clean up migration history before creating MigrationManager
        $this->entityManager->getPdm()->exec('DELETE FROM migration_history WHERE 1=1');
        
        $this->migrationManager = new MigrationManager($this->entityManager);
    }

    protected function tearDown(): void
    {
        // Clean up migration history first
        $this->entityManager->getPdm()->exec('DELETE FROM migration_history');
        
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
            $nonExistentDir,
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateMigrationAndMigrate(): void
    {
        $migrationDatetime = '202505011025';
        $migrationName = '20250501-1025';
        $filename = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
            $this->migrationsDirectory,
//            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Migrations'
        )->generateMigration($migrationDatetime);

        $fileContent = file_get_contents($filename);

        // Test file creation
        $this->assertStringContainsString('class Migration' . $migrationDatetime, $fileContent);
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
        $this->assertTrue($this->migrationManager->isMigrationExecuted($migrations[$migrationName]));
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateMigrationReturnsNullWhenNoChanges(): void
    {
        // First, generate and execute a migration that should create all the necessary tables
        $generator = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
            $this->migrationsDirectory,
        );
        
        $filename = $generator->generateMigration('202505011025');
        $content = file_get_contents($filename);

        $this->assertStringContainsString('class Migration202505011025', $content);
        $this->migrationManager->registerMigrations($this->migrationsDirectory);
        
        $executedCount = $this->migrationManager->migrate();
        $this->assertEquals(1, $executedCount, 'Should execute exactly one migration');

        // Now the database should be in sync with the metadata
        // Create a fresh generator to ensure no caching issues
        $freshGenerator = new MigrationGenerator(
            new SchemaComparer(
                new InformationSchema($this->entityManager->getEmEngine()),
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
            $this->migrationsDirectory,
        );
        
        // Second migration generation should return null since there are no more changes
        $result = $freshGenerator->generateMigration('202505011506');
        
        $this->assertNull($result, 'Second migration should return null when no changes are needed');
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
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
                $this->entityManager->getMetadataCache(),
                $this->databaseName
            ),
            $this->entityManager->getMetadataCache(),
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

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->entityManager->getMetadataCache(), $this->migrationsDirectory);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not find entity class for table 'empty_table'");

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

        $migrationGenerator = new MigrationGenerator($schemaComparer, $this->entityManager->getMetadataCache(), $this->migrationsDirectory);

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
    public function testGenerateMigrationWithForeignKeyAddition(): void
    {
        $schemaComparer = $this->getMockBuilder(SchemaComparer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['compare'])
            ->getMock();

        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_category', [
            'COLUMN_NAME' => 'category_id',
            'REFERENCED_TABLE_NAME' => 'categories',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => FkRule::CASCADE,
            'UPDATE_RULE' => FkRule::RESTRICT
        ]);
        $schemaComparer->method('compare')->willReturn($schemaDifference);

        $generator = new MigrationGenerator($schemaComparer, $this->entityManager->getMetadataCache(), $this->migrationsDirectory);
        $filename = $generator->generateMigration($this->migrationDatetime);

        $fileContent = file_get_contents($filename);

        // Verify foreign key generation
        $this->assertStringContainsString('->foreignKey("fk_category")', $fileContent);
        $this->assertStringContainsString('->references("categories", "id")', $fileContent);
    }

    /**
     * Test migration manager functionality
     */
    public function testMigrationManagerFunctionality(): void
    {
        // Create valid migration file
        $validMigration = $this->migrationsDirectory . DIRECTORY_SEPARATOR . 'Migration202501010000.php';
        $migrationContent = '<?php
        use MulerTech\Database\Schema\Migration\Migration;
        use MulerTech\Database\Schema\Builder\SchemaBuilder;
        
        class Migration202501010000 extends Migration
        {
            public function up(): void {}
            public function down(): void {}
        }';

        file_put_contents($validMigration, $migrationContent);

        $this->migrationManager->registerMigrations($this->migrationsDirectory);

        $migrations = $this->migrationManager->getMigrations();
        $this->assertNotEmpty($migrations);
        $this->assertArrayHasKey('20250101-0000', $migrations);
    }

    /**
     * Test with complex column types
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

        $filename = new MigrationGenerator($this->schemaComparer, $this->entityManager->getMetadataCache(), $this->migrationsDirectory)
            ->generateMigration('202505011030');

        $fileContent = file_get_contents($filename);
        
        // Test that complex types are handled correctly
        $this->assertStringContainsString('->column("username")', $fileContent);
        $this->assertStringContainsString('->string(255)', $fileContent);
        $this->assertStringContainsString('->default("John")', $fileContent);
    }

}
