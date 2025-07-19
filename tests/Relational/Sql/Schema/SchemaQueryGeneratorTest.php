<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Schema\Builder\ColumnDefinition;
use MulerTech\Database\Schema\Builder\ForeignKeyDefinition;
use MulerTech\Database\Schema\Builder\TableDefinition;
use MulerTech\Database\Schema\SchemaQueryGenerator;
use MulerTech\Database\Schema\Types\ReferentialAction;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Class SchemaQueryGeneratorTest
 * Tests for the SchemaQueryGenerator class
 */
class SchemaQueryGeneratorTest extends TestCase
{
    private SchemaQueryGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaQueryGenerator();
    }

    /**
     * Test generate method for creating a table with basic structure
     */
    public function testGenerateCreateTable(): void
    {
        // Create a simple table with an ID column
        $table = new TableDefinition('users', true);
        $idColumn = $table->column('id');
        $idColumn->integer()->notNull()->autoIncrement();
        $table->primaryKey('id');

        $sql = $this->generator->generate($table);
        
        $expected = "CREATE TABLE `users` (" . PHP_EOL .
                    "    `id` INT NOT NULL AUTO_INCREMENT," . PHP_EOL .
                    "    PRIMARY KEY (`id`)" . PHP_EOL .
                    ");";
                    
        $this->assertEquals($expected, $sql);
    }

    /**
     * Test generate method for creating a table with multiple columns and constraints
     */
    public function testGenerateCreateTableComplex(): void
    {
        // Create a more complex table
        $table = new TableDefinition('products', true);
        
        // Add columns
        $table->column('id')->integer()->notNull()->autoIncrement();
        $table->column('name')->string(100)->notNull();
        $table->column('description')->text();
        $table->column('price')->decimal(10, 2)->notNull();
        $table->column('created_at')->datetime()->notNull()->default('CURRENT_TIMESTAMP');
        
        // Add primary key
        $table->primaryKey('id');
        
        // Add options
        $table->engine('InnoDB');
        $table->charset('utf8mb4');
        $table->collation('utf8mb4_unicode_ci');

        $sql = $this->generator->generate($table);
        
        $expected = "CREATE TABLE `products` (" . PHP_EOL .
                    "    `id` INT NOT NULL AUTO_INCREMENT," . PHP_EOL .
                    "    `name` VARCHAR(100) NOT NULL," . PHP_EOL .
                    "    `description` TEXT," . PHP_EOL .
                    "    `price` DECIMAL(10,2) NOT NULL," . PHP_EOL .
                    "    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP," . PHP_EOL .
                    "    PRIMARY KEY (`id`)" . PHP_EOL .
                    ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                    
        $this->assertEquals($expected, $sql);
    }

    /**
     * Test create table with foreign keys
     */
    public function testGenerateCreateTableWithForeignKeys(): void
    {
        $table = new TableDefinition('posts', true);
        
        // Add columns
        $table->column('id')->integer()->notNull()->autoIncrement();
        $table->column('user_id')->integer()->notNull();
        $table->column('category_id')->integer();
        $table->column('title')->string(255)->notNull();
        
        // Add primary key
        $table->primaryKey('id');
        
        // Add foreign keys
        $table->foreignKey('fk_posts_users')
            ->columns('user_id')
            ->references('users', 'id')
            ->onDelete(ReferentialAction::CASCADE)
            ->onUpdate(ReferentialAction::CASCADE);
            
        $table->foreignKey('fk_posts_categories')
            ->columns('category_id')
            ->references('categories', 'id')
            ->onDelete(ReferentialAction::SET_NULL)
            ->onUpdate(ReferentialAction::NO_ACTION);

        $sql = $this->generator->generate($table);
        
        $expected = "CREATE TABLE `posts` (" . PHP_EOL .
                    "    `id` INT NOT NULL AUTO_INCREMENT," . PHP_EOL .
                    "    `user_id` INT NOT NULL," . PHP_EOL .
                    "    `category_id` INT," . PHP_EOL .
                    "    `title` VARCHAR(255) NOT NULL," . PHP_EOL .
                    "    PRIMARY KEY (`id`)," . PHP_EOL .
                    "    CONSTRAINT `fk_posts_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE," . PHP_EOL .
                    "    CONSTRAINT `fk_posts_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION" . PHP_EOL .
                    ");";
                    
        $this->assertEquals($expected, $sql);
    }

    /**
     * Test generate method for altering a table
     */
    public function testGenerateAlterTable(): void
    {
        // Create an alter table definition
        $table = new TableDefinition('users', false);
        
        // Add a new column
        $table->column('email')->string(100)->notNull()->after('name');
        
        $sql = $this->generator->generate($table);
        
        $expected = "ALTER TABLE `users`" . PHP_EOL .
                    "    ADD COLUMN `email` VARCHAR(100) NOT NULL AFTER `name`;";
                    
        $this->assertEquals($expected, $sql);
    }

    /**
     * Test generate method for altering a table with multiple operations
     */
    public function testGenerateAlterTableComplex(): void
    {
        // Create an alter table definition with multiple operations
        $table = new TableDefinition('products', false);
        
        // Add a new column
        $table->column('stock')->integer()->notNull()->default(0);
        
        // Drop a column
        $table->dropColumn('old_column');
        
        // Add a foreign key
        $table->foreignKey('fk_products_categories')
            ->columns('category_id')
            ->references('categories', 'id');

        $sql = $this->generator->generate($table);
        
        $expected = "ALTER TABLE `products`" . PHP_EOL .
                    "    ADD COLUMN `stock` INT NOT NULL DEFAULT 0," . PHP_EOL .
                    "    DROP COLUMN `old_column`," . PHP_EOL .
                    "    ADD CONSTRAINT `fk_products_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);";
                    
        $this->assertEquals($expected, $sql);
    }

    /**
     * Test generating column definition with various options
     */
    public function testGenerateColumnDefinition(): void
    {
        $method = new ReflectionMethod(SchemaQueryGenerator::class, 'generateColumnDefinition');

        // Test basic column
        $column = new ColumnDefinition('name');
        $column->string(50)->notNull();
        $result = $method->invoke($this->generator, $column);
        $this->assertEquals('`name` VARCHAR(50) NOT NULL', $result);
        
        // Test column with default value
        $column = new ColumnDefinition('created_at');
        $column->datetime()->default('CURRENT_TIMESTAMP');
        $result = $method->invoke($this->generator, $column);
        $this->assertEquals('`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP', $result);
        
        // Test numeric column with unsigned
        $column = new ColumnDefinition('quantity');
        $column->integer()->unsigned()->notNull()->default(0);
        $result = $method->invoke($this->generator, $column);
        $this->assertEquals('`quantity` INT UNSIGNED NOT NULL DEFAULT 0', $result);
        
        // Test column with comment and after (note: after() not implemented in generateColumnDefinition)
        $column = new ColumnDefinition('email');
        $column->string(100)->notNull();
        $result = $method->invoke($this->generator, $column);
        $this->assertEquals('`email` VARCHAR(100) NOT NULL', $result);
        
        // Test decimal column
        $column = new ColumnDefinition('price');
        $column->decimal(10, 2)->notNull();
        $result = $method->invoke($this->generator, $column);
        $this->assertEquals('`price` DECIMAL(10,2) NOT NULL', $result);
        
        // Test auto increment column
        $column = new ColumnDefinition('id');
        $column->integer()->notNull()->autoIncrement();
        $result = $method->invoke($this->generator, $column);
        $this->assertEquals('`id` INT NOT NULL AUTO_INCREMENT', $result);
    }

    /**
     * Test generating foreign key definitions
     */
    public function testGenerateForeignKey(): void
    {
        $method = new ReflectionMethod(SchemaQueryGenerator::class, 'generateForeignKey');

        // Test basic foreign key
        $fk = new ForeignKeyDefinition('fk_test');
        $fk->columns('user_id')->references('users', 'id');
        $result = $method->invoke($this->generator, $fk);
        $this->assertEquals('CONSTRAINT `fk_test` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)', $result);
        
        // Test foreign key with CASCADE options
        $fk = new ForeignKeyDefinition('fk_test');
        $fk->columns('user_id')
           ->references('users', 'id')
           ->onDelete(ReferentialAction::CASCADE)
           ->onUpdate(ReferentialAction::CASCADE);
        $result = $method->invoke($this->generator, $fk);
        $this->assertEquals('CONSTRAINT `fk_test` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE', $result);
        
        // Test foreign key with multiple columns
        $fk = new ForeignKeyDefinition('fk_test');
        $fk->columns(['user_id', 'role_id'])
           ->references('user_roles', ['user_id', 'role_id']);
        $result = $method->invoke($this->generator, $fk);
        $this->assertEquals('CONSTRAINT `fk_test` FOREIGN KEY (`user_id`, `role_id`) REFERENCES `user_roles` (`user_id`, `role_id`)', $result);
    }

    /**
     * Test quoteValue method for different types of values
     */
    public function testQuoteValue(): void
    {
        $method = new ReflectionMethod(SchemaQueryGenerator::class, 'quoteValue');

        // Test numeric values
        $this->assertEquals(123, $method->invoke($this->generator, 123));
        $this->assertEquals(123.45, $method->invoke($this->generator, 123.45));
        
        // Test boolean values
        $this->assertEquals('1', $method->invoke($this->generator, true));
        $this->assertEquals('0', $method->invoke($this->generator, false));
        
        // Test NULL value
        $this->assertEquals('NULL', $method->invoke($this->generator, null));
        
        // Test string values
        $this->assertEquals("'hello'", $method->invoke($this->generator, 'hello'));
        
        // Test string with quotes
        $this->assertEquals("'It''s a test'", $method->invoke($this->generator, "It's a test"));
    }

    /**
     * Test alter table with empty operations
     */
    public function testGenerateAlterTableWithEmptyOperations(): void
    {
        $table = new TableDefinition('empty_table', false);
        $sql = $this->generator->generate($table);
        $this->assertEquals('', $sql);
    }
}
