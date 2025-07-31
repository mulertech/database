<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MtManyToMany::class)]
class MtManyToManyTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $relation = new MtManyToMany();
        
        $this->assertNull($relation->entity);
        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->mappedBy);
        $this->assertNull($relation->joinProperty);
        $this->assertNull($relation->inverseJoinProperty);
    }

    public function testConstructorWithAllParameters(): void
    {
        $relation = new MtManyToMany(
            entity: 'App\\Entity\\User',
            targetEntity: 'App\\Entity\\Role',
            mappedBy: 'App\\Entity\\UserRole',
            joinProperty: 'user_id',
            inverseJoinProperty: 'role_id'
        );
        
        $this->assertEquals('App\\Entity\\User', $relation->entity);
        $this->assertEquals('App\\Entity\\Role', $relation->targetEntity);
        $this->assertEquals('App\\Entity\\UserRole', $relation->mappedBy);
        $this->assertEquals('user_id', $relation->joinProperty);
        $this->assertEquals('role_id', $relation->inverseJoinProperty);
    }

    public function testAttributeTargetsProperty(): void
    {
        $reflection = new ReflectionClass(MtManyToMany::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function testConstructorWithPartialParameters(): void
    {
        $relation = new MtManyToMany(
            targetEntity: 'App\\Entity\\Tag',
            mappedBy: 'App\\Entity\\PostTag'
        );
        
        $this->assertNull($relation->entity);
        $this->assertEquals('App\\Entity\\Tag', $relation->targetEntity);
        $this->assertEquals('App\\Entity\\PostTag', $relation->mappedBy);
        $this->assertNull($relation->joinProperty);
        $this->assertNull($relation->inverseJoinProperty);
    }

    public function testManyToManyWithJoinProperties(): void
    {
        $relation = new MtManyToMany(
            targetEntity: 'App\\Entity\\Product',
            joinProperty: 'category_id',
            inverseJoinProperty: 'product_id'
        );
        
        $this->assertEquals('App\\Entity\\Product', $relation->targetEntity);
        $this->assertEquals('category_id', $relation->joinProperty);
        $this->assertEquals('product_id', $relation->inverseJoinProperty);
        $this->assertNull($relation->mappedBy);
    }

    public function testManyToManyWithTargetEntityOnly(): void
    {
        $relation = new MtManyToMany(targetEntity: 'App\\Entity\\Permission');
        
        $this->assertNull($relation->entity);
        $this->assertEquals('App\\Entity\\Permission', $relation->targetEntity);
        $this->assertNull($relation->mappedBy);
        $this->assertNull($relation->joinProperty);
        $this->assertNull($relation->inverseJoinProperty);
    }

    public function testManyToManyWithPivotEntity(): void
    {
        $relation = new MtManyToMany(
            entity: 'App\\Entity\\Student',
            targetEntity: 'App\\Entity\\Course',
            mappedBy: 'App\\Entity\\Enrollment'
        );
        
        $this->assertEquals('App\\Entity\\Student', $relation->entity);
        $this->assertEquals('App\\Entity\\Course', $relation->targetEntity);
        $this->assertEquals('App\\Entity\\Enrollment', $relation->mappedBy);
    }

    public function testManyToManyRelationshipProperties(): void
    {
        $relation = new MtManyToMany(
            entity: 'MulerTech\\Database\\Tests\\Entity\\Author',
            targetEntity: 'MulerTech\\Database\\Tests\\Entity\\Book',
            mappedBy: 'MulerTech\\Database\\Tests\\Entity\\AuthorBook',
            joinProperty: 'author_id',
            inverseJoinProperty: 'book_id'
        );
        
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\Author', $relation->entity);
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\Book', $relation->targetEntity);
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\AuthorBook', $relation->mappedBy);
        $this->assertEquals('author_id', $relation->joinProperty);
        $this->assertEquals('book_id', $relation->inverseJoinProperty);
    }

    public function testManyToManyWithComplexEntityNames(): void
    {
        $relation = new MtManyToMany(
            entity: 'Very\\Long\\Namespace\\Path\\To\\SourceEntity',
            targetEntity: 'Another\\Very\\Long\\Namespace\\Path\\To\\TargetEntity',
            mappedBy: 'Pivot\\Namespace\\PivotEntity'
        );
        
        $this->assertEquals('Very\\Long\\Namespace\\Path\\To\\SourceEntity', $relation->entity);
        $this->assertEquals('Another\\Very\\Long\\Namespace\\Path\\To\\TargetEntity', $relation->targetEntity);
        $this->assertEquals('Pivot\\Namespace\\PivotEntity', $relation->mappedBy);
    }
}