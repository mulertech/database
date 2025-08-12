<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Clause;

use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\JoinConditionBuilder;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\JoinType;
use MulerTech\Database\Core\Parameters\QueryParameterBag;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for JoinConditionBuilder class
 */
class JoinConditionBuilderTest extends TestCase
{
    private JoinClauseBuilder $joinBuilder;
    private JoinConditionBuilder $conditionBuilder;
    private QueryParameterBag $parameterBag;

    protected function setUp(): void
    {
        $this->parameterBag = new QueryParameterBag();
        $this->joinBuilder = new JoinClauseBuilder($this->parameterBag);
        $this->conditionBuilder = $this->joinBuilder->inner('orders', 'o');
    }

    public function testConstructor(): void
    {
        $joinBuilder = new JoinClauseBuilder($this->parameterBag);
        $conditionBuilder = $joinBuilder->inner('users', 'u');
        
        $this->assertInstanceOf(JoinConditionBuilder::class, $conditionBuilder);
    }

    public function testOn(): void
    {
        $result = $this->conditionBuilder->on('users.id', 'o.user_id');
        
        $this->assertSame($this->conditionBuilder, $result); // Test fluent interface
    }

    public function testOnCondition(): void
    {
        $result = $this->conditionBuilder->onCondition('o.amount', ComparisonOperator::GREATER_THAN, 100);
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOnConditionWithEqual(): void
    {
        $result = $this->conditionBuilder->onCondition('users.id', ComparisonOperator::EQUAL, 'o.user_id');
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOnConditionWithNotEqual(): void
    {
        $result = $this->conditionBuilder->onCondition('o.status', ComparisonOperator::NOT_EQUAL, 'cancelled');
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOnConditionWithGreaterThan(): void
    {
        $result = $this->conditionBuilder->onCondition('o.total', ComparisonOperator::GREATER_THAN, 0);
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOnConditionWithLessThan(): void
    {
        $result = $this->conditionBuilder->onCondition('o.discount', ComparisonOperator::LESS_THAN, 50);
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testAndOn(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOn('users.active', 'o.user_active');
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOrOn(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->orOn('users.email', 'o.user_email');
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testAndOnCondition(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOnCondition('o.status', ComparisonOperator::EQUAL, 'active');
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testAndOnConditionWithGreaterThan(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOnCondition('o.amount', ComparisonOperator::GREATER_THAN, 100);
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOrOnCondition(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->orOnCondition('o.priority', ComparisonOperator::EQUAL, 'high');
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testOrOnConditionWithLessThan(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->orOnCondition('o.amount', ComparisonOperator::LESS_THAN, 10);
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testChaining(): void
    {
        $result = $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOn('users.active', 'o.user_active')
            ->orOn('users.email', 'o.user_email')
            ->andOnCondition('o.status', ComparisonOperator::EQUAL, 'pending')
            ->orOnCondition('o.priority', ComparisonOperator::GREATER_THAN, 5);
        
        $this->assertSame($this->conditionBuilder, $result);
    }

    public function testComplexConditions(): void
    {
        $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOnCondition('o.amount', ComparisonOperator::GREATER_THAN, 100)
            ->andOnCondition('o.status', ComparisonOperator::NOT_EQUAL, 'cancelled')
            ->orOnCondition('o.priority', ComparisonOperator::EQUAL, 'urgent')
            ->andOn('users.department_id', 'o.department_id');
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testAllComparisonOperators(): void
    {
        $this->conditionBuilder
            ->onCondition('col1', ComparisonOperator::EQUAL, 'val1')
            ->andOnCondition('col2', ComparisonOperator::NOT_EQUAL, 'val2')
            ->andOnCondition('col3', ComparisonOperator::GREATER_THAN, 'val3')
            ->andOnCondition('col4', ComparisonOperator::LESS_THAN, 'val4')
            ->andOnCondition('col5', ComparisonOperator::GREATER_THAN_OR_EQUAL, 'val5')
            ->andOnCondition('col6', ComparisonOperator::LESS_THAN_OR_EQUAL, 'val6');
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testMixedAndOrConditions(): void
    {
        $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOnCondition('o.status', ComparisonOperator::EQUAL, 'active')
            ->orOnCondition('o.status', ComparisonOperator::EQUAL, 'pending')
            ->andOnCondition('o.amount', ComparisonOperator::GREATER_THAN, 0)
            ->orOn('users.backup_id', 'o.user_id');
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testWithDifferentJoinTypes(): void
    {
        // Test with LEFT JOIN
        $leftJoin = $this->joinBuilder->left('profiles', 'p');
        $leftJoin->on('users.id', 'p.user_id')
                 ->andOnCondition('p.active', ComparisonOperator::EQUAL, 1);
        
        // Test with RIGHT JOIN
        $rightJoin = $this->joinBuilder->right('departments', 'd');
        $rightJoin->on('users.dept_id', 'd.id')
                  ->orOnCondition('d.name', ComparisonOperator::EQUAL, 'default');
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('RIGHT JOIN', $sql);
    }

    public function testMultipleJoinsWithDifferentConditions(): void
    {
        // First join with basic condition
        $this->conditionBuilder->on('users.id', 'o.user_id');
        
        // Second join with complex conditions
        $profileJoin = $this->joinBuilder->left('profiles', 'p');
        $profileJoin->on('users.id', 'p.user_id')
                    ->andOnCondition('p.verified', ComparisonOperator::EQUAL, true)
                    ->orOnCondition('p.status', ComparisonOperator::EQUAL, 'temp');
        
        // Third join with mixed conditions
        $deptJoin = $this->joinBuilder->right('departments', 'd');
        $deptJoin->onCondition('users.dept_id', ComparisonOperator::EQUAL, 'd.id')
                 ->andOn('users.company_id', 'd.company_id')
                 ->orOnCondition('d.is_default', ComparisonOperator::EQUAL, 1);
        
        $this->assertEquals(3, $this->joinBuilder->count());
        
        $sql = $this->joinBuilder->toSql();
        $this->assertStringContainsString('orders', $sql);
        $this->assertStringContainsString('profiles', $sql);  
        $this->assertStringContainsString('departments', $sql);
    }

    public function testConditionsWithScalarValues(): void
    {
        $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOnCondition('o.amount', ComparisonOperator::GREATER_THAN, 100.50)
            ->andOnCondition('o.quantity', ComparisonOperator::EQUAL, 1)
            ->andOnCondition('o.active', ComparisonOperator::EQUAL, true)
            ->andOnCondition('o.notes', ComparisonOperator::NOT_EQUAL, null);
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('ON', $sql);
        
        // Check that parameters were added for scalar values
        $parameters = $this->parameterBag->toArray();
        $this->assertNotEmpty($parameters);
    }

    public function testConditionsWithStringValues(): void
    {
        $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOnCondition('o.status', ComparisonOperator::EQUAL, 'active')
            ->orOnCondition('o.type', ComparisonOperator::NOT_EQUAL, 'cancelled')
            ->andOnCondition('o.priority', ComparisonOperator::EQUAL, 'high');
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('ON', $sql);
    }

    public function testColumnReferences(): void
    {
        // Test that column references are not parameterized
        $this->conditionBuilder
            ->on('users.id', 'o.user_id')
            ->andOn('users.department_id', 'o.department_id')
            ->orOn('users.backup_email', 'o.contact_email');
        
        $sql = $this->joinBuilder->toSql();
        
        $this->assertStringContainsString('`users`.`id`', $sql);
        $this->assertStringContainsString('`o`.`user_id`', $sql);
        $this->assertStringContainsString('`users`.`department_id`', $sql);
        $this->assertStringContainsString('`o`.`department_id`', $sql);
    }

    public function testReadOnlyClass(): void
    {
        // JoinConditionBuilder is readonly, so we can't modify its properties
        // This test just ensures the class behaves as expected
        $this->assertInstanceOf(JoinConditionBuilder::class, $this->conditionBuilder);
        
        // Test that methods return the same instance (fluent interface)
        $result1 = $this->conditionBuilder->on('a', 'b');
        $result2 = $result1->andOn('c', 'd');
        $result3 = $result2->orOnCondition('e', ComparisonOperator::EQUAL, 'f');
        
        $this->assertSame($this->conditionBuilder, $result1);
        $this->assertSame($this->conditionBuilder, $result2);
        $this->assertSame($this->conditionBuilder, $result3);
    }
}