<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Types;

use MulerTech\Database\Query\Types\ComparisonOperator;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for ComparisonOperator enum
 */
class ComparisonOperatorTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('=', ComparisonOperator::EQUAL->value);
        $this->assertEquals('<>', ComparisonOperator::NOT_EQUAL->value);
        $this->assertEquals('>', ComparisonOperator::GREATER_THAN->value);
        $this->assertEquals('>=', ComparisonOperator::GREATER_THAN_OR_EQUAL->value);
        $this->assertEquals('!>', ComparisonOperator::NOT_GREATER_THAN->value);
        $this->assertEquals('<', ComparisonOperator::LESS_THAN->value);
        $this->assertEquals('<=', ComparisonOperator::LESS_THAN_OR_EQUAL->value);
        $this->assertEquals('!<', ComparisonOperator::NOT_LESS_THAN->value);
    }

    public function testEnumCases(): void
    {
        $expectedCases = [
            'EQUAL',
            'NOT_EQUAL',
            'GREATER_THAN',
            'GREATER_THAN_OR_EQUAL',
            'NOT_GREATER_THAN',
            'LESS_THAN',
            'LESS_THAN_OR_EQUAL',
            'NOT_LESS_THAN'
        ];

        $actualCases = array_column(ComparisonOperator::cases(), 'name');
        
        $this->assertEquals($expectedCases, $actualCases);
    }

    public function testReverseMethod(): void
    {
        $this->assertEquals(ComparisonOperator::NOT_EQUAL, ComparisonOperator::EQUAL->reverse());
        $this->assertEquals(ComparisonOperator::EQUAL, ComparisonOperator::NOT_EQUAL->reverse());
        
        $this->assertEquals(ComparisonOperator::LESS_THAN_OR_EQUAL, ComparisonOperator::GREATER_THAN->reverse());
        $this->assertEquals(ComparisonOperator::LESS_THAN, ComparisonOperator::GREATER_THAN_OR_EQUAL->reverse());
        $this->assertEquals(ComparisonOperator::LESS_THAN, ComparisonOperator::NOT_LESS_THAN->reverse());
        
        $this->assertEquals(ComparisonOperator::GREATER_THAN_OR_EQUAL, ComparisonOperator::LESS_THAN->reverse());
        $this->assertEquals(ComparisonOperator::GREATER_THAN, ComparisonOperator::LESS_THAN_OR_EQUAL->reverse());
        $this->assertEquals(ComparisonOperator::GREATER_THAN, ComparisonOperator::NOT_GREATER_THAN->reverse());
    }

    public function testFromValue(): void
    {
        $this->assertEquals(ComparisonOperator::EQUAL, ComparisonOperator::from('='));
        $this->assertEquals(ComparisonOperator::NOT_EQUAL, ComparisonOperator::from('<>'));
        $this->assertEquals(ComparisonOperator::GREATER_THAN, ComparisonOperator::from('>'));
        $this->assertEquals(ComparisonOperator::GREATER_THAN_OR_EQUAL, ComparisonOperator::from('>='));
        $this->assertEquals(ComparisonOperator::NOT_GREATER_THAN, ComparisonOperator::from('!>'));
        $this->assertEquals(ComparisonOperator::LESS_THAN, ComparisonOperator::from('<'));
        $this->assertEquals(ComparisonOperator::LESS_THAN_OR_EQUAL, ComparisonOperator::from('<='));
        $this->assertEquals(ComparisonOperator::NOT_LESS_THAN, ComparisonOperator::from('!<'));
    }

    public function testTryFromValue(): void
    {
        $this->assertEquals(ComparisonOperator::EQUAL, ComparisonOperator::tryFrom('='));
        $this->assertEquals(ComparisonOperator::NOT_EQUAL, ComparisonOperator::tryFrom('<>'));
        $this->assertNull(ComparisonOperator::tryFrom('INVALID'));
    }
}