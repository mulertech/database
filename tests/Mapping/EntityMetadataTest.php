<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\EntityMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityMetadata::class)]
class EntityMetadataTest extends TestCase
{
    private EntityMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new EntityMetadata();
    }

    public function testDefaultProperties(): void
    {
        $this->assertEquals('', $this->metadata->className);
        $this->assertEquals('', $this->metadata->tableName);
        $this->assertEquals([], $this->metadata->columns);
        $this->assertEquals([], $this->metadata->foreignKeys);
        $this->assertEquals([], $this->metadata->relationships);
    }

    public function testSetAndGetProperties(): void
    {
        $this->metadata->className = 'App\\Entity\\User';
        $this->metadata->tableName = 'users';
        $this->metadata->columns = ['id' => 'id', 'name' => 'user_name'];
        
        $this->assertEquals('App\\Entity\\User', $this->metadata->className);
        $this->assertEquals('users', $this->metadata->tableName);
        $this->assertEquals(['id' => 'id', 'name' => 'user_name'], $this->metadata->columns);
    }

    public function testGetColumnNameReturnsCorrectColumn(): void
    {
        $this->metadata->columns = [
            'id' => 'user_id',
            'name' => 'full_name',
            'email' => 'email_address'
        ];
        
        $this->assertEquals('user_id', $this->metadata->getColumnName('id'));
        $this->assertEquals('full_name', $this->metadata->getColumnName('name'));
        $this->assertEquals('email_address', $this->metadata->getColumnName('email'));
    }

    public function testGetColumnNameReturnsNullForNonExistentProperty(): void
    {
        $this->metadata->columns = ['id' => 'user_id'];
        
        $this->assertNull($this->metadata->getColumnName('nonExistent'));
    }

    public function testGetPropertiesColumnsReturnsAllColumns(): void
    {
        $columns = ['id' => 'user_id', 'name' => 'full_name', 'email' => 'email_address'];
        $this->metadata->columns = $columns;
        
        $this->assertEquals($columns, $this->metadata->getPropertiesColumns());
    }

    public function testHasForeignKeyReturnsTrueWhenForeignKeyExists(): void
    {
        $this->metadata->foreignKeys = [
            'userId' => ['table' => 'users', 'column' => 'id'],
            'categoryId' => ['table' => 'categories', 'column' => 'id']
        ];
        
        $this->assertTrue($this->metadata->hasForeignKey('userId'));
        $this->assertTrue($this->metadata->hasForeignKey('categoryId'));
    }

    public function testHasForeignKeyReturnsFalseWhenForeignKeyDoesNotExist(): void
    {
        $this->metadata->foreignKeys = ['userId' => ['table' => 'users', 'column' => 'id']];
        
        $this->assertFalse($this->metadata->hasForeignKey('nonExistent'));
    }

    public function testGetForeignKeyReturnsCorrectForeignKey(): void
    {
        $foreignKey = ['table' => 'users', 'column' => 'id', 'onDelete' => 'CASCADE'];
        $this->metadata->foreignKeys = ['userId' => $foreignKey];
        
        $this->assertEquals($foreignKey, $this->metadata->getForeignKey('userId'));
    }

    public function testGetForeignKeyReturnsNullForNonExistentProperty(): void
    {
        $this->metadata->foreignKeys = ['userId' => ['table' => 'users', 'column' => 'id']];
        
        $this->assertNull($this->metadata->getForeignKey('nonExistent'));
    }

    public function testGetRelationReturnsCorrectRelation(): void
    {
        $oneToManyRelation = ['targetEntity' => 'App\\Entity\\Post', 'mappedBy' => 'author'];
        $manyToOneRelation = ['targetEntity' => 'App\\Entity\\Category'];
        
        $this->metadata->relationships = [
            'oneToMany' => ['posts' => $oneToManyRelation],
            'manyToOne' => ['category' => $manyToOneRelation]
        ];
        
        $this->assertEquals($oneToManyRelation, $this->metadata->getRelation('oneToMany', 'posts'));
        $this->assertEquals($manyToOneRelation, $this->metadata->getRelation('manyToOne', 'category'));
    }

    public function testGetRelationReturnsNullForNonExistentRelation(): void
    {
        $this->metadata->relationships = [
            'oneToMany' => ['posts' => ['targetEntity' => 'App\\Entity\\Post']]
        ];
        
        $this->assertNull($this->metadata->getRelation('oneToMany', 'nonExistent'));
        $this->assertNull($this->metadata->getRelation('manyToOne', 'posts'));
    }

    public function testGetRelationsByTypeReturnsCorrectRelations(): void
    {
        $oneToManyRelations = [
            'posts' => ['targetEntity' => 'App\\Entity\\Post', 'mappedBy' => 'author'],
            'comments' => ['targetEntity' => 'App\\Entity\\Comment', 'mappedBy' => 'user']
        ];
        
        $manyToOneRelations = [
            'category' => ['targetEntity' => 'App\\Entity\\Category']
        ];
        
        $this->metadata->relationships = [
            'oneToMany' => $oneToManyRelations,
            'manyToOne' => $manyToOneRelations
        ];
        
        $this->assertEquals($oneToManyRelations, $this->metadata->getRelationsByType('oneToMany'));
        $this->assertEquals($manyToOneRelations, $this->metadata->getRelationsByType('manyToOne'));
    }

    public function testGetRelationsByTypeReturnsEmptyArrayForNonExistentType(): void
    {
        $this->metadata->relationships = [
            'oneToMany' => ['posts' => ['targetEntity' => 'App\\Entity\\Post']]
        ];
        
        $this->assertEquals([], $this->metadata->getRelationsByType('manyToMany'));
    }

    public function testComplexMetadataStructure(): void
    {
        $this->metadata->className = 'App\\Entity\\User';
        $this->metadata->tableName = 'users';
        $this->metadata->columns = [
            'id' => 'user_id',
            'name' => 'full_name',
            'email' => 'email_address',
            'categoryId' => 'category_id'
        ];
        $this->metadata->foreignKeys = [
            'categoryId' => [
                'table' => 'categories',
                'column' => 'id',
                'onDelete' => 'SET NULL',
                'onUpdate' => 'CASCADE'
            ]
        ];
        $this->metadata->relationships = [
            'oneToMany' => [
                'posts' => [
                    'targetEntity' => 'App\\Entity\\Post',
                    'mappedBy' => 'author',
                    'cascade' => ['persist', 'remove']
                ]
            ],
            'manyToOne' => [
                'category' => [
                    'targetEntity' => 'App\\Entity\\Category',
                    'inversedBy' => 'users'
                ]
            ],
            'manyToMany' => [
                'roles' => [
                    'targetEntity' => 'App\\Entity\\Role',
                    'joinTable' => 'user_roles'
                ]
            ]
        ];
        
        // Test all aspects
        $this->assertEquals('App\\Entity\\User', $this->metadata->className);
        $this->assertEquals('users', $this->metadata->tableName);
        $this->assertEquals('category_id', $this->metadata->getColumnName('categoryId'));
        $this->assertTrue($this->metadata->hasForeignKey('categoryId'));
        $this->assertNotNull($this->metadata->getForeignKey('categoryId'));
        $this->assertNotNull($this->metadata->getRelation('oneToMany', 'posts'));
        $this->assertNotNull($this->metadata->getRelation('manyToOne', 'category'));
        $this->assertNotNull($this->metadata->getRelation('manyToMany', 'roles'));
        $this->assertCount(1, $this->metadata->getRelationsByType('oneToMany'));
        $this->assertCount(1, $this->metadata->getRelationsByType('manyToOne'));
        $this->assertCount(1, $this->metadata->getRelationsByType('manyToMany'));
    }
}