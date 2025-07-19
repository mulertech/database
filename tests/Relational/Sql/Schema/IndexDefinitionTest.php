<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use InvalidArgumentException;
use MulerTech\Database\Schema\Builder\IndexDefinition;
use MulerTech\Database\Schema\Types\IndexType;
use PHPUnit\Framework\TestCase;

class IndexDefinitionTest extends TestCase
{
    /**
     * Test constructor sets name and table correctly
     */
    public function testConstructor(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        
        $this->assertEquals('test_index', $index->getName());
        $this->assertEquals('test_table', $index->getTable());
    }

    /**
     * Test columns method with a string
     */
    public function testColumnsWithString(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->columns('email');
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals(['email'], $index->getColumns());
    }

    /**
     * Test columns method with an array
     */
    public function testColumnsWithArray(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->columns(['first_name', 'last_name']);
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals(['first_name', 'last_name'], $index->getColumns());
    }

    /**
     * Test unique index type
     */
    public function testUnique(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->unique();
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals(IndexType::UNIQUE, $index->getType());
    }

    /**
     * Test fulltext index type
     */
    public function testFullText(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->fullText();
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals(IndexType::FULLTEXT, $index->getType());
    }

    /**
     * Test setting algorithm
     */
    public function testAlgorithm(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->algorithm('btree');
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals('BTREE', $index->getAlgorithm());
    }

    /**
     * Test setting key block size
     */
    public function testKeyBlockSize(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->keyBlockSize(1024);
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals(1024, $index->getKeyBlockSize());
    }

    /**
     * Test setting comment
     */
    public function testComment(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        $result = $index->comment('This is a test comment');
        
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertEquals('This is a test comment', $index->getComment());
    }

    /**
     * Test setting visibility
     */
    public function testVisible(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        
        // Test default value
        $this->assertTrue($index->isVisible());
        
        // Test setting to invisible
        $result = $index->visible(false);
        $this->assertSame($index, $result, 'Method should return $this for chaining');
        $this->assertFalse($index->isVisible());
        
        // Test setting back to visible
        $index->visible(true);
        $this->assertTrue($index->isVisible());
    }

    /**
     * Test generating SQL for a simple index
     */
    public function testToSqlSimple(): void
    {
        $index = new IndexDefinition('idx_email', 'users');
        $index->columns('email');
        
        $expected = 'CREATE INDEX `idx_email` ON `users` (`email`)';
        $this->assertEquals($expected, $index->toSql());
    }

    /**
     * Test generating SQL for an index with multiple columns
     */
    public function testToSqlMultipleColumns(): void
    {
        $index = new IndexDefinition('idx_name', 'users');
        $index->columns(['first_name', 'last_name']);
        
        $expected = 'CREATE INDEX `idx_name` ON `users` (`first_name`, `last_name`)';
        $this->assertEquals($expected, $index->toSql());
    }

    /**
     * Test generating SQL for a unique index
     */
    public function testToSqlUnique(): void
    {
        $index = new IndexDefinition('idx_email', 'users');
        $index->columns('email')->unique();
        
        $expected = 'CREATE UNIQUE INDEX `idx_email` ON `users` (`email`)';
        $this->assertEquals($expected, $index->toSql());
    }

    /**
     * Test generating SQL for a fulltext index
     */
    public function testToSqlFulltext(): void
    {
        $index = new IndexDefinition('idx_content', 'articles');
        $index->columns('content')->fullText();
        
        $expected = 'CREATE FULLTEXT INDEX `idx_content` ON `articles` (`content`)';
        $this->assertEquals($expected, $index->toSql());
    }

    /**
     * Test generating SQL with all options
     */
    public function testToSqlWithAllOptions(): void
    {
        $index = new IndexDefinition('idx_complex', 'products');
        $index->columns(['name', 'description'])
              ->unique()
              ->algorithm('BTREE')
              ->keyBlockSize(2048)
              ->comment('Complex index example')
              ->visible(false);
        
        $expected = 'CREATE UNIQUE INDEX `idx_complex` ON `products` (`name`, `description`) ' .
                   'ALGORITHM = BTREE KEY_BLOCK_SIZE = 2048 ' .
                   'COMMENT \'Complex index example\' INVISIBLE';
        $this->assertEquals($expected, $index->toSql());
    }

    /**
     * Test escaping in SQL generation
     */
    public function testToSqlEscaping(): void
    {
        $index = new IndexDefinition('idx`special', 'table`special');
        $index->columns('column`special')->comment('Comment with \' quote');
        
        $expected = 'CREATE INDEX `idx``special` ON `table``special` (`column``special`) ' .
                   'COMMENT \'Comment with \'\' quote\'';
        $this->assertEquals($expected, $index->toSql());
    }

    /**
     * Test exception when generating SQL without columns
     */
    public function testToSqlThrowsExceptionWithoutColumns(): void
    {
        $index = new IndexDefinition('idx_test', 'test_table');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The index must have at least one column.');
        
        $index->toSql();
    }
    
    /**
     * Test default values
     */
    public function testDefaultValues(): void
    {
        $index = new IndexDefinition('test_index', 'test_table');
        
        $this->assertEquals(IndexType::INDEX, $index->getType());
        $this->assertEmpty($index->getColumns());
        $this->assertNull($index->getAlgorithm());
        $this->assertNull($index->getKeyBlockSize());
        $this->assertNull($index->getComment());
        $this->assertTrue($index->isVisible());
    }
}

