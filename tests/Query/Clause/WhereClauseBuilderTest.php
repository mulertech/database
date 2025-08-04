<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Clause;

use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Builder\Raw;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for WhereClauseBuilder class
 */
class WhereClauseBuilderTest extends TestCase
{
    private WhereClauseBuilder $builder;
    private QueryParameterBag $parameterBag;

    protected function setUp(): void
    {
        $this->parameterBag = new QueryParameterBag();
        $this->builder = new WhereClauseBuilder($this->parameterBag);
    }

    public function testConstructor(): void
    {
        $builder = new WhereClauseBuilder($this->parameterBag);
        $this->assertInstanceOf(WhereClauseBuilder::class, $builder);
    }

    public function testAdd(): void
    {
        $result = $this->builder->add('name', 'John', ComparisonOperator::EQUAL);
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testEqual(): void
    {
        $result = $this->builder->equal('name', 'John');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testEqualWithLinkOperator(): void
    {
        $result = $this->builder->equal('name', 'John', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotEqual(): void
    {
        $result = $this->builder->notEqual('status', 'inactive');
        
        $this->assertSame($this->builder, $result);
    }

    public function testGreaterThan(): void
    {
        $result = $this->builder->greaterThan('age', 18);
        
        $this->assertSame($this->builder, $result);
    }

    public function testLessThan(): void
    {
        $result = $this->builder->lessThan('price', 100);
        
        $this->assertSame($this->builder, $result);
    }

    public function testGreaterOrEqual(): void
    {
        $result = $this->builder->greaterOrEqual('score', 75);
        
        $this->assertSame($this->builder, $result);
    }

    public function testLessOrEqual(): void
    {
        $result = $this->builder->lessOrEqual('discount', 50);
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotGreaterThan(): void
    {
        $result = $this->builder->notGreaterThan('limit', 1000);
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotLessThan(): void
    {
        $result = $this->builder->notLessThan('minimum', 10);
        
        $this->assertSame($this->builder, $result);
    }

    public function testLike(): void
    {
        $result = $this->builder->like('name', '%John%');
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotLike(): void
    {
        $result = $this->builder->notLike('email', '%@spam.com');
        
        $this->assertSame($this->builder, $result);
    }

    public function testIn(): void
    {
        $values = [1, 2, 3, 4, 5];
        $result = $this->builder->in('id', $values);
        
        $this->assertSame($this->builder, $result);
    }

    public function testInWithSelectBuilder(): void
    {
        $subQuery = new SelectBuilder();
        $result = $this->builder->in('id', $subQuery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotIn(): void
    {
        $values = [1, 2, 3];
        $result = $this->builder->notIn('status', $values);
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotInWithSelectBuilder(): void
    {
        $subQuery = new SelectBuilder();
        $result = $this->builder->notIn('role', $subQuery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testBetween(): void
    {
        $result = $this->builder->between('age', 18, 65);
        
        $this->assertSame($this->builder, $result);
    }

    public function testNotBetween(): void
    {
        $result = $this->builder->notBetween('score', 0, 10);
        
        $this->assertSame($this->builder, $result);
    }

    public function testIsNull(): void
    {
        $result = $this->builder->isNull('deleted_at');
        
        $this->assertSame($this->builder, $result);
    }

    public function testIsNotNull(): void
    {
        $result = $this->builder->isNotNull('email_verified_at');
        
        $this->assertSame($this->builder, $result);
    }

    public function testRaw(): void
    {
        $result = $this->builder->raw('YEAR(created_at) = ?', ['year' => 2023]);
        
        $this->assertSame($this->builder, $result);
    }

    public function testRawWithoutParameters(): void
    {
        $result = $this->builder->raw('1 = 1');
        
        $this->assertSame($this->builder, $result);
    }

    public function testExists(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->exists($subquery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testExistsWithLinkOperator(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->exists($subquery, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testGroup(): void
    {
        $result = $this->builder->group(function (WhereClauseBuilder $query) {
            $query->equal('name', 'John')
                  ->equal('age', 30, LinkOperator::OR);
        });
        
        $this->assertSame($this->builder, $result);
    }

    public function testGroupWithLinkOperator(): void
    {
        $result = $this->builder
            ->equal('active', 1)
            ->group(function (WhereClauseBuilder $query) {
                $query->equal('role', 'admin')
                      ->equal('verified', 1, LinkOperator::OR);
            }, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testComplexConditions(): void
    {
        $result = $this->builder
            ->equal('active', 1)
            ->group(function (WhereClauseBuilder $query) {
                $query->equal('role', 'admin')
                      ->equal('role', 'moderator', LinkOperator::OR);
            })
            ->in('department', ['IT', 'HR', 'Finance'])
            ->between('age', 25, 55)
            ->isNotNull('email')
            ->like('name', '%manager%');
        
        $this->assertSame($this->builder, $result);
    }

    public function testToSql(): void
    {
        $this->builder
            ->equal('active', 1)
            ->equal('name', 'John');
        
        $sql = $this->builder->toSql();
        
        $this->assertIsString($sql);
        $this->assertNotEmpty($sql);
    }

    public function testToSqlEmpty(): void
    {
        $sql = $this->builder->toSql();
        
        $this->assertEquals('', $sql);
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->builder->isEmpty());
        
        $this->builder->equal('active', 1);
        
        $this->assertFalse($this->builder->isEmpty());
    }

    public function testClear(): void
    {
        $this->builder->equal('active', 1);
        $this->assertFalse($this->builder->isEmpty());
        
        $result = $this->builder->clear();
        
        $this->assertSame($this->builder, $result);
        $this->assertTrue($this->builder->isEmpty());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->builder->count());
        
        $this->builder->equal('active', 1);
        $this->assertEquals(1, $this->builder->count());
        
        $this->builder->equal('name', 'John');
        $this->assertEquals(2, $this->builder->count());
    }

    public function testMerge(): void
    {
        $otherBuilder = new WhereClauseBuilder(new QueryParameterBag());
        $otherBuilder->equal('role', 'admin');
        
        $this->builder->equal('active', 1);
        
        $result = $this->builder->merge($otherBuilder);
        
        $this->assertSame($this->builder, $result);
        $this->assertEquals(2, $this->builder->count());
    }

    public function testWithRawValue(): void
    {
        $raw = new Raw('NOW()');
        $result = $this->builder->equal('created_at', $raw);
        
        $this->assertSame($this->builder, $result);
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->equal('active', 1)
            ->notEqual('status', 'banned')
            ->greaterThan('age', 18)
            ->lessThan('age', 65)
            ->like('name', '%John%')
            ->in('role', ['user', 'admin'])
            ->between('score', 50, 100)
            ->isNotNull('email')
            ->raw('YEAR(created_at) = ?', ['year' => 2023]);
        
        $this->assertSame($this->builder, $result);
        $this->assertEquals(9, $this->builder->count());
    }

    public function testAllLinkOperators(): void
    {
        $this->builder
            ->equal('a', 1, LinkOperator::AND)
            ->equal('b', 2, LinkOperator::OR)
            ->equal('c', 3, LinkOperator::AND)
            ->equal('d', 4, LinkOperator::OR);
        
        $this->assertEquals(4, $this->builder->count());
    }
}