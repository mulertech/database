<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\Schema\DataType;
use PHPUnit\Framework\TestCase;
use ValueError;

class DataTypeTest extends TestCase
{
    /**
     * Test that all DataType cases have the correct string values
     */
    public function testDataTypeValues(): void
    {
        $this->assertEquals('INT', DataType::INT->value);
        $this->assertEquals('BIGINT', DataType::BIGINT->value);
        $this->assertEquals('VARCHAR', DataType::VARCHAR->value);
        $this->assertEquals('TEXT', DataType::TEXT->value);
        $this->assertEquals('DECIMAL', DataType::DECIMAL->value);
        $this->assertEquals('FLOAT', DataType::FLOAT->value);
        $this->assertEquals('DATETIME', DataType::DATETIME->value);
        $this->assertEquals('DATE', DataType::DATE->value);
        $this->assertEquals('BOOLEAN', DataType::BOOLEAN->value);
        $this->assertEquals('JSON', DataType::JSON->value);
    }

    /**
     * Test case equality comparisons
     */
    public function testDataTypeEquality(): void
    {
        $intType = DataType::INT;
        
        $this->assertSame(DataType::INT, $intType);
        $this->assertNotSame(DataType::VARCHAR, $intType);
        
        // Test instanceof
        $this->assertInstanceOf(DataType::class, $intType);
    }
    
    /**
     * Test using string values to identify cases
     */
    public function testFromString(): void
    {
        $this->assertSame(DataType::INT, DataType::from('INT'));
        $this->assertSame(DataType::BIGINT, DataType::from('BIGINT'));
        $this->assertSame(DataType::VARCHAR, DataType::from('VARCHAR'));
        $this->assertSame(DataType::TEXT, DataType::from('TEXT'));
        $this->assertSame(DataType::DECIMAL, DataType::from('DECIMAL'));
        $this->assertSame(DataType::FLOAT, DataType::from('FLOAT'));
        $this->assertSame(DataType::DATETIME, DataType::from('DATETIME'));
        $this->assertSame(DataType::DATE, DataType::from('DATE'));
        $this->assertSame(DataType::BOOLEAN, DataType::from('BOOLEAN'));
        $this->assertSame(DataType::JSON, DataType::from('JSON'));
    }
    
    /**
     * Test that an invalid string value throws an exception
     */
    public function testInvalidStringThrowsException(): void
    {
        $this->expectException(ValueError::class);
        DataType::from('INVALID_TYPE');
    }
    
    /**
     * Test case name retrieval
     */
    public function testCaseName(): void
    {
        $this->assertEquals('INT', DataType::INT->name);
        $this->assertEquals('BIGINT', DataType::BIGINT->name);
        $this->assertEquals('VARCHAR', DataType::VARCHAR->name);
        $this->assertEquals('TEXT', DataType::TEXT->name);
        $this->assertEquals('DECIMAL', DataType::DECIMAL->name);
        $this->assertEquals('FLOAT', DataType::FLOAT->name);
        $this->assertEquals('DATETIME', DataType::DATETIME->name);
        $this->assertEquals('DATE', DataType::DATE->name);
        $this->assertEquals('BOOLEAN', DataType::BOOLEAN->name);
        $this->assertEquals('JSON', DataType::JSON->name);
    }
    
    /**
     * Test getting all cases
     */
    public function testGetAllCases(): void
    {
        $cases = DataType::cases();
        
        $this->assertCount(10, $cases);
        $this->assertContains(DataType::INT, $cases);
        $this->assertContains(DataType::BIGINT, $cases);
        $this->assertContains(DataType::VARCHAR, $cases);
        $this->assertContains(DataType::TEXT, $cases);
        $this->assertContains(DataType::DECIMAL, $cases);
        $this->assertContains(DataType::FLOAT, $cases);
        $this->assertContains(DataType::DATETIME, $cases);
        $this->assertContains(DataType::DATE, $cases);
        $this->assertContains(DataType::BOOLEAN, $cases);
        $this->assertContains(DataType::JSON, $cases);
    }
    
    /**
     * Test trying to get a case that might exist
     */
    public function testTryFrom(): void
    {
        $this->assertSame(DataType::INT, DataType::tryFrom('INT'));
        $this->assertNull(DataType::tryFrom('NOT_EXISTS'));
    }
}
