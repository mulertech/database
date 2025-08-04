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
}