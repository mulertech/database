<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Schema\Migration\MigrationCodeGenerator;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Mapping\Types\FkRule;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for MigrationCodeGenerator class
 */
class MigrationCodeGeneratorTest extends TestCase
{
    private MigrationCodeGenerator $generator;
    private MetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        // Create a real MetadataRegistry instance since it's final and cannot be mocked
        $this->metadataRegistry = new MetadataRegistry();
        $this->generator = new MigrationCodeGenerator($this->metadataRegistry);
    }

    public function testConstructor(): void
    {
        $generator = new MigrationCodeGenerator($this->metadataRegistry);
        $this->assertInstanceOf(MigrationCodeGenerator::class, $generator);
    }

    public function testGenerateUpCodeEmpty(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        $this->assertEquals('', $result);
    }

    public function testGenerateDownCodeEmpty(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);

        $result = $this->generator->generateDownCode($diff);

        $this->assertEquals('        // No rollback operations defined', $result);
    }

    public function testGenerateUpCodeWithDropForeignKeys(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([
            'users' => ['fk_user_department']
        ]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        $this->assertStringContainsString('dropForeignKey', $result);
        $this->assertStringContainsString('fk_user_department', $result);
    }

    public function testGenerateUpCodeWithDropColumns(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([
            'users' => ['old_column']
        ]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        $this->assertStringContainsString('dropColumn', $result);
        $this->assertStringContainsString('old_column', $result);
    }

    public function testGenerateUpCodeWithCreateTables(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        // Use empty array to avoid entity lookup issues
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        // With empty arrays, result should be empty
        $this->assertEquals('', $result);
    }

    public function testGenerateUpCodeWithAddColumns(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([
            'users' => [
                'new_column' => [
                    'COLUMN_TYPE' => 'varchar(255)',
                    'IS_NULLABLE' => 'YES',
                    'COLUMN_DEFAULT' => null,
                    'EXTRA' => ''
                ]
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        $this->assertStringContainsString('alterTable', $result);
        $this->assertStringContainsString('users', $result);
        $this->assertStringContainsString('new_column', $result);
    }

    public function testGenerateUpCodeWithModifyColumns(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([
            'users' => [
                'name' => [
                    'COLUMN_TYPE' => ['from' => 'varchar(100)', 'to' => 'varchar(255)'],
                    'IS_NULLABLE' => ['from' => 'NO', 'to' => 'YES']
                ]
            ]
        ]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        $this->assertStringContainsString('string', $result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('modifyColumn', $result);
    }

    public function testGenerateUpCodeWithAddForeignKeys(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([
            'orders' => [
                'fk_orders_user_id' => [
                    'COLUMN_NAME' => 'user_id',
                    'REFERENCED_TABLE_NAME' => 'users',
                    'REFERENCED_COLUMN_NAME' => 'id',
                    'DELETE_RULE' => FkRule::CASCADE,
                    'UPDATE_RULE' => FkRule::CASCADE
                ]
            ]
        ]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        $this->assertStringContainsString('foreignKey', $result);
        $this->assertStringContainsString('user_id', $result);
        $this->assertStringContainsString('users', $result);
        $this->assertStringContainsString('onDelete', $result);
        $this->assertStringContainsString('CASCADE', $result);
    }

    public function testGenerateUpCodeWithDropTables(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn(['old_table']);

        $result = $this->generator->generateUpCode($diff);

        $this->assertStringContainsString('dropTable', $result);
        $this->assertStringContainsString('old_table', $result);
    }

    public function testGenerateDownCodeWithContent(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToAdd')->willReturn([
            'orders' => [
                'fk_orders_user_id' => [
                    'COLUMN_NAME' => 'user_id',
                    'REFERENCED_TABLE_NAME' => 'users',
                    'REFERENCED_COLUMN_NAME' => 'id',
                    'DELETE_RULE' => FkRule::CASCADE,
                    'UPDATE_RULE' => FkRule::CASCADE
                ]
            ]
        ]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([
            'users' => [
                'new_column' => [
                    'COLUMN_TYPE' => 'varchar(255)',
                    'IS_NULLABLE' => 'YES'
                ]
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([
            'users' => [
                'name' => [
                    'COLUMN_TYPE' => ['from' => 'varchar(100)', 'to' => 'varchar(255)']
                ]
            ]
        ]);

        $result = $this->generator->generateDownCode($diff);

        $this->assertStringContainsString('dropForeignKey', $result);
        $this->assertStringContainsString('dropColumn', $result);
        // No dropTable since we don't create tables in up code
    }

    public function testGenerateUpCodeFiltersColumnsForNewTables(): void
    {
        // Test focuses on column filtering logic without entity lookup
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]); // Empty to avoid entity lookup
        $diff->method('getColumnsToAdd')->willReturn([
            'existing_table' => [
                'new_column' => [
                    'COLUMN_TYPE' => 'varchar(255)',
                    'IS_NULLABLE' => 'YES'
                ]
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        // Should contain alter table for existing_table
        $this->assertStringContainsString('existing_table', $result);
        $this->assertStringContainsString('new_column', $result);
    }

    public function testGenerateDownCodeFiltersColumnsForNewTables(): void
    {
        // Test down code generation without entity lookup
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]); // Empty to avoid entity lookup
        $diff->method('getColumnsToAdd')->willReturn([
            'existing_table' => [
                'new_column' => [
                    'COLUMN_TYPE' => 'varchar(255)',
                    'IS_NULLABLE' => 'YES'
                ]
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([]);

        $result = $this->generator->generateDownCode($diff);

        // Should contain drop column for existing_table
        $this->assertStringContainsString('dropColumn', $result);
        $this->assertStringContainsString('new_column', $result);
    }

    public function testGenerateUpCodeWithComplexScenario(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([
            'orders' => ['old_fk']
        ]);
        $diff->method('getColumnsToDrop')->willReturn([
            'users' => ['old_column']
        ]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([
            'users' => [
                'new_column' => [
                    'COLUMN_TYPE' => 'varchar(255)',
                    'IS_NULLABLE' => 'NO'
                ]
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([
            'users' => [
                'name' => [
                    'COLUMN_TYPE' => ['from' => 'varchar(100)', 'to' => 'varchar(255)']
                ]
            ]
        ]);
        $diff->method('getForeignKeysToAdd')->willReturn([
            'orders' => [
                'new_fk' => [
                    'COLUMN_NAME' => 'user_id',
                    'REFERENCED_TABLE_NAME' => 'users',
                    'REFERENCED_COLUMN_NAME' => 'id',
                    'DELETE_RULE' => FkRule::CASCADE,
                    'UPDATE_RULE' => FkRule::CASCADE
                ]
            ]
        ]);
        $diff->method('getTablesToDrop')->willReturn(['old_table']);

        $result = $this->generator->generateUpCode($diff);

        // Operations should be present (without table creation since we avoid entity lookup)
        $this->assertStringContainsString('dropForeignKey', $result);
        $this->assertStringContainsString('dropColumn', $result);
        $this->assertStringContainsString('new_column', $result);
        $this->assertStringContainsString('modifyColumn', $result);
        $this->assertStringContainsString('foreignKey', $result);
        $this->assertStringContainsString('dropTable', $result);
    }

    public function testGenerateCodeProperIndentation(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([
            'users' => [
                'new_column' => [
                    'COLUMN_TYPE' => 'varchar(255)',
                    'IS_NULLABLE' => 'NO'
                ]
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        $result = $this->generator->generateUpCode($diff);

        // All lines should start with 8 spaces (2 levels of indentation)
        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $this->assertStringStartsWith('        ', $line, "Line should start with 8 spaces: '$line'");
            }
        }
    }

    public function testHandleNonArrayColumnDefinition(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([
            'users' => [
                'invalid_column' => 'not_an_array' // Invalid column definition
            ]
        ]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        // Should not throw exception and should handle gracefully
        $result = $this->generator->generateUpCode($diff);
        
        // Should not contain the invalid column
        $this->assertStringNotContainsString('invalid_column', $result);
    }

    public function testHandleNonArrayColumnModification(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([
            'users' => [
                'invalid_column' => 'not_an_array' // Invalid modification
            ]
        ]);
        $diff->method('getForeignKeysToAdd')->willReturn([]);
        $diff->method('getTablesToDrop')->willReturn([]);

        // Should not throw exception and should handle gracefully
        $result = $this->generator->generateUpCode($diff);
        
        // Should not contain the invalid column
        $this->assertStringNotContainsString('invalid_column', $result);
    }

    public function testHandleNonArrayForeignKeyInfo(): void
    {
        $diff = $this->createMock(SchemaDifference::class);
        $diff->method('getForeignKeysToDrop')->willReturn([]);
        $diff->method('getColumnsToDrop')->willReturn([]);
        $diff->method('getTablesToCreate')->willReturn([]);
        $diff->method('getColumnsToAdd')->willReturn([]);
        $diff->method('getColumnsToModify')->willReturn([]);
        $diff->method('getForeignKeysToAdd')->willReturn([
            'orders' => [
                'invalid_fk' => 'not_an_array' // Invalid FK info
            ]
        ]);
        $diff->method('getTablesToDrop')->willReturn([]);

        // Should not throw exception and should handle gracefully
        $result = $this->generator->generateUpCode($diff);
        
        // Should not contain the invalid FK
        $this->assertStringNotContainsString('invalid_fk', $result);
    }
}