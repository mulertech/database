<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\Schema\ColumnDefinition;
use MulerTech\Database\Relational\Sql\Schema\DataType;
use PHPUnit\Framework\TestCase;

class ColumnDefinitionTest extends TestCase
{
    /**
     * Test creation of a column with a name
     */
    public function testConstructor(): void
    {
        $column = new ColumnDefinition('test_column');
        $this->assertEquals('test_column', $column->getName());
    }
    
    /**
     * Test integer column type
     */
    public function testInteger(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->integer();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::INT, $column->getType());
    }
    
    /**
     * Test big integer column type
     */
    public function testBigInteger(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->bigInteger();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::BIGINT, $column->getType());
    }
    
    /**
     * Test string column type with default length
     */
    public function testStringWithDefaultLength(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::VARCHAR, $column->getType());
        $this->assertEquals(255, $column->getLength());
    }
    
    /**
     * Test string column type with custom length
     */
    public function testStringWithCustomLength(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string(100);
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::VARCHAR, $column->getType());
        $this->assertEquals(100, $column->getLength());
    }
    
    /**
     * Test text column type
     */
    public function testText(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->text();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::TEXT, $column->getType());
    }
    
    /**
     * Test decimal column type with default precision and scale
     */
    public function testDecimalWithDefaultPrecisionAndScale(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->decimal();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::DECIMAL, $column->getType());
        $this->assertEquals(8, $column->getPrecision());
        $this->assertEquals(2, $column->getScale());
    }
    
    /**
     * Test decimal column type with custom precision and scale
     */
    public function testDecimalWithCustomPrecisionAndScale(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->decimal(10, 4);
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::DECIMAL, $column->getType());
        $this->assertEquals(10, $column->getPrecision());
        $this->assertEquals(4, $column->getScale());
    }
    
    /**
     * Test datetime column type
     */
    public function testDatetime(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->datetime();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals(DataType::DATETIME, $column->getType());
    }
    
    /**
     * Test not null constraint
     */
    public function testNotNull(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->notNull();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertFalse($column->isNullable());
    }
    
    /**
     * Test setting default value
     */
    public function testDefault(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->default('default_value');
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals('default_value', $column->getDefault());
    }
    
    /**
     * Test auto increment flag
     */
    public function testAutoIncrement(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->autoIncrement();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertTrue($column->isAutoIncrement());
    }
    
    /**
     * Test unsigned flag
     */
    public function testUnsigned(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->unsigned();
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertTrue($column->isUnsigned());
    }
    
    /**
     * Test comment
     */
    public function testComment(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->comment('This is a test comment');
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals('This is a test comment', $column->getComment());
    }
    
    /**
     * Test after
     */
    public function testAfter(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->after('another_column');
        $this->assertSame($column, $result, 'Method should return $this for chaining');
        $this->assertEquals('another_column', $column->getAfter());
    }
}
