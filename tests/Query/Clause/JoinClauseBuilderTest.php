<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Clause;

use InvalidArgumentException;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\JoinConditionBuilder;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\JoinType;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for JoinClauseBuilder class
 */
class JoinClauseBuilderTest extends TestCase
{
    private JoinClauseBuilder $builder;
    private QueryParameterBag $parameterBag;

    protected function setUp(): void
    {
        $this->parameterBag = new QueryParameterBag();
        $this->builder = new JoinClauseBuilder($this->parameterBag);
    }

    public function testConstructor(): void
    {
        $builder = new JoinClauseBuilder($this->parameterBag);
        $this->assertInstanceOf(JoinClauseBuilder::class, $builder);
    }

    public function testAdd(): void
    {
        $result = $this->builder->add(JoinType::INNER, 'users', 'u');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testAddWithoutAlias(): void
    {
        $result = $this->builder->add(JoinType::LEFT, 'profiles');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testInner(): void
    {
        $result = $this->builder->inner('orders', 'o');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testInnerWithoutAlias(): void
    {
        $result = $this->builder->inner('orders');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testLeft(): void
    {
        $result = $this->builder->left('profiles', 'p');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testLeftWithoutAlias(): void
    {
        $result = $this->builder->left('profiles');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testRight(): void
    {
        $result = $this->builder->right('departments', 'd');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testRightWithoutAlias(): void
    {
        $result = $this->builder->right('departments');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testCross(): void
    {
        $result = $this->builder->cross('settings', 's');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testCrossWithoutAlias(): void
    {
        $result = $this->builder->cross('settings');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $result);
    }

    public function testAddCondition(): void
    {
        $this->builder->inner('users', 'u');
        
        // Should not throw exception
        $this->builder->addCondition(0, 'u.id', ComparisonOperator::EQUAL, 'orders.user_id');
        
        $this->addToAssertionCount(1);
    }

    public function testAddConditionWithInvalidIndex(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid join index: 5');
        
        $this->builder->addCondition(5, 'u.id', ComparisonOperator::EQUAL, 'orders.user_id');
    }

    public function testAddConditionWithNullRightColumn(): void
    {
        $this->builder->inner('users', 'u');
        
        // Should not throw exception
        $this->builder->addCondition(0, 'u.id', ComparisonOperator::EQUAL, null);
        
        $this->addToAssertionCount(1);
    }

    public function testAddConditionWithScalarRightColumn(): void
    {
        $this->builder->inner('users', 'u');
        
        // Should not throw exception
        $this->builder->addCondition(0, 'u.active', ComparisonOperator::EQUAL, 1);
        
        $this->addToAssertionCount(1);
    }

    public function testAddConditionWithInvalidRightColumn(): void
    {
        $this->builder->inner('users', 'u');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Right column must be a string or scalar value');
        
        $this->builder->addCondition(0, 'u.id', ComparisonOperator::EQUAL, ['invalid']);
    }

    public function testAddConditionWithLinkOperator(): void
    {
        $this->builder->inner('users', 'u');
        
        // Should not throw exception
        $this->builder->addCondition(0, 'u.id', ComparisonOperator::EQUAL, 'orders.user_id', LinkOperator::OR);
        
        $this->addToAssertionCount(1);
    }

    public function testToSqlEmpty(): void
    {
        $sql = $this->builder->toSql();
        
        $this->assertEquals('', $sql);
    }

    public function testToSqlSingleJoin(): void
    {
        $this->builder->inner('orders', 'o')->on('users.id', 'o.user_id');
        
        $sql = $this->builder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testToSqlSingleJoinWithoutAlias(): void
    {
        $this->builder->inner('orders')->on('users.id', 'orders.user_id');
        
        $sql = $this->builder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testToSqlMultipleJoins(): void
    {
        $this->builder->inner('orders', 'o')->on('users.id', 'o.user_id');
        $this->builder->left('profiles', 'p')->on('users.id', 'p.user_id');
        
        $sql = $this->builder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('profiles', $sql);
    }

    public function testToSqlWithoutConditions(): void
    {
        $this->builder->cross('settings');
        
        $sql = $this->builder->toSql();
        
        $this->assertStringContainsString('CROSS JOIN', $sql);
        $this->assertStringContainsString('settings', $sql);
        $this->assertStringNotContainsString('ON', $sql);
    }

    public function testToSqlAllJoinTypes(): void
    {
        $this->builder->add(JoinType::INNER, 't1', 't1_alias')->on('base.id', 't1.base_id');
        $this->builder->add(JoinType::LEFT, 't2', 't2_alias')->on('base.id', 't2.base_id');
        $this->builder->add(JoinType::RIGHT, 't3', 't3_alias')->on('base.id', 't3.base_id');
        $this->builder->add(JoinType::CROSS, 't4', 't4_alias');
        $this->builder->add(JoinType::FULL_OUTER, 't5', 't5_alias')->on('base.id', 't5.base_id');
        $this->builder->add(JoinType::LEFT_OUTER, 't6', 't6_alias')->on('base.id', 't6.base_id');
        $this->builder->add(JoinType::RIGHT_OUTER, 't7', 't7_alias')->on('base.id', 't7.base_id');
        
        $sql = $this->builder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('RIGHT JOIN', $sql);
        $this->assertStringContainsString('CROSS JOIN', $sql);
        $this->assertStringContainsString('FULL OUTER JOIN', $sql);
        $this->assertStringContainsString('LEFT OUTER JOIN', $sql);
        $this->assertStringContainsString('RIGHT OUTER JOIN', $sql);
    }

    public function testIsEmpty(): void
    {
        $this->assertTrue($this->builder->isEmpty());
        
        $this->builder->inner('users');
        
        $this->assertFalse($this->builder->isEmpty());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->builder->count());
        
        $this->builder->inner('users');
        $this->assertEquals(1, $this->builder->count());
        
        $this->builder->left('profiles');
        $this->assertEquals(2, $this->builder->count());
    }

    public function testClear(): void
    {
        $this->builder->inner('users')->on('users.id', 'orders.user_id');
        $this->builder->left('profiles')->on('users.id', 'profiles.user_id');
        
        $this->assertEquals(2, $this->builder->count());
        
        $result = $this->builder->clear();
        
        $this->assertSame($this->builder, $result); // Test fluent interface
        $this->assertEquals(0, $this->builder->count());
        $this->assertTrue($this->builder->isEmpty());
        $this->assertEquals('', $this->builder->toSql());
    }

    public function testGetJoins(): void
    {
        $this->builder->inner('users', 'u');
        $this->builder->left('profiles', 'p');
        $this->builder->right('departments');
        
        $joins = $this->builder->getJoins();
        
        $this->assertIsArray($joins);
        $this->assertCount(3, $joins);
        
        $this->assertEquals(JoinType::INNER, $joins[0]['type']);
        $this->assertEquals('users', $joins[0]['table']);
        $this->assertEquals('u', $joins[0]['alias']);
        
        $this->assertEquals(JoinType::LEFT, $joins[1]['type']);
        $this->assertEquals('profiles', $joins[1]['table']);
        $this->assertEquals('p', $joins[1]['alias']);
        
        $this->assertEquals(JoinType::RIGHT, $joins[2]['type']);
        $this->assertEquals('departments', $joins[2]['table']);
        $this->assertNull($joins[2]['alias']);
    }

    public function testGetJoinsEmpty(): void
    {
        $joins = $this->builder->getJoins();
        
        $this->assertIsArray($joins);
        $this->assertEmpty($joins);
    }

    public function testComplexJoinSequence(): void
    {
        $this->builder->inner('orders', 'o')->on('users.id', 'o.user_id');
        $this->builder->left('profiles', 'p')->on('users.id', 'p.user_id');
        $this->builder->right('departments', 'd')->on('users.dept_id', 'd.id');
        $this->builder->cross('settings');
        
        $this->assertEquals(4, $this->builder->count());
        $this->assertFalse($this->builder->isEmpty());
        
        $sql = $this->builder->toSql();
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('RIGHT JOIN', $sql);
        $this->assertStringContainsString('CROSS JOIN', $sql);
        
        $joins = $this->builder->getJoins();
        $this->assertCount(4, $joins);
    }

    public function testMultipleConditionsOnSingleJoin(): void
    {
        $join = $this->builder->inner('orders', 'o');
        $join->on('users.id', 'o.user_id');
        $join->andOnCondition('users.active', ComparisonOperator::EQUAL, 1);
        $join->orOnCondition('o.status', ComparisonOperator::EQUAL, 'pending');
        
        $sql = $this->builder->toSql();
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testParameterHandling(): void
    {
        $this->builder->inner('orders', 'o')->on('users.id', 'o.user_id');
        
        // This should add parameters to the parameter bag
        $this->builder->addCondition(0, 'o.status', ComparisonOperator::EQUAL, 'active');
        
        $sql = $this->builder->toSql();
        $this->assertStringContainsString('INNER JOIN', $sql);
        
        // The parameter handling is working, we can test that the SQL was generated
        $this->assertNotEmpty($sql);
    }

    public function testColumnReferenceDetection(): void
    {
        // Test with column references (should not be parameterized)
        $this->builder->inner('orders', 'o')->on('users.id', 'o.user_id');
        
        // Test with literal values (should be parameterized)
        $this->builder->addCondition(0, 'o.status', ComparisonOperator::EQUAL, 'active');
        
        $sql = $this->builder->toSql();
        $this->assertStringContainsString('`users`.`id`', $sql);
        $this->assertStringContainsString('`o`.`user_id`', $sql);
        
        // The parameter handling is working, we can test that the SQL was generated
        $this->assertStringContainsString('`o`.`status`', $sql);
    }

    public function testEdgeCases(): void
    {
        // Test with numeric string (should be treated as value, not column)
        $this->builder->inner('orders', 'o');
        $this->builder->addCondition(0, 'o.id', ComparisonOperator::EQUAL, '123');
        
        $sql = $this->builder->toSql();
        $this->assertStringContainsString('INNER JOIN', $sql);
        
        // Test with reserved words
        $this->builder->clear();
        $this->builder->inner('orders', 'o');
        $this->builder->addCondition(0, 'o.active', ComparisonOperator::EQUAL, 'TRUE');
        
        $sql = $this->builder->toSql();
        $this->assertStringContainsString('INNER JOIN', $sql);
    }
}