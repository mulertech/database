<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Types;

use MulerTech\Database\Query\Types\LinkOperator;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for LinkOperator enum
 */
class LinkOperatorTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('AND', LinkOperator::AND->value);
        $this->assertEquals('OR', LinkOperator::OR->value);
        $this->assertEquals('NOT', LinkOperator::NOT->value);
        $this->assertEquals('AND NOT', LinkOperator::AND_NOT->value);
        $this->assertEquals('OR NOT', LinkOperator::OR_NOT->value);
    }

    public function testEnumCases(): void
    {
        $expectedCases = [
            'AND',
            'OR',
            'NOT',
            'AND_NOT',
            'OR_NOT'
        ];

        $actualCases = array_column(LinkOperator::cases(), 'name');
        
        $this->assertEquals($expectedCases, $actualCases);
    }

    public function testNotMethod(): void
    {
        $this->assertEquals(LinkOperator::AND_NOT, LinkOperator::AND->not());
        $this->assertEquals(LinkOperator::OR_NOT, LinkOperator::OR->not());
        $this->assertEquals(LinkOperator::NOT, LinkOperator::NOT->not());
        $this->assertEquals(LinkOperator::NOT, LinkOperator::AND_NOT->not());
        $this->assertEquals(LinkOperator::NOT, LinkOperator::OR_NOT->not());
    }

    public function testFromValue(): void
    {
        $this->assertEquals(LinkOperator::AND, LinkOperator::from('AND'));
        $this->assertEquals(LinkOperator::OR, LinkOperator::from('OR'));
        $this->assertEquals(LinkOperator::NOT, LinkOperator::from('NOT'));
        $this->assertEquals(LinkOperator::AND_NOT, LinkOperator::from('AND NOT'));
        $this->assertEquals(LinkOperator::OR_NOT, LinkOperator::from('OR NOT'));
    }

    public function testTryFromValue(): void
    {
        $this->assertEquals(LinkOperator::AND, LinkOperator::tryFrom('AND'));
        $this->assertEquals(LinkOperator::OR, LinkOperator::tryFrom('OR'));
        $this->assertNull(LinkOperator::tryFrom('INVALID'));
    }
}