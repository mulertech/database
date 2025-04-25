<?php

namespace MulerTech\Database\Tests\Migration;

use DateTime;
use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Migration\MigrationGenerator;
use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\Migration\Schema\SchemaDifference;
use MulerTech\Database\Tests\Files\Entity\Group;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MigrationGeneratorTest extends TestCase
{
    private SchemaComparer $schemaComparer;
    private string $migrationsDir;
    private DbMapping $dbMapping;
    private string $migrationDatetime = '202310011200';

    protected function setUp(): void
    {
        $this->migrationsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Migrations';
        // Create mocks for dependencies
        $this->schemaComparer = $this->createMock(SchemaComparer::class);
        $this->dbMapping = $this->createMock(DbMapping::class);
    }

    protected function tearDown(): void
    {
        // Clean up any generated migration files
        $migrationFiles = glob($this->migrationsDir . '/Migration*.php');
        foreach ($migrationFiles as $file) {
            unlink($file);
        }
    }

    public function testGenerateMigrationWithInvalidDatetime(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid datetime format. Expected: YYYYMMDDHHMM');

        new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping)
            ->generateMigration('invalid-datetime');
    }

    public function testConstructorThrowsExceptionWhenDirectoryDoesNotExist(): void
    {
        $nonExistentDir = '/root/non_existent';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Migration directory does not exist: $nonExistentDir");

        new MigrationGenerator($this->schemaComparer, $nonExistentDir, $this->dbMapping);
    }

    public function testGenerateMigrationReturnsNullWhenNoChanges(): void
    {
        $schemaDifference = $this->createMock(SchemaDifference::class);
        $schemaDifference->method('hasDifferences')->willReturn(false);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $this->assertNull($migrationGenerator->generateMigration($this->migrationDatetime));
    }

    public function testGenerateMigrationCreatesFileWithTableCreation(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addTableToCreate('users_test', User::class);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        // Configure mapping mocks to return necessary data
        $this->dbMapping->method('getPropertiesColumns')
            ->with(User::class)
            ->willReturn([
                'id' => 'id',
                'username' => 'username',
                'size' => 'size',
                'unit' => 'unit_id'
            ]);
        
        $this->dbMapping->method('getColumnType')
            ->willReturnCallback(function($class, $property) {
                if ($class === User::class) {
                    return match($property) {
                        'id' => 'int unsigned',
                        'username' => 'varchar(255)',
                        'size' => 'int',
                        'unit' => 'int unsigned',
                        default => null
                    };
                }
                return null;
            });
        
        $this->dbMapping->method('isNullable')
            ->willReturnCallback(function($class, $property) {
                if ($class === User::class) {
                    return match($property) {
                        'id' => false,
                        'username' => false,
                        'size' => true,
                        'unit' => false,
                        default => true
                    };
                }
                return true;
            });
        
        $this->dbMapping->method('getExtra')
            ->willReturnCallback(function($class, $property) {
                if ($class === User::class) {
                    return match($property) {
                        'id' => 'auto_increment',
                        default => null
                    };
                }
                return null;
            });
        
        $this->dbMapping->method('getColumnDefault')
            ->willReturnCallback(function($class, $property) {
                if ($class === User::class) {
                    return match($property) {
                        'username' => 'John',
                        default => null
                    };
                }
                return null;
            });
        
        $this->dbMapping->method('getColumnKey')
            ->willReturnCallback(function($class, $property) {
                if ($class === User::class) {
                    return match($property) {
                        'id' => 'PRI',
                        'unit' => 'MUL',
                        default => null
                    };
                }
                return null;
            });
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('CREATE TABLE `users_test`', $fileContent);
        $this->assertStringContainsString('`id` int unsigned NOT NULL auto_increment', $fileContent);
        $this->assertStringContainsString('`username` varchar(255) NOT NULL DEFAULT \'John\'', $fileContent);
        $this->assertStringContainsString('`size` int', $fileContent);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $fileContent);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("DROP TABLE IF EXISTS `users_test`");', $fileContent);
    }

    public function testGenerateMigrationWithTableDrop(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addTableToDrop('groups_test');
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("DROP TABLE IF EXISTS `groups_test`");', $fileContent);
        $this->assertStringContainsString('// Recreate dropped table groups_test', $fileContent);
    }

    public function testGenerateMigrationWithColumnAddition(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addColumnToAdd('users_test', 'email', [
            'COLUMN_TYPE' => 'varchar(255)',
            'IS_NULLABLE' => 'NO',
            'COLUMN_DEFAULT' => null
        ]);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('ALTER TABLE `users_test` ADD COLUMN `email` varchar(255) NOT NULL', $fileContent);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("ALTER TABLE `users_test` DROP COLUMN `email`");', $fileContent);
    }

    public function testGenerateMigrationWithColumnModification(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addColumnToModify('users_test', 'username', [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'varchar(255)'
            ],
            'IS_NULLABLE' => [
                'from' => 'YES',
                'to' => 'NO'
            ],
            'COLUMN_DEFAULT' => [
                'from' => null,
                'to' => 'User'
            ]
        ]);
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('ALTER TABLE `users_test` MODIFY COLUMN `username` varchar(255) NOT NULL DEFAULT \'User\'', $fileContent);
        $this->assertStringContainsString('// Restore modified column users_test.username', $fileContent);
    }

    public function testGenerateMigrationWithColumnDrop(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addColumnToDrop('users_test', 'old_column');
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("ALTER TABLE `users_test` DROP COLUMN `old_column`");', $fileContent);
        $this->assertStringContainsString('// Restore dropped column users_test.old_column', $fileContent);
    }

    public function testGenerateMigrationWithForeignKeyAddition(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToAdd('users_test', 'fk_users_test_unit_id_units_test', [
            'COLUMN_NAME' => 'unit_id',
            'REFERENCED_TABLE_NAME' => 'units_test',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => 'RESTRICT',
            'UPDATE_RULE' => 'CASCADE'
        ]);
        
        // Add column to avoid validation error (foreign key needs an existing column)
        $schemaDifference->addColumnToAdd('users_test', 'unit_id', [
            'COLUMN_TYPE' => 'int unsigned',
            'IS_NULLABLE' => 'NO'
        ]);
        
        // Add referenced table to avoid validation error
        $schemaDifference->addTableToCreate('units_test', Unit::class);
        
        // Setup necessary DbMapping responses
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
        
        $this->dbMapping->method('getColumnType')
            ->willReturnCallback(function($class, $property) {
                if ($class === Unit::class) {
                    return match($property) {
                        'id' => 'int unsigned',
                        'name' => 'varchar(255)',
                        default => null
                    };
                }
                return null;
            });
        
        $this->dbMapping->method('isNullable')
            ->willReturnCallback(function($class, $property) {
                if ($class === Unit::class) {
                    return match($property) {
                        'id' => false,
                        'name' => false,
                        default => true
                    };
                }
                return true;
            });
        
        $this->dbMapping->method('getExtra')
            ->willReturnCallback(function($class, $property) {
                if ($class === Unit::class && $property === 'id') {
                    return 'auto_increment';
                }
                return '';
            });
        
        $this->dbMapping->method('getColumnKey')
            ->willReturnCallback(function($class, $property) {
                if ($class === Unit::class && $property === 'id') {
                    return 'PRI';
                }
                return null;
            });
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('ALTER TABLE `users_test` ADD CONSTRAINT `fk_users_test_unit_id_units_test` FOREIGN KEY (`unit_id`) REFERENCES `units_test` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE', $fileContent);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("ALTER TABLE `users_test` DROP FOREIGN KEY `fk_users_test_unit_id_units_test`");', $fileContent);
    }

    public function testGenerateMigrationWithForeignKeyDrop(): void
    {
        $schemaDifference = new SchemaDifference();
        $schemaDifference->addForeignKeyToDrop('users_test', 'fk_users_test_unit_id_units_test');
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("ALTER TABLE `users_test` DROP FOREIGN KEY `fk_users_test_unit_id_units_test`");', $fileContent);
        $this->assertStringContainsString('// Restore dropped foreign key fk_users_test_unit_id_units_test', $fileContent);
    }

    public function testGenerateMigrationWithMultipleChanges(): void
    {
        $schemaDifference = new SchemaDifference();
        
        // Multiple types of changes
        $schemaDifference->addTableToCreate('groups_test', Group::class);
        $schemaDifference->addTableToDrop('old_table');
        $schemaDifference->addColumnToAdd('users_test', 'email', [
            'COLUMN_TYPE' => 'varchar(255)',
            'IS_NULLABLE' => 'NO'
        ]);
        $schemaDifference->addColumnToModify('users_test', 'username', [
            'COLUMN_TYPE' => [
                'from' => 'varchar(100)',
                'to' => 'varchar(255)'
            ]
        ]);
        $schemaDifference->addColumnToDrop('users_test', 'old_column');
        $schemaDifference->addForeignKeyToDrop('users_test', 'fk_old');
        
        // Setup necessary DbMapping responses for table creation
        $this->dbMapping->method('getPropertiesColumns')
            ->willReturnCallback(function($class) {
                if ($class === Group::class) {
                    return [
                        'id' => 'id',
                        'name' => 'name',
                        'parent' => 'parent_id'
                    ];
                }
                return [];
            });
        
        $this->dbMapping->method('getColumnType')
            ->willReturnCallback(function($class, $property) {
                if ($class === Group::class) {
                    return match($property) {
                        'id' => 'int unsigned',
                        'name' => 'varchar(255)',
                        'parent' => 'int unsigned',
                        default => null
                    };
                }
                return null;
            });
        
        $this->dbMapping->method('isNullable')
            ->willReturnCallback(function($class, $property) {
                if ($class === Group::class) {
                    return match($property) {
                        'id' => false,
                        'name' => false,
                        'parent' => true,
                        default => true
                    };
                }
                return true;
            });
        
        $this->dbMapping->method('getExtra')
            ->willReturnCallback(function($class, $property) {
                if ($class === Group::class && $property === 'id') {
                    return 'auto_increment';
                }
                return '';
            });
        
        $this->dbMapping->method('getColumnKey')
            ->willReturnCallback(function($class, $property) {
                if ($class === Group::class) {
                    return match($property) {
                        'id' => 'PRI',
                        'parent' => 'MUL',
                        default => null
                    };
                }
                return null;
            });
        
        $this->schemaComparer->method('compare')->willReturn($schemaDifference);
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        
        // Check for various SQL statements in the up() method
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("ALTER TABLE `users_test` DROP FOREIGN KEY `fk_old`");', $fileContent);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("ALTER TABLE `users_test` DROP COLUMN `old_column`");', $fileContent);
        $this->assertStringContainsString('CREATE TABLE `groups_test`', $fileContent);
        $this->assertStringContainsString('ALTER TABLE `users_test` ADD COLUMN `email` varchar(255) NOT NULL', $fileContent);
        $this->assertStringContainsString('ALTER TABLE `users_test` MODIFY COLUMN `username` varchar(255)', $fileContent);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("DROP TABLE IF EXISTS `old_table`");', $fileContent);
        
        // Check for down method rollback operations
        $this->assertStringContainsString('// Restore dropped foreign key fk_old', $fileContent);
        $this->assertStringContainsString('// Restore dropped column users_test.old_column', $fileContent);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec("DROP TABLE IF EXISTS `groups_test`");', $fileContent);
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
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot create table 'empty_table': Entity 'EmptyEntity' has no columns defined.");
        
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
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
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
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
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
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('ALTER TABLE `users_test` ADD COLUMN `status` enum(\'active\',\'inactive\') NOT NULL DEFAULT \'active\'', $fileContent);
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
        
        $migrationGenerator = new MigrationGenerator($this->schemaComparer, $this->migrationsDir, $this->dbMapping);
        
        $filePath = $migrationGenerator->generateMigration($this->migrationDatetime);
        
        $this->assertNotNull($filePath);
        $this->assertFileExists($filePath);
        
        $fileContent = file_get_contents($filePath);
        $this->assertStringContainsString('ALTER TABLE `users_test` MODIFY COLUMN `description` text NULL DEFAULT NULL', $fileContent);
    }
}
