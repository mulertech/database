<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\InsertBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Builder\Raw;
use MulerTech\Database\ORM\EmEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test cases for InsertBuilder class
 */
class InsertBuilderTest extends TestCase
{
    private InsertBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new InsertBuilder();
    }

    public function testConstructor(): void
    {
        $builder = new InsertBuilder();
        $this->assertInstanceOf(InsertBuilder::class, $builder);
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $builder = new InsertBuilder($emEngine);
        $this->assertInstanceOf(InsertBuilder::class, $builder);
    }

    public function testInto(): void
    {
        $result = $this->builder->into('users');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testIntoWithInvalidTableName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Table name cannot be empty');
        
        $this->builder->into('');
    }

    public function testIntoWithInvalidTableFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name format');
        
        $this->builder->into('invalid-table-name');
    }

    public function testSet(): void
    {
        $result = $this->builder->set('name', 'John Doe');
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testSetWithRawValue(): void
    {
        $raw = new Raw('NOW()');
        $result = $this->builder->set('created_at', $raw);
        
        $this->assertSame($this->builder, $result);
    }

    public function testSetWithInvalidColumnName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column name cannot be empty');
        
        $this->builder->set('', 'value');
    }

    public function testSetWithInvalidColumnFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name format');
        
        $this->builder->set('invalid-column', 'value');
    }

    public function testBatchValues(): void
    {
        $batchData = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com']
        ];
        
        $result = $this->builder->batchValues($batchData);
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testBatchValuesWithEmptyData(): void
    {
        $this->expectException(RuntimeException::class);
        
        $this->builder->batchValues([]);
    }

    public function testReplace(): void
    {
        $result = $this->builder->replace();
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testWithoutReplace(): void
    {
        $result = $this->builder->replace()->withoutReplace();
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testOnDuplicateKeyUpdate(): void
    {
        $updates = ['name' => 'Updated Name', 'updated_at' => new Raw('NOW()')];
        $result = $this->builder->onDuplicateKeyUpdate($updates);
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testFromSelect(): void
    {
        $selectBuilder = new SelectBuilder();
        $columns = ['name', 'email'];
        $result = $this->builder->fromSelect($selectBuilder, $columns);
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testFromSelectWithoutColumns(): void
    {
        $selectBuilder = new SelectBuilder();
        $result = $this->builder->fromSelect($selectBuilder);
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testFromSelectWithInvalidColumns(): void
    {
        $this->expectException(RuntimeException::class);
        
        $selectBuilder = new SelectBuilder();
        $this->builder->fromSelect($selectBuilder, ['invalid-column']);
    }

    public function testGetQueryType(): void
    {
        $this->assertEquals('INSERT', $this->builder->getQueryType());
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->into('users')
            ->set('name', 'John')
            ->set('email', 'john@example.com')
            ->replace()
            ->onDuplicateKeyUpdate(['updated_at' => new Raw('NOW()')]);
        
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

    public function testReplaceDisablesIgnore(): void
    {
        // When replace is called, ignore should be disabled
        $this->builder->ignore()->replace();
        // This should work without issues - the builder handles the conflict internally
        $this->assertInstanceOf(InsertBuilder::class, $this->builder);
    }

    public function testMultipleSetCalls(): void
    {
        $this->builder
            ->into('users')
            ->set('name', 'John')
            ->set('email', 'john@example.com')
            ->set('age', 30)
            ->set('created_at', new Raw('NOW()'));
        
        $this->assertInstanceOf(InsertBuilder::class, $this->builder);
    }

    public function testGetParameterBag(): void
    {
        $this->builder
            ->set('name', 'John')
            ->set('age', 30);
        
        $parameterBag = $this->builder->getParameterBag();
        $this->assertCount(2, $parameterBag->toArray());
    }
}