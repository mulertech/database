<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Types;

use MulerTech\Database\Query\Types\SqlOperator;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for SqlOperator enum
 */
class SqlOperatorTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('IN', SqlOperator::IN->value);
        $this->assertEquals('NOT IN', SqlOperator::NOT_IN->value);
        $this->assertEquals('BETWEEN', SqlOperator::BETWEEN->value);
        $this->assertEquals('NOT BETWEEN', SqlOperator::NOT_BETWEEN->value);
        $this->assertEquals('EXISTS', SqlOperator::EXISTS->value);
        $this->assertEquals('LIKE', SqlOperator::LIKE->value);
        $this->assertEquals('NOT LIKE', SqlOperator::NOT_LIKE->value);
    }

    public function testEnumCases(): void
    {
        $expectedCases = [
            'IN',
            'NOT_IN',
            'BETWEEN',
            'NOT_BETWEEN',
            'EXISTS',
            'LIKE',
            'NOT_LIKE'
        ];

        $actualCases = array_column(SqlOperator::cases(), 'name');
        
        $this->assertEquals($expectedCases, $actualCases);
    }

    public function testFromValue(): void
    {
        $this->assertEquals(SqlOperator::IN, SqlOperator::from('IN'));
        $this->assertEquals(SqlOperator::NOT_IN, SqlOperator::from('NOT IN'));
        $this->assertEquals(SqlOperator::BETWEEN, SqlOperator::from('BETWEEN'));
        $this->assertEquals(SqlOperator::NOT_BETWEEN, SqlOperator::from('NOT BETWEEN'));
        $this->assertEquals(SqlOperator::EXISTS, SqlOperator::from('EXISTS'));
        $this->assertEquals(SqlOperator::LIKE, SqlOperator::from('LIKE'));
        $this->assertEquals(SqlOperator::NOT_LIKE, SqlOperator::from('NOT LIKE'));
    }

    public function testTryFromValue(): void
    {
        $this->assertEquals(SqlOperator::IN, SqlOperator::tryFrom('IN'));
        $this->assertEquals(SqlOperator::NOT_IN, SqlOperator::tryFrom('NOT IN'));
        $this->assertNull(SqlOperator::tryFrom('INVALID'));
    }
}