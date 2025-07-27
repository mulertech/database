<?php

namespace Mapping;

use MulerTech\Database\Mapping\Types\FkRule;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Class ReferentialActionTest
 * Tests for ReferentialAction enum
 */
class FkRuleTest extends TestCase
{
    /**
     * Test that enum cases are correctly defined
     */
    public function testEnumCasesExist(): void
    {
        $this->assertInstanceOf(FkRule::class, FkRule::CASCADE);
        $this->assertInstanceOf(FkRule::class, FkRule::SET_NULL);
        $this->assertInstanceOf(FkRule::class, FkRule::RESTRICT);
        $this->assertInstanceOf(FkRule::class, FkRule::NO_ACTION);
        $this->assertInstanceOf(FkRule::class, FkRule::SET_DEFAULT);
    }

    /**
     * Test that enum values match expected string values
     */
    public function testEnumValues(): void
    {
        $this->assertEquals('CASCADE', FkRule::CASCADE->value);
        $this->assertEquals('SET NULL', FkRule::SET_NULL->value);
        $this->assertEquals('RESTRICT', FkRule::RESTRICT->value);
        $this->assertEquals('NO ACTION', FkRule::NO_ACTION->value);
        $this->assertEquals('SET DEFAULT', FkRule::SET_DEFAULT->value);
    }

    /**
     * Test creating FkRule from string values
     */
    public function testFromString(): void
    {
        $this->assertSame(FkRule::CASCADE, FkRule::from('CASCADE'));
        $this->assertSame(FkRule::SET_NULL, FkRule::from('SET NULL'));
        $this->assertSame(FkRule::RESTRICT, FkRule::from('RESTRICT'));
        $this->assertSame(FkRule::NO_ACTION, FkRule::from('NO ACTION'));
        $this->assertSame(FkRule::SET_DEFAULT, FkRule::from('SET DEFAULT'));
    }
    
    /**
     * Test that invalid values throw exception
     */
    public function testInvalidValue(): void
    {
        $this->expectException(ValueError::class);
        FkRule::from('NON_EXISTENT_ACTION');
    }
    
    /**
     * Test tryFrom method with valid values
     */
    public function testTryFromWithValidValues(): void
    {
        $this->assertSame(FkRule::CASCADE, FkRule::tryFrom('CASCADE'));
        $this->assertSame(FkRule::SET_NULL, FkRule::tryFrom('SET NULL'));
        $this->assertSame(FkRule::RESTRICT, FkRule::tryFrom('RESTRICT'));
        $this->assertSame(FkRule::NO_ACTION, FkRule::tryFrom('NO ACTION'));
        $this->assertSame(FkRule::SET_DEFAULT, FkRule::tryFrom('SET DEFAULT'));
    }
    
    /**
     * Test tryFrom method with invalid value
     */
    public function testTryFromWithInvalidValue(): void
    {
        $this->assertNull(FkRule::tryFrom('NON_EXISTENT_ACTION'));
    }
    
    /**
     * Test getting all possible cases
     */
    public function testCases(): void
    {
        $cases = FkRule::cases();
        $this->assertCount(5, $cases);
        $this->assertContains(FkRule::CASCADE, $cases);
        $this->assertContains(FkRule::SET_NULL, $cases);
        $this->assertContains(FkRule::RESTRICT, $cases);
        $this->assertContains(FkRule::NO_ACTION, $cases);
        $this->assertContains(FkRule::SET_DEFAULT, $cases);
    }

    /**
     * Test toEnumCallString method
     */
    public function testToEnumCallString(): void
    {
        $this->assertEquals('MulerTech\Database\Mapping\Types\FkRule::CASCADE', FkRule::CASCADE->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Mapping\Types\FkRule::SET_NULL', FkRule::SET_NULL->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Mapping\Types\FkRule::RESTRICT', FkRule::RESTRICT->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Mapping\Types\FkRule::NO_ACTION', FkRule::NO_ACTION->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Mapping\Types\FkRule::SET_DEFAULT', FkRule::SET_DEFAULT->toEnumCallString());
    }
}
