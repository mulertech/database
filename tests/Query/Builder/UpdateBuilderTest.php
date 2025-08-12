<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\UpdateBuilder;
use MulerTech\Database\Query\Builder\Raw;
use MulerTech\Database\ORM\EmEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test cases for UpdateBuilder class
 */
class UpdateBuilderTest extends TestCase
{
    private UpdateBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new UpdateBuilder();
    }

    public function testConstructor(): void
    {
        $builder = new UpdateBuilder();
        $this->assertInstanceOf(UpdateBuilder::class, $builder);
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $builder = new UpdateBuilder($emEngine);
        $this->assertInstanceOf(UpdateBuilder::class, $builder);
    }

    public function testTable(): void
    {
        $result = $this->builder->table('users');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testTableWithAlias(): void
    {
        $result = $this->builder->table('users', 'u');
        
        $this->assertSame($this->builder, $result);
    }

    public function testTableValidation(): void
    {
        // Test that table method works with valid names
        $this->builder->table('users');
        $this->assertInstanceOf(UpdateBuilder::class, $this->builder);
    }

    public function testSet(): void
    {
        $result = $this->builder->set('name', 'John Doe');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testSetWithRawValue(): void
    {
        $raw = new Raw('NOW()');
        $result = $this->builder->set('updated_at', $raw);
        
        $this->assertSame($this->builder, $result);
    }

    public function testSetValidation(): void
    {
        // Test that set method works with valid column names
        $this->builder->set('name', 'value');
        $this->assertInstanceOf(UpdateBuilder::class, $this->builder);
    }

    public function testSetMultiple(): void
    {
        $result = $this->builder
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('age', 30);
        
        $this->assertSame($this->builder, $result);
    }

    public function testSetMultipleWithRawValues(): void
    {
        $result = $this->builder
            ->set('name', 'John Doe')
            ->set('updated_at', new Raw('NOW()'))
            ->set('view_count', new Raw('view_count + 1'));
        
        $this->assertSame($this->builder, $result);
    }

    public function testIncrement(): void
    {
        $result = $this->builder->increment('view_count');
        
        $this->assertSame($this->builder, $result);
    }

    public function testIncrementWithAmount(): void
    {
        $result = $this->builder->increment('score', 10);
        
        $this->assertSame($this->builder, $result);
    }

    public function testDecrement(): void
    {
        $result = $this->builder->decrement('stock');
        
        $this->assertSame($this->builder, $result);
    }

    public function testDecrementWithAmount(): void
    {
        $result = $this->builder->decrement('balance', 50);
        
        $this->assertSame($this->builder, $result);
    }

    public function testGetQueryType(): void
    {
        $this->assertEquals('UPDATE', $this->builder->getQueryType());
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->table('users', 'u')
            ->set('name', 'John')
            ->set('email', 'john@example.com')
            ->increment('login_count')
            ->set('updated_at', new Raw('NOW()'));
        
        $this->assertSame($this->builder, $result);
    }

    public function testIgnore(): void
    {
        // Test ignore functionality from QueryOptionsTrait
        $result = $this->builder->ignore();
        $this->assertSame($this->builder, $result);
        
        $result = $this->builder->withoutIgnore();
        $this->assertSame($this->builder, $result);
    }

    public function testMultipleSetCalls(): void
    {
        $this->builder
            ->table('users')
            ->set('name', 'John')
            ->set('email', 'john@example.com')
            ->set('age', 30)
            ->set('updated_at', new Raw('NOW()'));
        
        $this->assertInstanceOf(UpdateBuilder::class, $this->builder);
    }

    public function testGetParameterBag(): void
    {
        $this->builder
            ->set('name', 'John')
            ->set('age', 30);
        
        $parameterBag = $this->builder->getParameterBag();
        $this->assertCount(2, $parameterBag->toArray());
    }

    public function testIncrementValidation(): void
    {
        // Test that increment method works with valid column names
        $this->builder->increment('count');
        $this->assertInstanceOf(UpdateBuilder::class, $this->builder);
    }

    public function testDecrementValidation(): void
    {
        // Test that decrement method works with valid column names
        $this->builder->decrement('stock');
        $this->assertInstanceOf(UpdateBuilder::class, $this->builder);
    }

    public function testComplexUpdate(): void
    {
        $this->builder
            ->table('users', 'u')
            ->set('name', 'Updated Name')
            ->set('updated_at', new Raw('NOW()'))
            ->increment('login_count')
            ->decrement('failed_login_attempts');
        
        $this->assertInstanceOf(UpdateBuilder::class, $this->builder);
    }

    public function testQueryModifiers(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('name', 'John')
            ->ignore()
            ->toSql();
        
        $this->assertStringContainsString('UPDATE IGNORE', $sql);
    }

    public function testQueryModifiersWithLowPriority(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('name', 'John')
            ->lowPriority()
            ->toSql();
        
        $this->assertStringContainsString('UPDATE LOW_PRIORITY', $sql);
    }

    public function testQueryModifiersIgnoreAndLowPriority(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('name', 'John')
            ->ignore()
            ->lowPriority()
            ->toSql();
        
        $this->assertStringContainsString('UPDATE LOW_PRIORITY IGNORE', $sql);
    }

    public function testJoinClause(): void
    {
        $sql = $this->builder
            ->table('users', 'u')
            ->innerJoin('profiles', 'u.id', 'p.user_id', 'p')
            ->set('u.name', 'Updated Name')
            ->toSql();
        
        $this->assertStringContainsString('UPDATE `users` AS `u`', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('`profiles` AS `p`', $sql);
        $this->assertStringContainsString('`u`.`id` = `p`.`user_id`', $sql);
    }

    public function testJoinClauseWithLeftJoin(): void
    {
        $sql = $this->builder
            ->table('users', 'u')
            ->leftJoin('profiles', 'u.id', 'p.user_id', 'p')
            ->set('u.status', 'active')
            ->toSql();
        
        $this->assertStringContainsString('UPDATE `users` AS `u`', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('`profiles` AS `p`', $sql);
    }

    public function testEmptySetValuesException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No SET values specified for UPDATE');
        
        $this->builder
            ->table('users')
            ->toSql();
    }

    public function testOrderByClause(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('name', 'Updated Name')
            ->orderBy('created_at', 'ASC')
            ->toSql();
        
        $this->assertStringContainsString('UPDATE `users`', $sql);
        $this->assertStringContainsString('SET `name` = ', $sql);
        $this->assertStringContainsString('ORDER BY `created_at` ASC', $sql);
    }

    public function testOrderByClauseWithMultipleColumns(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('status', 'updated')
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->toSql();
        
        $this->assertStringContainsString('ORDER BY `priority` DESC, `created_at` ASC', $sql);
    }

    public function testLimitClause(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('status', 'inactive')
            ->orderBy('last_login', 'ASC')
            ->limit(100)
            ->toSql();
        
        $this->assertStringContainsString('UPDATE `users`', $sql);
        $this->assertStringContainsString('SET `status` = ', $sql);
        $this->assertStringContainsString('ORDER BY `last_login` ASC', $sql);
        $this->assertStringContainsString('LIMIT 100', $sql);
    }

    public function testLimitClauseWithOrderBy(): void
    {
        $sql = $this->builder
            ->table('users')
            ->set('processed', 1)
            ->orderBy('created_at', 'ASC')
            ->limit(50)
            ->toSql();
        
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 50', $sql);
    }

    public function testEmptyTablesException(): void
    {
        // We need to access the buildTablesClause method indirectly
        // by calling toSql() without setting a table first
        $this->builder->set('name', 'test');
        
        // Use reflection to access the private method
        $reflection = new \ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildTablesClause');
        $method->setAccessible(true);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No table specified for UPDATE');
        
        $method->invoke($this->builder);
    }

    public function testComplexUpdateWithAllFeatures(): void
    {
        $sql = $this->builder
            ->table('users', 'u')
            ->innerJoin('profiles', 'u.id', 'p.user_id', 'p')
            ->set('u.name', 'Updated Name')
            ->set('u.updated_at', new Raw('NOW()'))
            ->increment('u.login_count')
            ->where('u.status', 'active')
            ->orderBy('u.last_login', 'ASC')
            ->limit(10)
            ->ignore()
            ->toSql();
        
        $this->assertStringContainsString('UPDATE IGNORE `users` AS `u`', $sql);
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('SET', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }
}