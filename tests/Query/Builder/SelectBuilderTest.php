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

    public function testGroupByClauseSQL(): void
    {
        $sql = $this->builder
            ->select('department', 'COUNT(*) as count')
            ->from('employees')
            ->groupBy('department')
            ->toSql();
        
        $this->assertStringContainsString('SELECT `department`, COUNT(*) AS `count`', $sql);
        $this->assertStringContainsString('FROM `employees`', $sql);
        $this->assertStringContainsString('GROUP BY `department`', $sql);
    }

    public function testGroupByMultipleColumns(): void
    {
        $sql = $this->builder
            ->select('department', 'status', 'COUNT(*) as count')
            ->from('employees')
            ->groupBy('department', 'status')
            ->toSql();
        
        $this->assertStringContainsString('GROUP BY `department`, `status`', $sql);
    }

    public function testHavingClauseSQL(): void
    {
        $sql = $this->builder
            ->select('department', 'COUNT(*) as count')
            ->from('employees')
            ->groupBy('department')
            ->having('COUNT(*)', 5, ComparisonOperator::GREATER_THAN)
            ->toSql();
        
        $this->assertStringContainsString('GROUP BY `department`', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('COUNT(*)', $sql);
    }

    public function testHavingWithMultipleConditions(): void
    {
        $sql = $this->builder
            ->select('department', 'COUNT(*) as count', 'AVG(salary) as avg_salary')
            ->from('employees')
            ->groupBy('department')
            ->having('COUNT(*)', 5, ComparisonOperator::GREATER_THAN)
            ->having('AVG(salary)', 50000, ComparisonOperator::GREATER_THAN)
            ->toSql();
        
        $this->assertStringContainsString('HAVING', $sql);
    }

    public function testInvalidFromClauseException(): void
    {
        // This is a more complex test since we need to create an invalid FROM structure
        // We'll manipulate the internal state to create an invalid condition
        $reflection = new \ReflectionClass($this->builder);
        $fromProperty = $reflection->getProperty('from');
        $fromProperty->setAccessible(true);
        
        // Create an invalid FROM entry (missing both table and subquery)
        $fromProperty->setValue($this->builder, [
            ['alias' => 'invalid']  // Missing 'table' or 'subquery' key
        ]);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid FROM clause: missing table or subquery.');
        
        $this->builder->generateFromParts();
    }

    public function testSubqueryInFromClause(): void
    {
        $subquery = new SelectBuilder();
        $subquery->select('id', 'name')->from('active_users')->where('status', 'active');
        
        $sql = $this->builder
            ->select('*')
            ->from($subquery, 'au')
            ->toSql();
        
        $this->assertStringContainsString('SELECT * FROM (', $sql);
        $this->assertStringContainsString('SELECT `id`, `name` FROM `active_users`', $sql);
        $this->assertStringContainsString(') AS `au`', $sql);
    }

    public function testSubqueryInFromClauseWithoutAlias(): void
    {
        $subquery = new SelectBuilder();
        $subquery->select('COUNT(*)')->from('orders');
        
        $sql = $this->builder
            ->select('*')
            ->from($subquery)
            ->toSql();
        
        $this->assertStringContainsString('SELECT * FROM (', $sql);
        $this->assertStringContainsString('SELECT COUNT(*) FROM `orders`', $sql);
        $this->assertStringContainsString(')', $sql);
        $this->assertStringNotContainsString(' AS ', $sql);
    }

    public function testComplexQueryWithAllClauses(): void
    {
        $subquery = new SelectBuilder();
        $subquery->select('user_id', 'COUNT(*) as order_count')
                 ->from('orders')
                 ->groupBy('user_id');
        
        $sql = $this->builder
            ->select('u.name', 'oc.order_count')
            ->from('users', 'u')
            ->from($subquery, 'oc')
            ->groupBy('u.id', 'u.name')
            ->having('oc.order_count', 3, ComparisonOperator::GREATER_THAN)
            ->orderBy('oc.order_count', 'DESC')
            ->limit(10)
            ->offset(5)
            ->distinct()
            ->toSql();
        
        $this->assertStringStartsWith('SELECT DISTINCT', $sql);
        $this->assertStringContainsString('FROM `users` AS `u`', $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
    }
}