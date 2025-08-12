<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder\Traits;

use MulerTech\Database\Tests\Files\Query\Builder\TestableQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for QueryOptionsTrait
 */
class QueryOptionsTraitTest extends TestCase
{
    private TestableQueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TestableQueryBuilder();
    }

    public function testIgnore(): void
    {
        $result = $this->builder->ignore();
        
        $this->assertSame($this->builder, $result); // Test fluent interface
    }

    public function testWithoutIgnore(): void
    {
        $result = $this->builder->ignore()->withoutIgnore();
        
        $this->assertSame($this->builder, $result);
    }

    public function testLowPriority(): void
    {
        $result = $this->builder->lowPriority();
        
        $this->assertSame($this->builder, $result);
    }

    public function testWithoutLowPriority(): void
    {
        $result = $this->builder->lowPriority()->withoutLowPriority();
        
        $this->assertSame($this->builder, $result);
    }

    public function testIgnoreToggle(): void
    {
        $this->builder->ignore();
        $this->builder->withoutIgnore();
        $this->builder->ignore();
        
        $this->assertInstanceOf(TestableQueryBuilder::class, $this->builder);
    }

    public function testLowPriorityToggle(): void
    {
        $this->builder->lowPriority();
        $this->builder->withoutLowPriority();
        $this->builder->lowPriority();
        
        $this->assertInstanceOf(TestableQueryBuilder::class, $this->builder);
    }

    public function testChaining(): void
    {
        $result = $this->builder
            ->ignore()
            ->lowPriority()
            ->withoutIgnore()
            ->withoutLowPriority();
        
        $this->assertSame($this->builder, $result);
    }

    public function testBuildQueryModifiersEmpty(): void
    {
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertIsArray($modifiers);
        $this->assertEmpty($modifiers);
    }

    public function testBuildQueryModifiersIgnoreOnly(): void
    {
        $this->builder->ignore();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertIsArray($modifiers);
        $this->assertContains('IGNORE', $modifiers);
        $this->assertNotContains('LOW_PRIORITY', $modifiers);
    }

    public function testBuildQueryModifiersLowPriorityOnly(): void
    {
        $this->builder->lowPriority();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertIsArray($modifiers);
        $this->assertContains('LOW_PRIORITY', $modifiers);
        $this->assertNotContains('IGNORE', $modifiers);
    }

    public function testBuildQueryModifiersBoth(): void
    {
        $this->builder->ignore()->lowPriority();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertIsArray($modifiers);
        $this->assertContains('IGNORE', $modifiers);
        $this->assertContains('LOW_PRIORITY', $modifiers);
        $this->assertEquals(['LOW_PRIORITY', 'IGNORE'], $modifiers);
    }

    public function testBuildQueryModifiersOrder(): void
    {
        $this->builder->lowPriority()->ignore();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        // LOW_PRIORITY should come before IGNORE
        $this->assertEquals(['LOW_PRIORITY', 'IGNORE'], $modifiers);
    }

    public function testBuildQueryModifiersAfterDisabling(): void
    {
        $this->builder->ignore()->lowPriority();
        $this->builder->withoutIgnore()->withoutLowPriority();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertIsArray($modifiers);
        $this->assertEmpty($modifiers);
    }

    public function testComplexToggling(): void
    {
        $this->builder
            ->ignore()
            ->lowPriority()
            ->withoutIgnore()
            ->ignore()
            ->withoutLowPriority()
            ->lowPriority();
        
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertEquals(['LOW_PRIORITY', 'IGNORE'], $modifiers);
    }

    public function testMultipleIgnoreCalls(): void
    {
        $this->builder->ignore()->ignore()->ignore();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertEquals(['IGNORE'], $modifiers);
    }

    public function testMultipleLowPriorityCalls(): void
    {
        $this->builder->lowPriority()->lowPriority()->lowPriority();
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertEquals(['LOW_PRIORITY'], $modifiers);
    }

    public function testMultipleWithoutCalls(): void
    {
        $this->builder
            ->ignore()
            ->lowPriority()
            ->withoutIgnore()
            ->withoutIgnore()
            ->withoutLowPriority()
            ->withoutLowPriority();
        
        $modifiers = $this->builder->testBuildQueryModifiers();
        
        $this->assertEmpty($modifiers);
    }
}