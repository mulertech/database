<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Schema\Builder\ColumnDefinition;
use MulerTech\Database\Schema\Builder\ForeignKeyDefinition;
use MulerTech\Database\Schema\Builder\TableDefinition;
use MulerTech\Database\Schema\Types\ReferentialAction;
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
        $tableDefinition = new TableDefinition('users', true);
        
        $this->assertEquals('users', $tableDefinition->getTableName());
        $this->assertTrue($tableDefinition->isCreate());
        $this->assertEquals([], $tableDefinition->getColumns());
        $this->assertEquals([], $tableDefinition->getIndexes());
        $this->assertEquals([], $tableDefinition->getForeignKeys());
        $this->assertEquals([], $tableDefinition->getOptions());
    }

    /**
     * Test adding a column
     */
    public function testColumn(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $column = $tableDefinition->column('name');
        
        $this->assertInstanceOf(ColumnDefinition::class, $column);
        $this->assertArrayHasKey('name', $tableDefinition->getColumns());
        $this->assertSame($column, $tableDefinition->getColumns()['name']);
    }

    /**
     * Test dropping a column
     */
    public function testDropColumn(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $tableDefinition->dropColumn('old_column');
        
        $columns = $tableDefinition->getColumns();
        $this->assertArrayHasKey('old_column', $columns);
        $this->assertEquals(['drop' => true], $columns['old_column']);
    }

    /**
     * Test setting a primary key with a single column
     */
    public function testPrimaryKeySingleColumn(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $tableDefinition->primaryKey('id');
        
        $indexes = $tableDefinition->getIndexes();
        $this->assertArrayHasKey('PRIMARY', $indexes);
        $this->assertEquals([
            'type' => 'PRIMARY KEY',
            'columns' => ['id']
        ], $indexes['PRIMARY']);
    }

    /**
     * Test setting a primary key with multiple columns
     */
    public function testPrimaryKeyMultipleColumns(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $tableDefinition->primaryKey(['user_id', 'post_id']);
        
        $indexes = $tableDefinition->getIndexes();
        $this->assertArrayHasKey('PRIMARY', $indexes);
        $this->assertEquals([
            'type' => 'PRIMARY KEY',
            'columns' => ['user_id', 'post_id']
        ], $indexes['PRIMARY']);
    }

    /**
     * Test adding a foreign key
     */
    public function testForeignKey(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $foreignKey = $tableDefinition->foreignKey('fk_posts_users');
        
        $this->assertInstanceOf(ForeignKeyDefinition::class, $foreignKey);
        $foreignKeys = $tableDefinition->getForeignKeys();
        $this->assertArrayHasKey('fk_posts_users', $foreignKeys);
        $this->assertSame($foreignKey, $foreignKeys['fk_posts_users']);
    }

    /**
     * Test setting engine option
     */
    public function testEngine(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $result = $tableDefinition->engine('InnoDB');
        
        $this->assertSame($tableDefinition, $result);
        $options = $tableDefinition->getOptions();
        $this->assertArrayHasKey('ENGINE', $options);
        $this->assertEquals('InnoDB', $options['ENGINE']);
    }

    /**
     * Test setting charset option
     */
    public function testCharset(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $result = $tableDefinition->charset('utf8mb4');
        
        $this->assertSame($tableDefinition, $result);
        $options = $tableDefinition->getOptions();
        $this->assertArrayHasKey('CHARSET', $options);
        $this->assertEquals('utf8mb4', $options['CHARSET']);
    }

    /**
     * Test setting collation option
     */
    public function testCollation(): void
    {
        $tableDefinition = new TableDefinition('posts');
        $result = $tableDefinition->collation('utf8mb4_unicode_ci');
        
        $this->assertSame($tableDefinition, $result);
        $options = $tableDefinition->getOptions();
        $this->assertArrayHasKey('COLLATE', $options);
        $this->assertEquals('utf8mb4_unicode_ci', $options['COLLATE']);
    }

    /**
     * Test toSql method for creating tables
     */
    public function testToSqlCreate(): void
    {
        $tableDefinition = new TableDefinition('users', true);
        $tableDefinition->column('id')->integer()->notNull()->autoIncrement();
        $tableDefinition->primaryKey('id');
        
        $sql = $tableDefinition->toSql();
        
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('users', $sql);
        $this->assertStringContainsString('PRIMARY KEY', $sql);
    }

    /**
     * Test toSql method for altering tables
     */
    public function testToSqlAlter(): void
    {
        $tableDefinition = new TableDefinition('users', false);
        $tableDefinition->column('email')->string(255)->notNull();
        
        $sql = $tableDefinition->toSql();
        
        $this->assertStringContainsString('ALTER TABLE', $sql);
        $this->assertStringContainsString('users', $sql);
        $this->assertStringContainsString('ADD COLUMN', $sql);
    }

    /**
     * Test a complete table definition with all features
     */
    public function testCompleteTableDefinition(): void
    {
        $tableDefinition = new TableDefinition('articles', true);
        
        // Add columns
        $tableDefinition->column('id')->integer()->notNull()->autoIncrement();
        $tableDefinition->column('title')->string(200)->notNull();
        $tableDefinition->column('content')->text();
        $tableDefinition->column('user_id')->integer()->notNull();
        $tableDefinition->column('created_at')->datetime()->notNull()->default('CURRENT_TIMESTAMP');
        
        // Add primary key
        $tableDefinition->primaryKey('id');
        
        // Add foreign key
        $tableDefinition->foreignKey('fk_articles_user')
            ->columns('user_id')
            ->references('users', 'id')
            ->onDelete(ReferentialAction::CASCADE)
            ->onUpdate(ReferentialAction::CASCADE);
        
        // Add options
        $tableDefinition->engine('InnoDB')
            ->charset('utf8mb4')
            ->collation('utf8mb4_unicode_ci');
        
        $sql = $tableDefinition->toSql();
        
        $this->assertStringContainsString('CREATE TABLE `articles`', $sql);
        $this->assertStringContainsString('`id` INT NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`title` VARCHAR(200) NOT NULL', $sql);
        $this->assertStringContainsString('`content` TEXT', $sql);
        $this->assertStringContainsString('`user_id` INT NOT NULL', $sql);
        $this->assertStringContainsString('`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        $this->assertStringContainsString('FOREIGN KEY', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
        $this->assertStringContainsString('DEFAULT CHARSET=utf8mb4', $sql);
        $this->assertStringContainsString('COLLATE=utf8mb4_unicode_ci', $sql);
    }
}
