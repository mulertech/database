<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping\Attributes;

use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(MtOneToMany::class)]
class MtOneToManyTest extends TestCase
{
    public function testConstructorWithDefaultValues(): void
    {
        $relation = new MtOneToMany();
        
        $this->assertNull($relation->entity);
        $this->assertNull($relation->targetEntity);
        $this->assertNull($relation->inverseJoinProperty);
    }

    public function testConstructorWithAllParameters(): void
    {
        $relation = new MtOneToMany(
            entity: 'App\\Entity\\User',
            targetEntity: 'App\\Entity\\Post',
            inverseJoinProperty: 'user_id'
        );
        
        $this->assertEquals('App\\Entity\\User', $relation->entity);
        $this->assertEquals('App\\Entity\\Post', $relation->targetEntity);
        $this->assertEquals('user_id', $relation->inverseJoinProperty);
    }

    public function testAttributeTargetsProperty(): void
    {
        $reflection = new ReflectionClass(MtOneToMany::class);
        $attributes = $reflection->getAttributes();
        
        $this->assertCount(1, $attributes);
        $this->assertEquals(\Attribute::class, $attributes[0]->getName());
        
        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function testConstructorWithPartialParameters(): void
    {
        $relation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Comment',
            inverseJoinProperty: 'post_id'
        );
        
        $this->assertNull($relation->entity);
        $this->assertEquals('App\\Entity\\Comment', $relation->targetEntity);
        $this->assertEquals('post_id', $relation->inverseJoinProperty);
    }

    public function testOneToManyWithTargetEntityOnly(): void
    {
        $relation = new MtOneToMany(targetEntity: 'App\\Entity\\Order');
        
        $this->assertNull($relation->entity);
        $this->assertEquals('App\\Entity\\Order', $relation->targetEntity);
        $this->assertNull($relation->inverseJoinProperty);
    }

    public function testOneToManyWithEntityAndTargetEntity(): void
    {
        $relation = new MtOneToMany(
            entity: 'App\\Entity\\Category',
            targetEntity: 'App\\Entity\\Product'
        );
        
        $this->assertEquals('App\\Entity\\Category', $relation->entity);
        $this->assertEquals('App\\Entity\\Product', $relation->targetEntity);
        $this->assertNull($relation->inverseJoinProperty);
    }

    public function testOneToManyWithInverseJoinPropertyOnly(): void
    {
        $relation = new MtOneToMany(inverseJoinProperty: 'author_id');
        
        $this->assertNull($relation->entity);
        $this->assertNull($relation->targetEntity);
        $this->assertEquals('author_id', $relation->inverseJoinProperty);
    }

    public function testOneToManyRelationshipProperties(): void
    {
        $relation = new MtOneToMany(
            entity: 'MulerTech\\Database\\Tests\\Entity\\Department',
            targetEntity: 'MulerTech\\Database\\Tests\\Entity\\Employee',
            inverseJoinProperty: 'department_id'
        );
        
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\Department', $relation->entity);
        $this->assertEquals('MulerTech\\Database\\Tests\\Entity\\Employee', $relation->targetEntity);
        $this->assertEquals('department_id', $relation->inverseJoinProperty);
    }

    public function testOneToManyWithComplexEntityNames(): void
    {
        $relation = new MtOneToMany(
            entity: 'Very\\Long\\Namespace\\Path\\To\\ParentEntity',
            targetEntity: 'Another\\Very\\Long\\Namespace\\Path\\To\\ChildEntity',
            inverseJoinProperty: 'parent_entity_id'
        );
        
        $this->assertEquals('Very\\Long\\Namespace\\Path\\To\\ParentEntity', $relation->entity);
        $this->assertEquals('Another\\Very\\Long\\Namespace\\Path\\To\\ChildEntity', $relation->targetEntity);
        $this->assertEquals('parent_entity_id', $relation->inverseJoinProperty);
    }

    public function testMultipleOneToManyRelations(): void
    {
        $relations = [
            new MtOneToMany(
                entity: 'App\\Entity\\Blog',
                targetEntity: 'App\\Entity\\Post',
                inverseJoinProperty: 'blog_id'
            ),
            new MtOneToMany(
                entity: 'App\\Entity\\Post',
                targetEntity: 'App\\Entity\\Comment',
                inverseJoinProperty: 'post_id'
            ),
            new MtOneToMany(
                entity: 'App\\Entity\\User',
                targetEntity: 'App\\Entity\\Article',
                inverseJoinProperty: 'author_id'
            ),
        ];
        
        $this->assertEquals('App\\Entity\\Blog', $relations[0]->entity);
        $this->assertEquals('App\\Entity\\Post', $relations[0]->targetEntity);
        $this->assertEquals('blog_id', $relations[0]->inverseJoinProperty);
        
        $this->assertEquals('App\\Entity\\Post', $relations[1]->entity);
        $this->assertEquals('App\\Entity\\Comment', $relations[1]->targetEntity);
        $this->assertEquals('post_id', $relations[1]->inverseJoinProperty);
        
        $this->assertEquals('App\\Entity\\User', $relations[2]->entity);
        $this->assertEquals('App\\Entity\\Article', $relations[2]->targetEntity);
        $this->assertEquals('author_id', $relations[2]->inverseJoinProperty);
    }
}