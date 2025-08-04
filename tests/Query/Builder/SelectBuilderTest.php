<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Builder\Raw;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\SqlOperator;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\ORM\EmEngine;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for SelectBuilder class
 */
class SelectBuilderTest extends TestCase
{
    private SelectBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SelectBuilder();
    }

    public function testConstructor(): void
    {
        $builder = new SelectBuilder();
        $this->assertInstanceOf(SelectBuilder::class, $builder);
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $builder = new SelectBuilder($emEngine);
        $this->assertInstanceOf(SelectBuilder::class, $builder);
    }

    public function testGetQueryType(): void
    {
        $this->assertEquals('SELECT', $this->builder->getQueryType());
    }

    public function testSelect(): void
    {
        $result = $this->builder->select('id', 'name', 'email');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testSelectWithSingleColumn(): void
    {
        $result = $this->builder->select('id');
        
        $this->assertSame($this->builder, $result);
    }

    public function testSelectMultipleCalls(): void
    {
        $result = $this->builder
            ->select('id')
            ->select('name', 'email')
            ->select('created_at');
        
        $this->assertSame($this->builder, $result);
    }

    public function testFromWithTable(): void
    {
        $result = $this->builder->from('users');
        
        $this->assertSame($this->builder, $result);
    }

    public function testFromWithTableAndAlias(): void
    {
        $result = $this->builder->from('users', 'u');
        
        $this->assertSame($this->builder, $result);
    }

    public function testFromWithSubquery(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->from($subquery, 'sub');
        
        $this->assertSame($this->builder, $result);
    }

    public function testFromWithSubqueryWithoutAlias(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->from($subquery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testGroupBy(): void
    {
        $result = $this->builder->groupBy('department', 'status');
        
        $this->assertSame($this->builder, $result);
    }

    public function testGroupBySingleColumn(): void
    {
        $result = $this->builder->groupBy('department');
        
        $this->assertSame($this->builder, $result);
    }

    public function testHaving(): void
    {
        $result = $this->builder->having('COUNT(*)', 5, ComparisonOperator::GREATER_THAN);
        
        $this->assertSame($this->builder, $result);
    }

    public function testHavingWithDefaultOperator(): void
    {
        $result = $this->builder->having('COUNT(*)', 5);
        
        $this->assertSame($this->builder, $result);
    }

    public function testHavingWithSqlOperator(): void
    {
        $result = $this->builder->having('name', ['John', 'Jane'], SqlOperator::IN);
        
        $this->assertSame($this->builder, $result);
    }

    public function testOrderBy(): void
    {
        $result = $this->builder->orderBy('name', 'ASC');
        
        $this->assertSame($this->builder, $result);
    }

    public function testOrderByWithDefaultDirection(): void
    {
        $result = $this->builder->orderBy('created_at');
        
        $this->assertSame($this->builder, $result);
    }

    public function testOrderByDescending(): void
    {
        $result = $this->builder->orderBy('updated_at', 'DESC');
        
        $this->assertSame($this->builder, $result);
    }

    public function testLimit(): void
    {
        $result = $this->builder->limit(10);
        
        $this->assertSame($this->builder, $result);
    }

    public function testOffset(): void
    {
        $this->builder->limit(10); // Must set limit before offset
        $result = $this->builder->offset(20);
        
        $this->assertSame($this->builder, $result);
    }

    public function testOffsetWithPage(): void
    {
        $this->builder->limit(10); // Must set limit before offset
        $result = $this->builder->offset(null, 3);
        
        $this->assertSame($this->builder, $result);
    }

    public function testOffsetWithBothParameters(): void
    {
        $this->builder->limit(10); // Must set limit before offset
        $result = $this->builder->offset(20, 3);
        
        $this->assertSame($this->builder, $result);
    }

    public function testOffsetWithoutLimitThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot set offset without a limit.');
        
        $this->builder->offset(20);
    }

    public function testDistinct(): void
    {
        $result = $this->builder->distinct();
        
        $this->assertSame($this->builder, $result);
    }

    public function testWithoutDistinct(): void
    {
        $result = $this->builder->distinct()->withoutDistinct();
        
        $this->assertSame($this->builder, $result);
    }

    public function testSetParameterBag(): void
    {
        $parameterBag = new QueryParameterBag();
        $result = $this->builder->setParameterBag($parameterBag);
        
        $this->assertSame($this->builder, $result);
        $this->assertSame($parameterBag, $this->builder->getParameterBag());
    }

    public function testGenerateFromParts(): void
    {
        $this->builder->from('users', 'u');
        
        $fromParts = $this->builder->generateFromParts();
        
        $this->assertIsArray($fromParts);
    }

    public function testComplexQuery(): void
    {
        $result = $this->builder
            ->select('u.id', 'u.name', 'COUNT(o.id) as order_count')
            ->from('users', 'u')
            ->from('orders', 'o')
            ->groupBy('u.id', 'u.name')
            ->having('COUNT(o.id)', 5, ComparisonOperator::GREATER_THAN)
            ->orderBy('order_count', 'DESC')
            ->limit(20)
            ->offset(40)
            ->distinct();
        
        $this->assertSame($this->builder, $result);
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->select('*')
            ->from('users')
            ->groupBy('department')
            ->orderBy('name')
            ->limit(50);
        
        $this->assertSame($this->builder, $result);
    }

    public function testMultipleFromCalls(): void
    {
        $this->builder
            ->from('users', 'u')
            ->from('profiles', 'p');
        
        $this->assertInstanceOf(SelectBuilder::class, $this->builder);
    }

    public function testMultipleOrderByCalls(): void
    {
        $this->builder
            ->orderBy('department', 'ASC')
            ->orderBy('name', 'DESC')
            ->orderBy('created_at', 'ASC');
        
        $this->assertInstanceOf(SelectBuilder::class, $this->builder);
    }

    public function testGetParameterBag(): void
    {
        $parameterBag = $this->builder->getParameterBag();
        $this->assertInstanceOf(QueryParameterBag::class, $parameterBag);
    }

    public function testToSqlGeneration(): void
    {
        $this->builder
            ->select('id', 'name')
            ->from('users');
        
        $sql = $this->builder->toSql();
        $this->assertIsString($sql);
        $this->assertNotEmpty($sql);
    }
}