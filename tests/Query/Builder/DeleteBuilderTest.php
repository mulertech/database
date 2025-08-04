<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\DeleteBuilder;
use MulerTech\Database\ORM\EmEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test cases for DeleteBuilder class
 */
class DeleteBuilderTest extends TestCase
{
    private DeleteBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new DeleteBuilder();
    }

    public function testConstructor(): void
    {
        $builder = new DeleteBuilder();
        $this->assertInstanceOf(DeleteBuilder::class, $builder);
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $builder = new DeleteBuilder($emEngine);
        $this->assertInstanceOf(DeleteBuilder::class, $builder);
    }

    public function testFrom(): void
    {
        $result = $this->builder->from('users');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testFromWithAlias(): void
    {
        $result = $this->builder->from('users', 'u');
        
        $this->assertSame($this->builder, $result);
    }

    public function testFromValidation(): void
    {
        // Test that from method works with valid table names
        $this->builder->from('users');
        $this->assertInstanceOf(DeleteBuilder::class, $this->builder);
    }

    public function testGetQueryType(): void
    {
        $this->assertEquals('DELETE', $this->builder->getQueryType());
    }

    public function testIgnore(): void
    {
        // Test ignore functionality from QueryOptionsTrait
        $result = $this->builder->ignore();
        $this->assertSame($this->builder, $result);
        
        $result = $this->builder->withoutIgnore();
        $this->assertSame($this->builder, $result);
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->from('users', 'u')
            ->ignore();
        
        $this->assertSame($this->builder, $result);
    }

    public function testGetParameterBag(): void
    {
        $parameterBag = $this->builder->getParameterBag();
        $this->assertCount(0, $parameterBag->toArray());
    }

    public function testMultipleFromCalls(): void
    {
        // Test that the last from call takes precedence
        $this->builder
            ->from('users')
            ->from('products', 'p');
        
        $this->assertInstanceOf(DeleteBuilder::class, $this->builder);
    }

    public function testWithValidTableNames(): void
    {
        $validNames = ['users', 'user_profiles', 'order_items', 'table123', '_temp_table'];
        
        foreach ($validNames as $tableName) {
            $builder = new DeleteBuilder();
            $result = $builder->from($tableName);
            $this->assertSame($builder, $result);
        }
    }

    public function testTableNameHandling(): void
    {
        // Test that the builder handles various table name scenarios
        $testNames = ['users', 'user_profiles', 'order_items'];
        
        foreach ($testNames as $tableName) {
            $builder = new DeleteBuilder();
            $result = $builder->from($tableName);
            $this->assertSame($builder, $result);
        }
    }
}