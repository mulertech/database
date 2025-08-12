<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder\Traits;

use MulerTech\Database\Query\Builder\Traits\OrderLimitTrait;
use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for OrderLimitTrait
 */
class OrderLimitTraitTest extends TestCase
{
    private TestableOrderLimitBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TestableOrderLimitBuilder();
    }

    public function testOrderByAsc(): void
    {
        $result = $this->builder->orderBy('name', 'ASC');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testOrderByDesc(): void
    {
        $result = $this->builder->orderBy('created_at', 'DESC');
        
        $this->assertSame($this->builder, $result);
    }

    public function testOrderByDefaultDirection(): void
    {
        $result = $this->builder->orderBy('id');
        
        $this->assertSame($this->builder, $result);
    }

    public function testOrderByInvalidDirection(): void
    {
        // Should default to ASC for invalid directions
        $result = $this->builder->orderBy('name', 'INVALID');
        
        $this->assertSame($this->builder, $result);
    }

    public function testOrderByCaseInsensitive(): void
    {
        $result = $this->builder
            ->orderBy('name', 'asc')
            ->orderBy('age', 'desc')
            ->orderBy('email', 'ASC')
            ->orderBy('phone', 'DESC');
        
        $this->assertSame($this->builder, $result);
    }

    public function testMultipleOrderBy(): void
    {
        $result = $this->builder
            ->orderBy('department', 'ASC')
            ->orderBy('salary', 'DESC')
            ->orderBy('name', 'ASC');
        
        $this->assertSame($this->builder, $result);
    }

    public function testLimit(): void
    {
        $result = $this->builder->limit(10);
        
        $this->assertSame($this->builder, $result);
    }

    public function testLimitZero(): void
    {
        $result = $this->builder->limit(0);
        
        $this->assertSame($this->builder, $result);
    }

    public function testLimitNegative(): void
    {
        // Should be converted to 0
        $result = $this->builder->limit(-5);
        
        $this->assertSame($this->builder, $result);
    }

    public function testLimitLarge(): void
    {
        $result = $this->builder->limit(999999);
        
        $this->assertSame($this->builder, $result);
    }

    public function testBuildOrderByClauseEmpty(): void
    {
        $clause = $this->builder->testBuildOrderByClause();
        
        $this->assertEquals('', $clause);
    }

    public function testBuildOrderByClauseSingle(): void
    {
        $this->builder->orderBy('name', 'ASC');
        $clause = $this->builder->testBuildOrderByClause();
        
        $this->assertStringContainsString('ORDER BY', $clause);
        $this->assertStringContainsString('name', $clause);
        $this->assertStringContainsString('ASC', $clause);
    }

    public function testBuildOrderByClauseMultiple(): void
    {
        $this->builder
            ->orderBy('department', 'ASC')
            ->orderBy('salary', 'DESC');
        
        $clause = $this->builder->testBuildOrderByClause();
        
        $this->assertStringContainsString('ORDER BY', $clause);
        $this->assertStringContainsString('department', $clause);
        $this->assertStringContainsString('salary', $clause);
        $this->assertStringContainsString(',', $clause);
    }

    public function testBuildLimitClauseZero(): void
    {
        $clause = $this->builder->testBuildLimitClause();
        
        $this->assertEquals('', $clause);
    }

    public function testBuildLimitClausePositive(): void
    {
        $this->builder->limit(25);
        $clause = $this->builder->testBuildLimitClause();
        
        $this->assertStringContainsString('LIMIT', $clause);
        $this->assertStringContainsString('25', $clause);
    }

    public function testBuildLimitClauseNegative(): void
    {
        $this->builder->limit(-10);
        $clause = $this->builder->testBuildLimitClause();
        
        $this->assertEquals('', $clause); // Should be empty for negative/zero limits
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->orderBy('name', 'ASC')
            ->orderBy('age', 'DESC')
            ->limit(50);
        
        $this->assertSame($this->builder, $result);
    }

    public function testComplexOrdering(): void
    {
        $this->builder
            ->orderBy('priority', 'DESC')
            ->orderBy('category', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'ASC')
            ->limit(100);
        
        $orderByClause = $this->builder->testBuildOrderByClause();
        $limitClause = $this->builder->testBuildLimitClause();
        
        $this->assertStringContainsString('ORDER BY', $orderByClause);
        $this->assertStringContainsString('priority', $orderByClause);
        $this->assertStringContainsString('category', $orderByClause);
        $this->assertStringContainsString('created_at', $orderByClause);
        $this->assertStringContainsString('id', $orderByClause);
        
        $this->assertStringContainsString('LIMIT 100', $limitClause);
    }
}

/**
 * Testable implementation of a query builder using OrderLimitTrait
 */
class TestableOrderLimitBuilder extends AbstractQueryBuilder
{
    use OrderLimitTrait;
    use SqlFormatterTrait;

    public function getQueryType(): string
    {
        return 'TEST';
    }

    protected function buildSql(): string
    {
        return 'SELECT * FROM test';
    }

    // Expose protected methods for testing
    public function testBuildOrderByClause(): string
    {
        return $this->buildOrderByClause();
    }

    public function testBuildLimitClause(): string
    {
        return $this->buildLimitClause();
    }
}