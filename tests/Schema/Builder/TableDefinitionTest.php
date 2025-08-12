<?php

namespace MulerTech\Database\Tests\Schema\Builder;

use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\Schema\Builder\ColumnDefinition;
use MulerTech\Database\Schema\Builder\ForeignKeyDefinition;
use MulerTech\Database\Schema\Builder\TableDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Class TableDefinitionTest
 * Tests for the TableDefinition class
 */
class TableDefinitionTest extends TestCase
{
    /**
     * Test the constructor and basic properties
     */
    public function testConstructorAndConstants(): void
    {
        $tableDefinition = new TableDefinition('users', TableDefinition::ACTION_CREATE);

        $this->assertEquals(TableDefinition::ACTION_CREATE, 'CREATE');
        $this->assertEquals(TableDefinition::ACTION_ALTER, 'ALTER');
    }

    /**
     * Test adding a column
     */
    public function testColumn(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $column = $tableDefinition->column('name');
        
        $this->assertInstanceOf(ColumnDefinition::class, $column);
        $this->assertEquals('name', $column->getName());
    }

    /**
     * Test dropping a column
     */
    public function testDropColumn(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_ALTER);
        $result = $tableDefinition->dropColumn('old_column');

        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('DROP COLUMN `old_column`', $sql);
    }

    /**
     * Test setting a primary key with a single column
     */
    public function testPrimaryKeySingleColumn(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $tableDefinition->column('id')->integer()->notNull()->autoIncrement();
        $result = $tableDefinition->primaryKey('id');

        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }

    /**
     * Test setting a primary key with multiple columns
     */
    public function testPrimaryKeyMultipleColumns(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $tableDefinition->column('user_id')->integer()->notNull();
        $tableDefinition->column('post_id')->integer()->notNull();
        $result = $tableDefinition->primaryKey('user_id', 'post_id');

        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('PRIMARY KEY (`user_id`, `post_id`)', $sql);
    }

    /**
     * Test adding a foreign key
     */
    public function testForeignKey(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $foreignKey = $tableDefinition->foreignKey('fk_posts_users');
        
        $this->assertInstanceOf(ForeignKeyDefinition::class, $foreignKey);
    }

    /**
     * Test dropping a foreign key
     */
    public function testDropForeignKey(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_ALTER);
        $result = $tableDefinition->dropForeignKey('fk_old_constraint');

        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('DROP FOREIGN KEY `fk_old_constraint`', $sql);
    }

    /**
     * Test modifying a column
     */
    public function testModifyColumn(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_ALTER);
        $column = new ColumnDefinition('title');
        $column->string(300);

        $result = $tableDefinition->modifyColumn($column);

        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('MODIFY COLUMN', $sql);
    }

    /**
     * Test setting engine option
     */
    public function testEngine(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $result = $tableDefinition->engine('InnoDB');
        
        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }

    /**
     * Test setting charset option
     */
    public function testCharset(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $result = $tableDefinition->charset('utf8mb4');
        
        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('CHARACTER SET utf8mb4', $sql);
    }

    /**
     * Test setting collation option
     */
    public function testCollation(): void
    {
        $tableDefinition = new TableDefinition('posts', TableDefinition::ACTION_CREATE);
        $result = $tableDefinition->collation('utf8mb4_unicode_ci');
        
        $this->assertSame($tableDefinition, $result);

        $sql = $tableDefinition->toSql();
        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $sql);
    }

    /**
     * Test toSql method for creating tables
     */
    public function testToSqlCreate(): void
    {
        $tableDefinition = new TableDefinition('users', TableDefinition::ACTION_CREATE);
        $tableDefinition->column('id')->integer()->notNull()->autoIncrement();
        $tableDefinition->primaryKey('id');
        
        $sql = $tableDefinition->toSql();
        
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('`id` INT NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }

    /**
     * Test toSql method for altering tables
     */
    public function testToSqlAlter(): void
    {
        $tableDefinition = new TableDefinition('users', TableDefinition::ACTION_ALTER);
        $tableDefinition->column('email')->string(255)->notNull();
        
        $sql = $tableDefinition->toSql();
        
        $this->assertStringContainsString('ALTER TABLE `users`', $sql);
        $this->assertStringContainsString('ADD COLUMN', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL', $sql);
    }

    /**
     * Test ALTER TABLE with no alterations
     */
    public function testToSqlAlterWithNoAlterations(): void
    {
        $tableDefinition = new TableDefinition('users', TableDefinition::ACTION_ALTER);

        $sql = $tableDefinition->toSql();

        $this->assertStringContainsString('-- No alterations defined for table users', $sql);
    }

    /**
     * Test a complete table definition with all features
     */
    public function testCompleteTableDefinition(): void
    {
        $tableDefinition = new TableDefinition('articles', TableDefinition::ACTION_CREATE);

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
            ->column('user_id')
            ->references('users', 'id')
            ->onDelete(FkRule::CASCADE)
            ->onUpdate(FkRule::CASCADE);

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
        $this->assertStringContainsString('`created_at` DATETIME NOT NULL DEFAULT \'CURRENT_TIMESTAMP\'', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
        $this->assertStringContainsString('CHARACTER SET utf8mb4', $sql);
        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $sql);
    }

    /**
     * Test ALTER TABLE with multiple operations
     */
    public function testCompleteAlterTableDefinition(): void
    {
        $tableDefinition = new TableDefinition('articles', TableDefinition::ACTION_ALTER);

        // Add new column
        $tableDefinition->column('slug')->string(300);

        // Modify existing column
        $modifyColumn = new ColumnDefinition('title');
        $modifyColumn->string(300)->notNull();
        $tableDefinition->modifyColumn($modifyColumn);

        // Drop column
        $tableDefinition->dropColumn('old_field');

        // Add foreign key
        $tableDefinition->foreignKey('fk_articles_category')
            ->column('category_id')
            ->references('categories', 'id')
            ->onDelete(FkRule::SET_NULL)
            ->onUpdate(FkRule::CASCADE);

        // Drop foreign key
        $tableDefinition->dropForeignKey('fk_old_relation');

        $sql = $tableDefinition->toSql();

        $this->assertStringContainsString('ALTER TABLE `articles`', $sql);
        $this->assertStringContainsString('ADD COLUMN `slug` VARCHAR(300)', $sql);
        $this->assertStringContainsString('MODIFY COLUMN `title` VARCHAR(300) NOT NULL', $sql);
        $this->assertStringContainsString('DROP COLUMN `old_field`', $sql);
        $this->assertStringContainsString('ADD CONSTRAINT `fk_articles_category`', $sql);
        $this->assertStringContainsString('DROP FOREIGN KEY `fk_old_relation`', $sql);
    }

    /**
     * Test invalid action throws exception
     */
    public function testInvalidActionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown action: INVALID');

        // Using reflection to test private method with invalid action
        $tableDefinition = new TableDefinition('test', 'INVALID');
        $tableDefinition->toSql();
    }
}
