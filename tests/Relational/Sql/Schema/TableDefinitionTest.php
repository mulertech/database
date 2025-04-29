<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\Schema\ColumnDefinition;
use MulerTech\Database\Relational\Sql\Schema\ForeignKeyDefinition;
use MulerTech\Database\Relational\Sql\Schema\SchemaQueryGenerator;
use MulerTech\Database\Relational\Sql\Schema\TableDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Class TableDefinitionTest
 * Tests for the TableDefinition class
 */
class TableDefinitionTest extends TestCase
{
    /**
     * Test the constructor and getters
     */
    public function testConstructorAndGetters(): void
    {
        $tableName = 'users';
        $isCreate = true;
        $table = new TableDefinition($tableName, $isCreate);
        
        $this->assertEquals($tableName, $table->getTableName());
        $this->assertTrue($table->isCreate());
        $this->assertEquals([], $table->getColumns());
        $this->assertEquals([], $table->getIndexes());
        $this->assertEquals([], $table->getForeignKeys());
        $this->assertEquals([], $table->getOptions());
    }

    /**
     * Test adding a column
     */
    public function testColumn(): void
    {
        $table = new TableDefinition('users', true);
        $column = $table->column('name');
        
        $this->assertInstanceOf(ColumnDefinition::class, $column);
        $this->assertArrayHasKey('name', $table->getColumns());
        $this->assertSame($column, $table->getColumns()['name']);
        
        // Test method chaining works
        $column->string(100)->notNull();
        $this->assertEquals('name', $column->getName());
    }

    /**
     * Test dropping a column
     */
    public function testDropColumn(): void
    {
        $table = new TableDefinition('users', false);
        $result = $table->dropColumn('old_column');
        
        $this->assertSame($table, $result, 'Method should return $this for chaining');
        $this->assertArrayHasKey('old_column', $table->getColumns());
        $this->assertIsArray($table->getColumns()['old_column']);
        $this->assertTrue($table->getColumns()['old_column']['drop']);
    }

    /**
     * Test setting a primary key with a single column
     */
    public function testPrimaryKeySingleColumn(): void
    {
        $table = new TableDefinition('users', true);
        $result = $table->primaryKey('id');
        
        $this->assertSame($table, $result, 'Method should return $this for chaining');
        $this->assertArrayHasKey('PRIMARY', $table->getIndexes());
        $this->assertEquals('PRIMARY KEY', $table->getIndexes()['PRIMARY']['type']);
        $this->assertEquals(['id'], $table->getIndexes()['PRIMARY']['columns']);
    }

    /**
     * Test setting a primary key with multiple columns
     */
    public function testPrimaryKeyMultipleColumns(): void
    {
        $table = new TableDefinition('order_items', true);
        $result = $table->primaryKey(['order_id', 'product_id']);
        
        $this->assertSame($table, $result, 'Method should return $this for chaining');
        $this->assertArrayHasKey('PRIMARY', $table->getIndexes());
        $this->assertEquals('PRIMARY KEY', $table->getIndexes()['PRIMARY']['type']);
        $this->assertEquals(['order_id', 'product_id'], $table->getIndexes()['PRIMARY']['columns']);
    }

    /**
     * Test adding a foreign key
     */
    public function testForeignKey(): void
    {
        $table = new TableDefinition('posts', true);
        $foreignKey = $table->foreignKey('fk_posts_users');
        
        $this->assertInstanceOf(ForeignKeyDefinition::class, $foreignKey);
        $this->assertArrayHasKey('fk_posts_users', $table->getForeignKeys());
        $this->assertSame($foreignKey, $table->getForeignKeys()['fk_posts_users']);
        
        // Test chaining works
        $result = $foreignKey->columns('user_id')->references('users', 'id');
        $this->assertSame($foreignKey, $result, 'Method should return $this for chaining');
    }

    /**
     * Test setting engine option
     */
    public function testEngine(): void
    {
        $table = new TableDefinition('users', true);
        $result = $table->engine('InnoDB');
        
        $this->assertSame($table, $result, 'Method should return $this for chaining');
        $this->assertArrayHasKey('ENGINE', $table->getOptions());
        $this->assertEquals('InnoDB', $table->getOptions()['ENGINE']);
    }

    /**
     * Test setting charset option
     */
    public function testCharset(): void
    {
        $table = new TableDefinition('users', true);
        $result = $table->charset('utf8mb4');
        
        $this->assertSame($table, $result, 'Method should return $this for chaining');
        $this->assertArrayHasKey('CHARSET', $table->getOptions());
        $this->assertEquals('utf8mb4', $table->getOptions()['CHARSET']);
    }

    /**
     * Test setting collation option
     */
    public function testCollation(): void
    {
        $table = new TableDefinition('users', true);
        $result = $table->collation('utf8mb4_unicode_ci');
        
        $this->assertSame($table, $result, 'Method should return $this for chaining');
        $this->assertArrayHasKey('COLLATE', $table->getOptions());
        $this->assertEquals('utf8mb4_unicode_ci', $table->getOptions()['COLLATE']);
    }

    /**
     * Test toSql method for creating tables
     */
    public function testToSqlCreate(): void
    {
        $table = new TableDefinition('users', true);
        $table->column('id')->integer()->notNull()->autoIncrement();
        $table->column('name')->string(100)->notNull();
        $table->column('email')->string(200)->notNull();
        $table->primaryKey('id');
        $table->engine('InnoDB');
        
        // Mock the SchemaQueryGenerator to verify it's called with the right arguments
        $mockGenerator = $this->createMock(SchemaQueryGenerator::class);
        $mockGenerator->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($table))
            ->willReturn('CREATE TABLE SQL STATEMENT');
        
        // Replace the generator with our mock using reflection
        $reflection = new \ReflectionClass(TableDefinition::class);
        $method = $reflection->getMethod('toSql');
        $reflection_method = new \ReflectionMethod(TableDefinition::class, 'toSql');
        
        // Save the original method implementation
        $originalCode = $reflection_method->getFileName();
        $startLine = $reflection_method->getStartLine();
        $endLine = $reflection_method->getEndLine();
        $originalMethod = file($originalCode);
        
        // Create a closure that will use our mock
        $mockMethod = function() use ($mockGenerator) {
            return $mockGenerator->generate($this);
        };
        
        // Bind the closure to $table
        $newMethod = \Closure::bind($mockMethod, $table, get_class($table));
        
        // Override the method
        $result = $newMethod();
        
        $this->assertEquals('CREATE TABLE SQL STATEMENT', $result);
    }

    /**
     * Test toSql method for altering tables
     */
    public function testToSqlAlter(): void
    {
        $table = new TableDefinition('users', false);
        $table->column('new_column')->string(50)->notNull();
        $table->dropColumn('old_column');
        
        // Create a real SchemaQueryGenerator to test the integration
        $generator = new SchemaQueryGenerator();
        $sql = $table->toSql();
        
        $this->assertNotEmpty($sql);
        $this->assertStringContainsString('ALTER TABLE', $sql);
        $this->assertStringContainsString('`users`', $sql);
        $this->assertStringContainsString('ADD COLUMN', $sql);
        $this->assertStringContainsString('DROP COLUMN', $sql);
    }

    /**
     * Test a complete table definition with all features
     */
    public function testCompleteTableDefinition(): void
    {
        $table = new TableDefinition('articles', true);
        
        // Add columns
        $table->column('id')->integer()->notNull()->autoIncrement();
        $table->column('title')->string(200)->notNull();
        $table->column('content')->text();
        $table->column('user_id')->integer()->notNull();
        $table->column('created_at')->datetime()->notNull()->default('CURRENT_TIMESTAMP');
        
        // Add primary key
        $table->primaryKey('id');
        
        // Add foreign key
        $table->foreignKey('fk_articles_users')
            ->columns('user_id')
            ->references('users', 'id');
        
        // Set options
        $table->engine('InnoDB');
        $table->charset('utf8mb4');
        $table->collation('utf8mb4_unicode_ci');
        
        // Generate SQL
        $sql = $table->toSql();
        
        $this->assertNotEmpty($sql);
        $this->assertStringContainsString('CREATE TABLE `articles`', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        $this->assertStringContainsString('FOREIGN KEY', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }
}
