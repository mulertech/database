<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder\Traits;

use InvalidArgumentException;
use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;
use MulerTech\Database\Tests\Files\Query\Builder\TestableQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for WhereClauseTrait
 */
class WhereClauseTraitTest extends TestCase
{
    private TestableQueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TestableQueryBuilder();
    }

    public function testWhere(): void
    {
        $result = $this->builder->where('name', 'John');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testWhereWithOperator(): void
    {
        $result = $this->builder->where('age', 18, ComparisonOperator::GREATER_THAN);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereWithSqlOperator(): void
    {
        $result = $this->builder->where('name', ['John', 'Jane'], SqlOperator::IN);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereWithLinkOperator(): void
    {
        $result = $this->builder->where('name', 'John', ComparisonOperator::EQUAL, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereEqual(): void
    {
        $result = $this->builder->whereEqual('name', 'John');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereEqualWithLinkOperator(): void
    {
        $result = $this->builder->whereEqual('name', 'John', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotEqual(): void
    {
        $result = $this->builder->whereNotEqual('status', 'inactive');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotEqualWithLinkOperator(): void
    {
        $result = $this->builder->whereNotEqual('status', 'inactive', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereGreaterThan(): void
    {
        $result = $this->builder->whereGreaterThan('age', 18);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereGreaterThanWithLinkOperator(): void
    {
        $result = $this->builder->whereGreaterThan('age', 18, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLessThan(): void
    {
        $result = $this->builder->whereLessThan('age', 65);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLessThanWithLinkOperator(): void
    {
        $result = $this->builder->whereLessThan('age', 65, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereGreaterOrEqual(): void
    {
        $result = $this->builder->whereGreaterOrEqual('score', 90);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereGreaterOrEqualWithLinkOperator(): void
    {
        $result = $this->builder->whereGreaterOrEqual('score', 90, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotGreaterThan(): void
    {
        $result = $this->builder->whereNotGreaterThan('price', 100);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotGreaterThanWithLinkOperator(): void
    {
        $result = $this->builder->whereNotGreaterThan('price', 100, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLessOrEqual(): void
    {
        $result = $this->builder->whereLessOrEqual('discount', 50);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLessOrEqualWithLinkOperator(): void
    {
        $result = $this->builder->whereLessOrEqual('discount', 50, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotLessThan(): void
    {
        $result = $this->builder->whereNotLessThan('minimum_age', 18);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotLessThanWithLinkOperator(): void
    {
        $result = $this->builder->whereNotLessThan('minimum_age', 18, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLike(): void
    {
        $result = $this->builder->whereLike('name', '%John%');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLikeWithLinkOperator(): void
    {
        $result = $this->builder->whereLike('name', '%John%', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLikeWithNullPattern(): void
    {
        $result = $this->builder->whereLike('name', null);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLikeWithScalarPattern(): void
    {
        $result = $this->builder->whereLike('id', 123);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereLikeWithInvalidPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pattern must be a string or scalar value');
        
        $this->builder->whereLike('name', ['invalid']);
    }

    public function testWhereNotLike(): void
    {
        $result = $this->builder->whereNotLike('name', '%spam%');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotLikeWithLinkOperator(): void
    {
        $result = $this->builder->whereNotLike('name', '%spam%', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotLikeWithNullPattern(): void
    {
        $result = $this->builder->whereNotLike('name', null);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotLikeWithScalarPattern(): void
    {
        $result = $this->builder->whereNotLike('id', 123);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotLikeWithInvalidPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pattern must be a string or scalar value');
        
        $this->builder->whereNotLike('name', ['invalid']);
    }

    public function testWhereIn(): void
    {
        $result = $this->builder->whereIn('status', ['active', 'pending']);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereInWithLinkOperator(): void
    {
        $result = $this->builder->whereIn('status', ['active', 'pending'], LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereInWithSubquery(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->whereIn('user_id', $subquery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotIn(): void
    {
        $result = $this->builder->whereNotIn('status', ['inactive', 'banned']);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotInWithLinkOperator(): void
    {
        $result = $this->builder->whereNotIn('status', ['inactive', 'banned'], LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotInWithSubquery(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->whereNotIn('user_id', $subquery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereBetween(): void
    {
        $result = $this->builder->whereBetween('age', 18, 65);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereBetweenWithLinkOperator(): void
    {
        $result = $this->builder->whereBetween('age', 18, 65, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotBetween(): void
    {
        $result = $this->builder->whereNotBetween('score', 0, 50);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotBetweenWithLinkOperator(): void
    {
        $result = $this->builder->whereNotBetween('score', 0, 50, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNull(): void
    {
        $result = $this->builder->whereNull('deleted_at');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNullWithLinkOperator(): void
    {
        $result = $this->builder->whereNull('deleted_at', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotNull(): void
    {
        $result = $this->builder->whereNotNull('email');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereNotNullWithLinkOperator(): void
    {
        $result = $this->builder->whereNotNull('email', LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereRaw(): void
    {
        $result = $this->builder->whereRaw('DATE(created_at) = :date', ['date' => '2023-01-01']);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereRawWithLinkOperator(): void
    {
        $result = $this->builder->whereRaw('DATE(created_at) = :date', ['date' => '2023-01-01'], LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereRawWithoutParameters(): void
    {
        $result = $this->builder->whereRaw('active = 1');
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereGroup(): void
    {
        $result = $this->builder->whereGroup(function (WhereClauseBuilder $where) {
            $where->equal('name', 'John');
            $where->equal('name', 'Jane', LinkOperator::OR);
        });
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereGroupWithLinkOperator(): void
    {
        $result = $this->builder->whereGroup(function (WhereClauseBuilder $where) {
            $where->equal('status', 'active');
        }, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereExists(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->whereExists($subquery);
        
        $this->assertSame($this->builder, $result);
    }

    public function testWhereExistsWithLinkOperator(): void
    {
        $subquery = new SelectBuilder();
        $result = $this->builder->whereExists($subquery, LinkOperator::OR);
        
        $this->assertSame($this->builder, $result);
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->where('name', 'John')
            ->whereEqual('status', 'active')
            ->whereGreaterThan('age', 18)
            ->whereLike('email', '%@example.com')
            ->whereIn('department', ['IT', 'HR'])
            ->whereNull('deleted_at');
        
        $this->assertSame($this->builder, $result);
    }

    public function testComplexConditions(): void
    {
        $this->builder
            ->where('name', 'John', ComparisonOperator::EQUAL, LinkOperator::AND)
            ->whereNotEqual('status', 'banned', LinkOperator::OR)
            ->whereGreaterOrEqual('age', 21, LinkOperator::AND)
            ->whereLessOrEqual('score', 100, LinkOperator::AND)
            ->whereBetween('salary', 30000, 80000, LinkOperator::AND)
            ->whereNotBetween('overtime_hours', 40, 60, LinkOperator::OR)
            ->whereNotNull('email', LinkOperator::AND)
            ->whereRaw('YEAR(created_at) = :year', ['year' => 2023], LinkOperator::AND);
        
        $this->assertInstanceOf(TestableQueryBuilder::class, $this->builder);
    }

    public function testAllComparisonOperators(): void
    {
        $this->builder
            ->whereEqual('col1', 'val1')
            ->whereNotEqual('col2', 'val2')
            ->whereGreaterThan('col3', 'val3')
            ->whereLessThan('col4', 'val4')
            ->whereGreaterOrEqual('col5', 'val5')
            ->whereLessOrEqual('col6', 'val6')
            ->whereNotGreaterThan('col7', 'val7')
            ->whereNotLessThan('col8', 'val8');
        
        $this->assertInstanceOf(TestableQueryBuilder::class, $this->builder);
    }

    public function testAllSpecialOperators(): void
    {
        $subquery = new SelectBuilder();
        
        $this->builder
            ->whereLike('name', '%pattern%')
            ->whereNotLike('email', '%spam%')
            ->whereIn('status', ['active', 'pending'])
            ->whereNotIn('role', ['banned', 'suspended'])
            ->whereBetween('age', 18, 65)
            ->whereNotBetween('score', 0, 25)
            ->whereNull('deleted_at')
            ->whereNotNull('verified_at')
            ->whereExists($subquery)
            ->whereRaw('custom_condition = 1');
        
        $this->assertInstanceOf(TestableQueryBuilder::class, $this->builder);
    }

    public function testMixedLinkOperators(): void
    {
        $this->builder
            ->where('name', 'John', ComparisonOperator::EQUAL, LinkOperator::AND)
            ->where('age', 25, ComparisonOperator::GREATER_THAN, LinkOperator::OR)
            ->whereEqual('status', 'active', LinkOperator::AND)
            ->whereNotEqual('type', 'guest', LinkOperator::OR);
        
        $this->assertInstanceOf(TestableQueryBuilder::class, $this->builder);
    }
}