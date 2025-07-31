<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MtManyToOne::class)]
class MtManyToOneTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $relation = new MtManyToOne();
        
        $this->assertNull($relation->targetEntity);
    }

    public function testConstructorWithTargetEntity(): void
    {
        $relation = new MtManyToOne(targetEntity: 'App\\Entity\\User');
        
        $this->assertEquals('App\\Entity\\User', $relation->targetEntity);
    }

    public function testAttributeTargetsProperty(): void
    {
        $reflection = new ReflectionClass(MtManyToOne::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function testManyToOneWithDifferentEntityTypes(): void
    {
        $userRelation = new MtManyToOne(targetEntity: 'App\\Entity\\User');
        $categoryRelation = new MtManyToOne(targetEntity: 'App\\Entity\\Category');
        $productRelation = new MtManyToOne(targetEntity: 'App\\Entity\\Product');
        
        $this->assertEquals('App\\Entity\\User', $userRelation->targetEntity);
        $this->assertEquals('App\\Entity\\Category', $categoryRelation->targetEntity);
        $this->assertEquals('App\\Entity\\Product', $productRelation->targetEntity);
    }

    public function testManyToOneWithComplexNamespace(): void
    {
        $relation = new MtManyToOne(
            targetEntity: 'Very\\Long\\Namespace\\Path\\To\\ComplexEntity'
        );
        
        $this->assertEquals('Very\\Long\\Namespace\\Path\\To\\ComplexEntity', $relation->targetEntity);
    }

    public function testManyToOneWithTestEntity(): void
    {
        $relation = new MtManyToOne(
            targetEntity: 'MulerTech\\Database\\Tests\\Entity\\TestEntity'
        );
        
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\TestEntity', $relation->targetEntity);
    }

    public function testMultipleManyToOneRelations(): void
    {
        $relations = [
            new MtManyToOne(targetEntity: 'App\\Entity\\Author'),
            new MtManyToOne(targetEntity: 'App\\Entity\\Publisher'),
            new MtManyToOne(targetEntity: 'App\\Entity\\Genre'),
        ];
        
        $this->assertEquals('App\\Entity\\Author', $relations[0]->targetEntity);
        $this->assertEquals('App\\Entity\\Publisher', $relations[1]->targetEntity);
        $this->assertEquals('App\\Entity\\Genre', $relations[2]->targetEntity);
    }

    public function testManyToOneWithNullTargetEntity(): void
    {
        $relation = new MtManyToOne(targetEntity: null);
        
        $this->assertNull($relation->targetEntity);
    }

    public function testManyToOneConstructorParameterHandling(): void
    {
        // Test with explicit null
        $relation1 = new MtManyToOne(targetEntity: null);
        $this->assertNull($relation1->targetEntity);
        
        // Test with no parameters (default)
        $relation2 = new MtManyToOne();
        $this->assertNull($relation2->targetEntity);
        
        // Test with entity
        $relation3 = new MtManyToOne(targetEntity: 'Test\\Entity');
        $this->assertEquals('Test\\Entity', $relation3->targetEntity);
    }
}