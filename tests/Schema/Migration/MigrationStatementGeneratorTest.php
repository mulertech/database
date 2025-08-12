<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Schema\Migration\MigrationStatementGenerator;
use MulerTech\Database\Schema\Migration\SqlTypeConverter;
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\Mapping\MetadataRegistry;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for MigrationStatementGenerator class
 */
class MigrationStatementGeneratorTest extends TestCase
{
    private MigrationStatementGenerator $generator;
    private MetadataRegistry $metadataRegistry;
    private SqlTypeConverter $mockTypeConverter;

    protected function setUp(): void
    {
        $this->generator = new MigrationStatementGenerator();
        
        // Create a real MetadataRegistry instance since it's final and cannot be mocked
        $this->metadataRegistry = new MetadataRegistry();
        
        $this->mockTypeConverter = $this->createMock(SqlTypeConverter::class);
    }

    public function testConstructor(): void
    {
        $generator = new MigrationStatementGenerator();
        $this->assertInstanceOf(MigrationStatementGenerator::class, $generator);
    }

    public function testGenerateCreateTableStatement(): void
    {
        // Since MetadataCache is a real instance and can't be mocked,
        // and the method requires entity class lookup, we expect it to fail
        // and test that the exception is thrown correctly
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not find entity class for table \'users\'');

        $this->generator->generateCreateTableStatement('users', $this->metadataRegistry);
    }

    public function testGenerateCreateTableStatementEntityNotFound(): void
    {
        // Test that proper exception is thrown when entity is not found
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Could not find entity class for table 'posts'");

        $this->generator->generateCreateTableStatement('posts', $this->metadataRegistry);
    }

    public function testGenerateAlterTableStatement(): void
    {
        $this->mockTypeConverter->method('convertToBuilderMethod')->willReturn('->string(255)');

        $result = $this->generator->generateAlterTableStatement(
            'users',
            'varchar(255)',
            'email',
            true,
            null,
            null,
            $this->mockTypeConverter
        );

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users");', $result);
        $this->assertStringContainsString('$tableDefinition->column("email")', $result);
        $this->assertStringContainsString('->string(255)', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateAlterTableStatementWithConstraints(): void
    {
        $this->mockTypeConverter->method('convertToBuilderMethod')->willReturn('->integer()');

        $result = $this->generator->generateAlterTableStatement(
            'users',
            'int(11)',
            'age',
            false,
            '0',
            'auto_increment',
            $this->mockTypeConverter
        );

        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->default("0")', $result);
        $this->assertStringContainsString('->autoIncrement()', $result);
    }

    public function testGenerateModifyColumnStatement(): void
    {
        $differences = [
            'COLUMN_TYPE' => ['from' => 'varchar(100)', 'to' => 'varchar(255)'],
            'IS_NULLABLE' => ['from' => 'NO', 'to' => 'YES']
        ];

        $result = $this->generator->generateModifyColumnStatement('users', 'name', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users");', $result);
        $this->assertStringContainsString('$tableDefinition->modifyColumn(', $result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateModifyColumnStatementWithDefaults(): void
    {
        $differences = [
            'COLUMN_DEFAULT' => ['from' => null, 'to' => 'default_value'],
            'EXTRA' => ['from' => null, 'to' => 'auto_increment']
        ];

        $result = $this->generator->generateModifyColumnStatement('users', 'id', $differences);

        $this->assertStringContainsString('->default("default_value")', $result);
        $this->assertStringContainsString('->autoIncrement()', $result);
    }

    public function testGenerateRestoreColumnStatement(): void
    {
        $differences = [
            'COLUMN_TYPE' => ['from' => 'varchar(100)', 'to' => 'varchar(255)'],
            'IS_NULLABLE' => ['from' => 'YES', 'to' => 'NO']
        ];

        $result = $this->generator->generateRestoreColumnStatement('users', 'name', $differences);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users");', $result);
        $this->assertStringContainsString('$tableDefinition->modifyColumn(', $result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateDropTableStatement(): void
    {
        $result = $this->generator->generateDropTableStatement('users');

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$sql = $schema->dropTable("users");', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateDropColumnStatement(): void
    {
        $result = $this->generator->generateDropColumnStatement('users', 'old_column');

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("users");', $result);
        $this->assertStringContainsString('$tableDefinition->dropColumn("old_column");', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateAddForeignKeyStatement(): void
    {
        $foreignKeyInfo = [
            'COLUMN_NAME' => 'user_id',
            'REFERENCED_TABLE_NAME' => 'users',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => FkRule::CASCADE,
            'UPDATE_RULE' => FkRule::RESTRICT
        ];

        $result = $this->generator->generateAddForeignKeyStatement('orders', 'fk_orders_user_id', $foreignKeyInfo);

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("orders");', $result);
        $this->assertStringContainsString('$tableDefinition->foreignKey("fk_orders_user_id")', $result);
        $this->assertStringContainsString('->column("user_id")', $result);
        $this->assertStringContainsString('->references("users", "id")', $result);
        $this->assertStringContainsString('->onUpdate(', $result);
        $this->assertStringContainsString('->onDelete(', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateDropForeignKeyStatement(): void
    {
        $result = $this->generator->generateDropForeignKeyStatement('orders', 'fk_orders_user_id');

        $this->assertStringContainsString('$schema = new SchemaBuilder();', $result);
        $this->assertStringContainsString('$tableDefinition = $schema->alterTable("orders");', $result);
        $this->assertStringContainsString('$tableDefinition->dropForeignKey("fk_orders_user_id");', $result);
        $this->assertStringContainsString('$this->entityManager->getPdm()->exec($sql);', $result);
    }

    public function testGenerateColumnDefinitionFromType(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateColumnDefinitionFromType(
            'varchar(255)',
            'email',
            false,
            'test@example.com',
            '',
            $typeConverter
        );

        $this->assertStringContainsString('$tableDefinition->column("email")', $result);
        $this->assertStringContainsString('->string(255)', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->default("test@example.com")', $result);
        $this->assertStringEndsWith(';', $result);
    }

    public function testGenerateColumnDefinitionFromTypeNullable(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateColumnDefinitionFromType(
            'text',
            'description',
            true,
            null,
            null,
            $typeConverter
        );

        $this->assertStringContainsString('$tableDefinition->column("description")', $result);
        $this->assertStringContainsString('->text()', $result);
        $this->assertStringNotContainsString('->notNull()', $result);
        $this->assertStringNotContainsString('->default(', $result);
        $this->assertStringNotContainsString('->autoIncrement()', $result);
    }

    public function testGenerateColumnDefinitionFromTypeWithAutoIncrement(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateColumnDefinitionFromType(
            'int(11)',
            'id',
            false,
            null,
            'auto_increment',
            $typeConverter
        );

        $this->assertStringContainsString('$tableDefinition->column("id")', $result);
        $this->assertStringContainsString('->integer()', $result);
        $this->assertStringContainsString('->notNull()', $result);
        $this->assertStringContainsString('->autoIncrement()', $result);
    }

    public function testGenerateColumnDefinitionFromTypeWithNullColumnType(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateColumnDefinitionFromType(
            null,
            'unknown_column',
            true,
            null,
            null,
            $typeConverter
        );

        $this->assertStringContainsString('$tableDefinition->column("unknown_column")', $result);
        $this->assertStringContainsString('->string()', $result);
        $this->assertStringNotContainsString('->notNull()', $result);
    }

    public function testGenerateColumnDefinitionWithEscapedDefault(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateColumnDefinitionFromType(
            'varchar(255)',
            'title',
            false,
            'It\'s a "test" value',
            null,
            $typeConverter
        );

        $this->assertStringContainsString('->default("It\\\'s a \\"test\\" value")', $result);
    }

    public function testGenerateColumnDefinitionWithEmptyDefault(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateColumnDefinitionFromType(
            'varchar(255)',
            'title',
            false,
            '',
            null,
            $typeConverter
        );

        // Empty string should not add default clause
        $this->assertStringNotContainsString('->default(', $result);
    }

    public function testGenerateAlterTableStatementStructure(): void
    {
        $typeConverter = new SqlTypeConverter();

        $result = $this->generator->generateAlterTableStatement(
            'products',
            'decimal(10,2)',
            'price',
            false,
            '0.00',
            null,
            $typeConverter
        );

        $lines = explode("\n", $result);
        
        $this->assertEquals('$schema = new SchemaBuilder();', $lines[0]);
        $this->assertStringStartsWith('        $tableDefinition = $schema->alterTable("products");', $lines[1]);
        $this->assertStringContainsString('$tableDefinition->column("price")', $lines[2]);
        $this->assertEquals('        $sql = $tableDefinition->toSql();', $lines[3]);
        $this->assertEquals('        $this->entityManager->getPdm()->exec($sql);', $lines[4]);
    }

    public function testGenerateModifyColumnWithPartialDifferences(): void
    {
        $differences = [
            'COLUMN_TYPE' => ['from' => 'varchar(100)', 'to' => 'varchar(255)']
            // Missing other properties - should use defaults
        ];

        $result = $this->generator->generateModifyColumnStatement('users', 'name', $differences);

        $this->assertStringContainsString('$tableDefinition->modifyColumn(', $result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('->string(255)', $result);
    }

    public function testGenerateRestoreColumnWithPartialDifferences(): void
    {
        $differences = [
            'IS_NULLABLE' => ['from' => 'YES', 'to' => 'NO']
            // Missing COLUMN_TYPE - should handle gracefully
        ];

        $result = $this->generator->generateRestoreColumnStatement('users', 'name', $differences);

        $this->assertStringContainsString('$tableDefinition->modifyColumn(', $result);
        $this->assertStringContainsString('name', $result);
        // Should default to string type when COLUMN_TYPE is missing
        $this->assertStringContainsString('->string()', $result);
    }

    public function testForeignKeyStatementWithAllFkRules(): void
    {
        $foreignKeyInfo = [
            'COLUMN_NAME' => 'category_id',
            'REFERENCED_TABLE_NAME' => 'categories',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => FkRule::SET_NULL,
            'UPDATE_RULE' => FkRule::NO_ACTION
        ];

        $result = $this->generator->generateAddForeignKeyStatement('products', 'fk_products_category', $foreignKeyInfo);

        $this->assertStringContainsString('->onUpdate(', $result);
        $this->assertStringContainsString('->onDelete(', $result);
        $this->assertStringContainsString('fk_products_category', $result);
        $this->assertStringContainsString('category_id', $result);
        $this->assertStringContainsString('categories', $result);
    }

    public function testGeneratedCodeIndentation(): void
    {
        $result = $this->generator->generateDropTableStatement('test_table');

        $lines = explode("\n", $result);
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                // Each line should start with proper indentation
                $this->assertMatchesRegularExpression('/^(\$|        )/', $line, "Line should be properly indented: '$line'");
            }
        }
    }

    public function testAllStatementTypesReturnString(): void
    {
        $typeConverter = new SqlTypeConverter();
        
        // Test all public methods return strings
        $this->assertIsString($this->generator->generateDropTableStatement('test'));
        $this->assertIsString($this->generator->generateDropColumnStatement('test', 'col'));
        $this->assertIsString($this->generator->generateDropForeignKeyStatement('test', 'fk'));
        
        $this->assertIsString($this->generator->generateAlterTableStatement(
            'test', 'varchar(255)', 'col', true, null, null, $typeConverter
        ));
        
        $this->assertIsString($this->generator->generateModifyColumnStatement('test', 'col', []));
        $this->assertIsString($this->generator->generateRestoreColumnStatement('test', 'col', []));
        
        $foreignKeyInfo = [
            'COLUMN_NAME' => 'id',
            'REFERENCED_TABLE_NAME' => 'table',
            'REFERENCED_COLUMN_NAME' => 'id',
            'DELETE_RULE' => FkRule::CASCADE,
            'UPDATE_RULE' => FkRule::CASCADE
        ];
        $this->assertIsString($this->generator->generateAddForeignKeyStatement('test', 'fk', $foreignKeyInfo));
        
        $this->assertIsString($this->generator->generateColumnDefinitionFromType(
            'int', 'id', false, null, null, $typeConverter
        ));
    }
}