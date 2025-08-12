<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder\Traits;

use MulerTech\Database\Query\Builder\Traits\JoinClauseTrait;
use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Types\JoinType;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\ORM\EmEngine;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for JoinClauseTrait
 */
class JoinClauseTraitTest extends TestCase
{
    private TestableJoinClauseBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TestableJoinClauseBuilder();
    }

    public function testJoin(): void
    {
        $result = $this->builder->join(
            JoinType::INNER,
            'orders',
            'users.id',
            'orders.user_id',
            'o'
        );
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testJoinWithoutAlias(): void
    {
        $result = $this->builder->join(
            JoinType::LEFT,
            'profiles',
            'users.id',
            'profiles.user_id'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testInnerJoin(): void
    {
        $result = $this->builder->innerJoin(
            'orders',
            'users.id',
            'orders.user_id',
            'o'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testInnerJoinWithoutAlias(): void
    {
        $result = $this->builder->innerJoin(
            'orders',
            'users.id',
            'orders.user_id'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testLeftJoin(): void
    {
        $result = $this->builder->leftJoin(
            'profiles',
            'users.id',
            'profiles.user_id',
            'p'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testLeftJoinWithoutAlias(): void
    {
        $result = $this->builder->leftJoin(
            'profiles',
            'users.id',
            'profiles.user_id'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testRightJoin(): void
    {
        $result = $this->builder->rightJoin(
            'departments',
            'users.department_id',
            'departments.id',
            'd'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testRightJoinWithoutAlias(): void
    {
        $result = $this->builder->rightJoin(
            'departments',
            'users.department_id',
            'departments.id'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testCrossJoin(): void
    {
        $result = $this->builder->crossJoin('settings', null, null, 's');
        
        $this->assertSame($this->builder, $result);
    }

    public function testCrossJoinWithColumns(): void
    {
        $result = $this->builder->crossJoin(
            'categories',
            'products.category_id',
            'categories.id',
            'c'
        );
        
        $this->assertSame($this->builder, $result);
    }

    public function testCrossJoinMinimal(): void
    {
        $result = $this->builder->crossJoin('tags');
        
        $this->assertSame($this->builder, $result);
    }

    public function testMultipleJoins(): void
    {
        $result = $this->builder
            ->innerJoin('orders', 'users.id', 'orders.user_id', 'o')
            ->leftJoin('profiles', 'users.id', 'profiles.user_id', 'p')
            ->rightJoin('departments', 'users.dept_id', 'departments.id', 'd')
            ->crossJoin('settings', null, null, 's');
        
        $this->assertSame($this->builder, $result);
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->join(JoinType::INNER, 'orders', 'users.id', 'orders.user_id')
            ->join(JoinType::LEFT, 'profiles', 'users.id', 'profiles.user_id')
            ->join(JoinType::RIGHT, 'departments', 'users.dept_id', 'departments.id');
        
        $this->assertSame($this->builder, $result);
    }

    public function testAllJoinTypes(): void
    {
        $this->builder
            ->join(JoinType::INNER, 't1', 'base.id', 't1.base_id')
            ->join(JoinType::LEFT, 't2', 'base.id', 't2.base_id')
            ->join(JoinType::RIGHT, 't3', 'base.id', 't3.base_id')
            ->join(JoinType::CROSS, 't4', 'base.id', 't4.base_id')
            ->join(JoinType::FULL_OUTER, 't5', 'base.id', 't5.base_id')
            ->join(JoinType::LEFT_OUTER, 't6', 'base.id', 't6.base_id')
            ->join(JoinType::RIGHT_OUTER, 't7', 'base.id', 't7.base_id');
        
        $this->assertInstanceOf(TestableJoinClauseBuilder::class, $this->builder);
    }
}

/**
 * Testable implementation of a query builder using JoinClauseTrait
 */
class TestableJoinClauseBuilder extends AbstractQueryBuilder
{
    use JoinClauseTrait;

    public function __construct()
    {
        parent::__construct();
        $this->joinBuilder = new JoinClauseBuilder($this->parameterBag);
    }

    public function getQueryType(): string
    {
        return 'TEST';
    }

    protected function buildSql(): string
    {
        return 'SELECT * FROM test';
    }
}