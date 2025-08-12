<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Types;

use MulerTech\Database\Query\Types\JoinType;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for JoinType enum
 */
class JoinTypeTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('INNER', JoinType::INNER->value);
        $this->assertEquals('LEFT', JoinType::LEFT->value);
        $this->assertEquals('RIGHT', JoinType::RIGHT->value);
        $this->assertEquals('CROSS', JoinType::CROSS->value);
        $this->assertEquals('FULL OUTER', JoinType::FULL_OUTER->value);
        $this->assertEquals('LEFT OUTER', JoinType::LEFT_OUTER->value);
        $this->assertEquals('RIGHT OUTER', JoinType::RIGHT_OUTER->value);
    }

    public function testEnumCases(): void
    {
        $expectedCases = [
            'INNER',
            'LEFT',
            'RIGHT',
            'CROSS',
            'FULL_OUTER',
            'LEFT_OUTER',
            'RIGHT_OUTER'
        ];

        $actualCases = array_column(JoinType::cases(), 'name');
        
        $this->assertEquals($expectedCases, $actualCases);
    }

    public function testFromValue(): void
    {
        $this->assertEquals(JoinType::INNER, JoinType::from('INNER'));
        $this->assertEquals(JoinType::LEFT, JoinType::from('LEFT'));
        $this->assertEquals(JoinType::RIGHT, JoinType::from('RIGHT'));
        $this->assertEquals(JoinType::CROSS, JoinType::from('CROSS'));
        $this->assertEquals(JoinType::FULL_OUTER, JoinType::from('FULL OUTER'));
        $this->assertEquals(JoinType::LEFT_OUTER, JoinType::from('LEFT OUTER'));
        $this->assertEquals(JoinType::RIGHT_OUTER, JoinType::from('RIGHT OUTER'));
    }

    public function testTryFromValue(): void
    {
        $this->assertEquals(JoinType::INNER, JoinType::tryFrom('INNER'));
        $this->assertEquals(JoinType::LEFT, JoinType::tryFrom('LEFT'));
        $this->assertNull(JoinType::tryFrom('INVALID'));
    }
}