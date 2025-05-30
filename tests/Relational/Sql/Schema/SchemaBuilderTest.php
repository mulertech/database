<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\Schema\IndexDefinition;
use MulerTech\Database\Relational\Sql\Schema\SchemaBuilder;
use MulerTech\Database\Relational\Sql\Schema\TableDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Class SchemaBuilderTest
 * Tests for the SchemaBuilder class
 */
class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $schemaBuilder;

    protected function setUp(): void
    {
        $this->schemaBuilder = new SchemaBuilder();
    }

    /**
     * Test the createTable method
     */
    public function testCreateTable(): void
    {
        $tableName = 'users';
        $tableDefinition = $this->schemaBuilder->createTable($tableName);

        $this->assertInstanceOf(TableDefinition::class, $tableDefinition);
        $this->assertEquals($tableName, $tableDefinition->getTableName());
        $this->assertTrue($tableDefinition->isCreate());
    }

    /**
     * Test the alterTable method
     */
    public function testAlterTable(): void
    {
        $tableName = 'users';
        $tableDefinition = $this->schemaBuilder->alterTable($tableName);

        $this->assertInstanceOf(TableDefinition::class, $tableDefinition);
        $this->assertEquals($tableName, $tableDefinition->getTableName());
        $this->assertFalse($tableDefinition->isCreate());
    }

    /**
     * Test the dropTable method
     */
    public function testDropTable(): void
    {
        $tableName = 'users';
        $sql = $this->schemaBuilder->dropTable($tableName);

        $this->assertEquals("DROP TABLE `users`", $sql);
    }

    /**
     * Test the dropTable method with a table name containing special characters
     */
    public function testDropTableWithSpecialCharacters(): void
    {
        $tableName = 'user`table';
        $sql = $this->schemaBuilder->dropTable($tableName);

        $this->assertEquals("DROP TABLE `user``table`", $sql);
    }

    /**
     * Test the createIndex method
     */
    public function testCreateIndex(): void
    {
        $indexName = 'idx_email';
        $tableName = 'users';
        $indexDefinition = $this->schemaBuilder->createIndex($indexName, $tableName);

        $this->assertInstanceOf(IndexDefinition::class, $indexDefinition);
        $this->assertEquals($indexName, $indexDefinition->getName());
        $this->assertEquals($tableName, $indexDefinition->getTable());
    }

    /**
     * Test the dropIndex method
     */
    public function testDropIndex(): void
    {
        $indexName = 'idx_email';
        $tableName = 'users';
        $sql = $this->schemaBuilder->dropIndex($indexName, $tableName);

        $this->assertEquals("DROP INDEX `idx_email` ON `users`", $sql);
    }

    /**
     * Test the dropIndex method with names containing special characters
     */
    public function testDropIndexWithSpecialCharacters(): void
    {
        $indexName = 'idx`email';
        $tableName = 'user`table';
        $sql = $this->schemaBuilder->dropIndex($indexName, $tableName);

        $this->assertEquals("DROP INDEX `idx``email` ON `user``table`", $sql);
    }
}
