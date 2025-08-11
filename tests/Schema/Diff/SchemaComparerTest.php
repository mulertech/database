<?php

namespace MulerTech\Database\Tests\Schema\Diff;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\MySQLDriver;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\Database\Tests\Files\Entity\Group;
use MulerTech\Database\Tests\Files\Entity\GroupUser;
use MulerTech\Database\Tests\Files\Entity\SameTableName;
use MulerTech\Database\Tests\Files\Entity\SubDirectory\GroupSub;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SchemaComparerTest extends TestCase
{
    private EntityManager $entityManager;
    private InformationSchema $informationSchema;
    private string $databaseName = 'db';
    
    protected function setUp(): void
    {
        // Create MetadataCache with automatic entity loading from test directory
        $entitiesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity';
        $metadataCache = new MetadataCache(null, $entitiesPath);
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new MySQLDriver()), []),
            $metadataCache,
        );
        $this->informationSchema = new InformationSchema($this->entityManager->getEmEngine());
    }

    private function createSameTables(): void
    {
        // Clean migration history to ensure migrations can run
        $this->entityManager->getPdm()->exec('DELETE FROM migration_history WHERE 1=1');
        
        // Replace by a migration script
        $migrationManager = new MigrationManager($this->entityManager);
        $migrationManager->registerMigrations(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'OriginalMigration'
        );
        $migrationManager->migrate();
    }

    protected function tearDown(): void
    {
        // Clean up migration history first
        $this->entityManager->getPdm()->exec('DELETE FROM migration_history WHERE 1=1');
        
        $tables = [
            'link_user_group_test',
            'users_test',
            'units_test',
            'groups_test',
            'same_table_name',
            'group_sub',
            'fake'
        ];

        $errors = [];
        foreach ($tables as $table) {
            $result = $this->entityManager->getPdm()->exec("DROP TABLE IF EXISTS $table");
            if ($result === false) {
                $errors[] = "Failed to drop table $table";
            }
        }

        if (!empty($errors)) {
            throw new RuntimeException(implode(', ', $errors));
        }
    }
    
    public function testCompareFindsNewTables(): void
    {
        // Database has no tables
        $schemaComparer = new SchemaComparer(
            $this->informationSchema,
            $this->entityManager->getMetadataCache(),
            $this->databaseName
        );
        $diff = $schemaComparer->compare();
        $this->assertTrue($diff->hasDifferences());
        $this->assertEquals([
            'groups_test' => Group::class,
            'link_user_group_test' => GroupUser::class,
            'same_table_name' => SameTableName::class,
            'group_sub' => GroupSub::class,
            'units_test' => Unit::class,
            'users_test' => User::class,
        ],
            $diff->getTablesToCreate()
        );
        $this->assertEmpty($diff->getTablesToDrop());
    }
    
    public function testCompareFindsTablesToDrop(): void
    {
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE IF NOT EXISTS fake (id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT)'
        );
        // Database has a table that should be removed
        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();
        $this->assertTrue($diff->hasDifferences());
        $this->assertEquals(['fake'], $diff->getTablesToDrop());
    }
    
    public function testCompareIgnoresMigrationHistoryTable(): void
    {
        $this->createSameTables();
        $this->entityManager->getPdm()->exec(
            'CREATE TABLE IF NOT EXISTS migration_history (id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT)'
        );
        // Database has migration_history table
        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();
        $this->assertFalse($diff->hasDifferences());
        $this->assertEmpty($diff->getTablesToCreate());
        $this->assertEmpty($diff->getTablesToDrop());
    }

    public function testCompareFindsColumnsToAdd(): void
    {
        $this->createTablesWithMissingColumns();

        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();

        $this->assertTrue($diff->hasDifferences());
        $columnsToAdd = $diff->getColumnsToAdd();

        // Check if the missing columns are detected
        $this->assertArrayHasKey('users_test', $columnsToAdd);
        $this->assertArrayHasKey('username', $columnsToAdd['users_test']);
        $this->assertArrayHasKey('units_test', $columnsToAdd);
        $this->assertArrayHasKey('name', $columnsToAdd['units_test']);
    }

    public function testCompareFindsColumnsToModify(): void
    {
        $this->createTablesWithDifferentColumns();

        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();

        $this->assertTrue($diff->hasDifferences());
        $columnsToModify = $diff->getColumnsToModify();

        // Check if the column type is detected
        $this->assertArrayHasKey('users_test', $columnsToModify);
        $this->assertArrayHasKey('size', $columnsToModify['users_test']);
        $this->assertArrayHasKey('COLUMN_TYPE', $columnsToModify['users_test']['size']);

        // Check if the nullability is detected
        $this->assertArrayHasKey('IS_NULLABLE', $columnsToModify['users_test']['size']);

        // Check if the default value is detected
        $this->assertArrayHasKey('username', $columnsToModify['users_test']);
        $this->assertArrayHasKey('COLUMN_DEFAULT', $columnsToModify['users_test']['username']);
    }

    public function testCompareFindsColumnsToDrop(): void
    {
        $this->createTablesWithExtraColumns();

        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();

        $this->assertTrue($diff->hasDifferences());
        $columnsToDrop = $diff->getColumnsToDrop();

        // Check if the extra columns are detected
        $this->assertArrayHasKey('users_test', $columnsToDrop);
        $this->assertContains('extra_column', $columnsToDrop['users_test']);

        $this->assertArrayHasKey('groups_test', $columnsToDrop);
        $this->assertContains('descriptions', $columnsToDrop['groups_test']);
    }

    public function testCompareFindsNewForeignKeys(): void
    {
        $this->createTablesWithoutForeignKeys();

        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();

        $this->assertTrue($diff->hasDifferences());
        $foreignKeysToAdd = $diff->getForeignKeysToAdd();

        // Check if the foreign keys are detected
        $this->assertArrayHasKey('users_test', $foreignKeysToAdd);
        $this->assertArrayHasKey('fk_users_test_unit_id_units_test', $foreignKeysToAdd['users_test']);

        $this->assertArrayHasKey('link_user_group_test', $foreignKeysToAdd);
        $this->assertArrayHasKey('fk_link_user_group_test_user_id_users_test', $foreignKeysToAdd['link_user_group_test']);
        $this->assertArrayHasKey('fk_link_user_group_test_group_id_groups_test', $foreignKeysToAdd['link_user_group_test']);
    }

    public function testCompareFindsObsoleteForeignKeys(): void
    {
        $this->createTablesWithExtraForeignKeys();

        $schemaComparer = new SchemaComparer($this->informationSchema, $this->entityManager->getMetadataCache(), $this->databaseName);
        $diff = $schemaComparer->compare();

        $this->assertTrue($diff->hasDifferences());
        $foreignKeysToDrop = $diff->getForeignKeysToDrop();

        // Check if the extra foreign key is detected
        $this->assertArrayHasKey('users_test', $foreignKeysToDrop);
        $this->assertContains('fk_users_test_extra_groups_test', $foreignKeysToDrop['users_test']);
    }

    private function createTablesWithMissingColumns(): void
    {
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS units_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS users_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                size INT,
                unit_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_users_test_unit_id_units_test FOREIGN KEY (unit_id) REFERENCES units_test(id)
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS groups_test (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                parent_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_groups_test_parent_id_groups_test FOREIGN KEY (parent_id) REFERENCES groups_test(id)
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS link_user_group_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                group_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_link_user_group_test_user_id_users_test FOREIGN KEY (user_id) REFERENCES users_test(id),
                CONSTRAINT fk_link_user_group_test_group_id_groups_test FOREIGN KEY (group_id) REFERENCES groups_test(id)
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS same_table_name (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS group_sub (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
    }

    private function createTablesWithDifferentColumns(): void
    {
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS units_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(128) NOT NULL
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS users_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NULL default "Jane",
                size VARCHAR(10) NOT NULL,
                unit_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_users_test_unit_id_units_test FOREIGN KEY (unit_id) REFERENCES units_test(id)
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS groups_test (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                parent_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_groups_test_parent_id_groups_test FOREIGN KEY (parent_id) REFERENCES groups_test(id)
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS link_user_group_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                group_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_link_user_group_test_user_id_users_test FOREIGN KEY (user_id) REFERENCES users_test(id),
                CONSTRAINT fk_link_user_group_test_group_id_groups_test FOREIGN KEY (group_id) REFERENCES groups_test(id)
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS same_table_name (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS group_sub (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
    }

    private function createTablesWithExtraColumns(): void
    {
        $this->createSameTables();
        $this->entityManager->getPdm()->exec('ALTER TABLE users_test ADD COLUMN extra_column VARCHAR(255)');
        $this->entityManager->getPdm()->exec('ALTER TABLE groups_test ADD COLUMN descriptions TEXT');
    }

    private function createTablesWithoutForeignKeys(): void
    {
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS units_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS users_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL default "John",
                size INT,
                unit_id INT UNSIGNED NOT NULL
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS groups_test (
                id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                parent_id INT UNSIGNED NOT NULL
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS link_user_group_test (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                group_id INT UNSIGNED NOT NULL
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS same_table_name (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
        $this->entityManager->getPdm()->exec('CREATE TABLE IF NOT EXISTS group_sub (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            )'
        );
    }

    private function createTablesWithExtraForeignKeys(): void
    {
        $this->createSameTables();
        $this->entityManager->getPdm()->exec('ALTER TABLE users_test ADD COLUMN extra_id INT UNSIGNED NULL');
        $this->entityManager->getPdm()->exec(
            'ALTER TABLE users_test ADD CONSTRAINT fk_users_test_extra_groups_test FOREIGN KEY (extra_id) REFERENCES groups_test(id)'
        );
    }
}
