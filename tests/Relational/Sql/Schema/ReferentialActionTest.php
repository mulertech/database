<?php

namespace MulerTech\Database\Tests\Relational\Sql\Schema;

use MulerTech\Database\Schema\Types\ReferentialAction;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Class ReferentialActionTest
 * Tests for ReferentialAction enum
 */
class ReferentialActionTest extends TestCase
{
    /**
     * Test that enum cases are correctly defined
     */
    public function testEnumCasesExist(): void
    {
        $this->assertInstanceOf(ReferentialAction::class, ReferentialAction::CASCADE);
        $this->assertInstanceOf(ReferentialAction::class, ReferentialAction::SET_NULL);
        $this->assertInstanceOf(ReferentialAction::class, ReferentialAction::RESTRICT);
        $this->assertInstanceOf(ReferentialAction::class, ReferentialAction::NO_ACTION);
        $this->assertInstanceOf(ReferentialAction::class, ReferentialAction::SET_DEFAULT);
    }

    /**
     * Test that enum values match expected string values
     */
    public function testEnumValues(): void
    {
        $this->assertEquals('CASCADE', ReferentialAction::CASCADE->value);
        $this->assertEquals('SET NULL', ReferentialAction::SET_NULL->value);
        $this->assertEquals('RESTRICT', ReferentialAction::RESTRICT->value);
        $this->assertEquals('NO ACTION', ReferentialAction::NO_ACTION->value);
        $this->assertEquals('SET DEFAULT', ReferentialAction::SET_DEFAULT->value);
    }

    /**
     * Test creating ReferentialAction from string values
     */
    public function testFromString(): void
    {
        $this->assertSame(ReferentialAction::CASCADE, ReferentialAction::from('CASCADE'));
        $this->assertSame(ReferentialAction::SET_NULL, ReferentialAction::from('SET NULL'));
        $this->assertSame(ReferentialAction::RESTRICT, ReferentialAction::from('RESTRICT'));
        $this->assertSame(ReferentialAction::NO_ACTION, ReferentialAction::from('NO ACTION'));
        $this->assertSame(ReferentialAction::SET_DEFAULT, ReferentialAction::from('SET DEFAULT'));
    }
    
    /**
     * Test that invalid values throw exception
     */
    public function testInvalidValue(): void
    {
        $this->expectException(ValueError::class);
        ReferentialAction::from('NON_EXISTENT_ACTION');
    }
    
    /**
     * Test tryFrom method with valid values
     */
    public function testTryFromWithValidValues(): void
    {
        $this->assertSame(ReferentialAction::CASCADE, ReferentialAction::tryFrom('CASCADE'));
        $this->assertSame(ReferentialAction::SET_NULL, ReferentialAction::tryFrom('SET NULL'));
        $this->assertSame(ReferentialAction::RESTRICT, ReferentialAction::tryFrom('RESTRICT'));
        $this->assertSame(ReferentialAction::NO_ACTION, ReferentialAction::tryFrom('NO ACTION'));
        $this->assertSame(ReferentialAction::SET_DEFAULT, ReferentialAction::tryFrom('SET DEFAULT'));
    }
    
    /**
     * Test tryFrom method with invalid value
     */
    public function testTryFromWithInvalidValue(): void
    {
        $this->assertNull(ReferentialAction::tryFrom('NON_EXISTENT_ACTION'));
    }
    
    /**
     * Test getting all possible cases
     */
    public function testCases(): void
    {
        $cases = ReferentialAction::cases();
        $this->assertCount(5, $cases);
        $this->assertContains(ReferentialAction::CASCADE, $cases);
        $this->assertContains(ReferentialAction::SET_NULL, $cases);
        $this->assertContains(ReferentialAction::RESTRICT, $cases);
        $this->assertContains(ReferentialAction::NO_ACTION, $cases);
        $this->assertContains(ReferentialAction::SET_DEFAULT, $cases);
    }

    /**
     * Test toEnumCallString method
     */
    public function testToEnumCallString(): void
    {
        $this->assertEquals('MulerTech\Database\Schema\Types\ReferentialAction::CASCADE', ReferentialAction::CASCADE->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Schema\Types\ReferentialAction::SET_NULL', ReferentialAction::SET_NULL->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Schema\Types\ReferentialAction::RESTRICT', ReferentialAction::RESTRICT->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Schema\Types\ReferentialAction::NO_ACTION', ReferentialAction::NO_ACTION->toEnumCallString());
        $this->assertEquals('MulerTech\Database\Schema\Types\ReferentialAction::SET_DEFAULT', ReferentialAction::SET_DEFAULT->toEnumCallString());
    }
}
