<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Schema\IndexType;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Class IndexTypeTest
 * Tests for IndexType enum
 */
class IndexTypeTest extends TestCase
{
    /**
     * Test that enum cases are correctly defined
     */
    public function testEnumCasesExist(): void
    {
        $this->assertInstanceOf(IndexType::class, IndexType::INDEX);
        $this->assertInstanceOf(IndexType::class, IndexType::UNIQUE);
        $this->assertInstanceOf(IndexType::class, IndexType::FULLTEXT);
    }

    /**
     * Test that enum values match expected string values
     */
    public function testEnumValues(): void
    {
        $this->assertEquals('INDEX', IndexType::INDEX->value);
        $this->assertEquals('UNIQUE', IndexType::UNIQUE->value);
        $this->assertEquals('FULLTEXT', IndexType::FULLTEXT->value);
    }

    /**
     * Test creating IndexType from string values
     */
    public function testFromString(): void
    {
        $this->assertSame(IndexType::INDEX, IndexType::from('INDEX'));
        $this->assertSame(IndexType::UNIQUE, IndexType::from('UNIQUE'));
        $this->assertSame(IndexType::FULLTEXT, IndexType::from('FULLTEXT'));
    }
    
    /**
     * Test that invalid values throw exception
     */
    public function testInvalidValue(): void
    {
        $this->expectException(ValueError::class);
        IndexType::from('NON_EXISTENT_TYPE');
    }
    
    /**
     * Test tryFrom method with valid values
     */
    public function testTryFromWithValidValues(): void
    {
        $this->assertSame(IndexType::INDEX, IndexType::tryFrom('INDEX'));
        $this->assertSame(IndexType::UNIQUE, IndexType::tryFrom('UNIQUE'));
        $this->assertSame(IndexType::FULLTEXT, IndexType::tryFrom('FULLTEXT'));
    }
    
    /**
     * Test tryFrom method with invalid values
     */
    public function testTryFromWithInvalidValue(): void
    {
        $this->assertNull(IndexType::tryFrom('NON_EXISTENT_TYPE'));
    }
    
    /**
     * Test getting all possible cases
     */
    public function testCases(): void
    {
        $cases = IndexType::cases();
        $this->assertCount(3, $cases);
        $this->assertContains(IndexType::INDEX, $cases);
        $this->assertContains(IndexType::UNIQUE, $cases);
        $this->assertContains(IndexType::FULLTEXT, $cases);
    }
}
