<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\RelationMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use MulerTech\Database\Tests\Files\Mapping\EntityWithRelations;
use MulerTech\Database\Tests\Files\Mapping\EntityWithoutRelations;

#[CoversClass(RelationMapping::class)]
class RelationMappingTest extends TestCase
{
    private RelationMapping $relationMapping;

    protected function setUp(): void
    {
        $this->relationMapping = new RelationMapping();
    }

    public function testGetOneToOneReturnsCorrectRelations(): void
    {
        $relations = $this->relationMapping->getOneToOne(EntityWithRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertArrayHasKey('profile', $relations);
        $this->assertInstanceOf(MtOneToOne::class, $relations['profile']);
        $this->assertEquals('App\\Entity\\UserProfile', $relations['profile']->targetEntity);
    }

    public function testGetOneToOneReturnsEmptyArrayForEntityWithoutRelations(): void
    {
        $relations = $this->relationMapping->getOneToOne(EntityWithoutRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertEmpty($relations);
    }

    public function testGetManyToOneReturnsCorrectRelations(): void
    {
        $relations = $this->relationMapping->getManyToOne(EntityWithRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertArrayHasKey('category', $relations);
        $this->assertArrayHasKey('author', $relations);
        $this->assertInstanceOf(MtManyToOne::class, $relations['category']);
        $this->assertInstanceOf(MtManyToOne::class, $relations['author']);
        $this->assertEquals('App\\Entity\\Category', $relations['category']->targetEntity);
        $this->assertEquals('App\\Entity\\User', $relations['author']->targetEntity);
    }

    public function testGetManyToOneReturnsEmptyArrayForEntityWithoutRelations(): void
    {
        $relations = $this->relationMapping->getManyToOne(EntityWithoutRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertEmpty($relations);
    }

    public function testGetOneToManyReturnsCorrectRelations(): void
    {
        $relations = $this->relationMapping->getOneToMany(EntityWithRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertArrayHasKey('posts', $relations);
        $this->assertArrayHasKey('comments', $relations);
        $this->assertInstanceOf(MtOneToMany::class, $relations['posts']);
        $this->assertInstanceOf(MtOneToMany::class, $relations['comments']);
        $this->assertEquals('App\\Entity\\Post', $relations['posts']->targetEntity);
        $this->assertEquals('App\\Entity\\Comment', $relations['comments']->targetEntity);
    }

    public function testGetOneToManyReturnsEmptyArrayForEntityWithoutRelations(): void
    {
        $relations = $this->relationMapping->getOneToMany(EntityWithoutRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertEmpty($relations);
    }

    public function testGetManyToManyReturnsCorrectRelations(): void
    {
        $relations = $this->relationMapping->getManyToMany(EntityWithRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertArrayHasKey('roles', $relations);
        $this->assertArrayHasKey('tags', $relations);
        $this->assertInstanceOf(MtManyToMany::class, $relations['roles']);
        $this->assertInstanceOf(MtManyToMany::class, $relations['tags']);
        $this->assertEquals('App\\Entity\\Role', $relations['roles']->targetEntity);
        $this->assertEquals('App\\Entity\\Tag', $relations['tags']->targetEntity);
    }

    public function testGetManyToManyReturnsEmptyArrayForEntityWithoutRelations(): void
    {
        $relations = $this->relationMapping->getManyToMany(EntityWithoutRelations::class);
        
        $this->assertIsArray($relations);
        $this->assertEmpty($relations);
    }

    public function testGetRelationColumnNameReturnsConventionBasedName(): void
    {
        $columns = ['id' => 'id', 'name' => 'name', 'email' => 'email'];
        
        $columnName = $this->relationMapping->getRelationColumnName('user', $columns);
        
        $this->assertEquals('user_id', $columnName);
    }

    public function testGetRelationColumnNameReturnsExistingColumnName(): void
    {
        $columns = ['id' => 'id', 'name' => 'name', 'user_id' => 'user_id'];
        
        $columnName = $this->relationMapping->getRelationColumnName('user', $columns);
        
        $this->assertEquals('user_id', $columnName);
    }

    public function testGetRelationColumnNameWithDifferentProperties(): void
    {
        $columns = ['id' => 'id', 'title' => 'title'];
        
        $authorColumn = $this->relationMapping->getRelationColumnName('author', $columns);
        $categoryColumn = $this->relationMapping->getRelationColumnName('category', $columns);
        $tagColumn = $this->relationMapping->getRelationColumnName('tag', $columns);
        
        $this->assertEquals('author_id', $authorColumn);
        $this->assertEquals('category_id', $categoryColumn);
        $this->assertEquals('tag_id', $tagColumn);
    }

    public function testAllRelationTypesOnSingleEntity(): void
    {
        $oneToOne = $this->relationMapping->getOneToOne(EntityWithRelations::class);
        $manyToOne = $this->relationMapping->getManyToOne(EntityWithRelations::class);
        $oneToMany = $this->relationMapping->getOneToMany(EntityWithRelations::class);
        $manyToMany = $this->relationMapping->getManyToMany(EntityWithRelations::class);
        
        $this->assertCount(1, $oneToOne);
        $this->assertCount(2, $manyToOne);
        $this->assertCount(2, $oneToMany);
        $this->assertCount(2, $manyToMany);
    }

    public function testReflectionExceptionHandling(): void
    {
        $oneToOne = $this->relationMapping->getOneToOne('NonExistentClass');
        $manyToOne = $this->relationMapping->getManyToOne('NonExistentClass');
        $oneToMany = $this->relationMapping->getOneToMany('NonExistentClass');
        $manyToMany = $this->relationMapping->getManyToMany('NonExistentClass');
        
        $this->assertEquals([], $oneToOne);
        $this->assertEquals([], $manyToOne);
        $this->assertEquals([], $oneToMany);
        $this->assertEquals([], $manyToMany);
    }
}
