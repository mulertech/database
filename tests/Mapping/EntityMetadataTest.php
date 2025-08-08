<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use DateTime;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\Types\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(EntityMetadata::class)]
class EntityMetadataTest extends TestCase
{
    private EntityMetadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities'
        );
    }

    public function testDefaultProperties(): void
    {
        $this->assertEquals('TestEntity', $this->metadata->className);
        $this->assertEquals('test_entities', $this->metadata->tableName);
        $this->assertEquals([], $this->metadata->columns);
        $this->assertEquals([], $this->metadata->foreignKeys);
        $this->assertEquals([], $this->metadata->relationships);
    }

    public function testSetAndGetProperties(): void
    {
        $metadata = new EntityMetadata(
            className: 'App\\Entity\\User',
            tableName: 'users',
            columns:   ['id' => 'id', 'name' => 'user_name']
        );

        $this->assertEquals('App\\Entity\\User', $metadata->className);
        $this->assertEquals('users', $metadata->tableName);
        $this->assertEquals(['id' => 'id', 'name' => 'user_name'], $metadata->columns);
    }

    public function testGetColumnNameReturnsCorrectColumn(): void
    {
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns:   [
                           'id' => 'user_id',
                           'name' => 'full_name',
                           'email' => 'email_address'
                       ]
        );

        $this->assertEquals('user_id', $metadata->getColumnName('id'));
        $this->assertEquals('full_name', $metadata->getColumnName('name'));
        $this->assertEquals('email_address', $metadata->getColumnName('email'));
    }

    public function testGetColumnNameReturnsNullForNonExistentProperty(): void
    {
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns:   ['id' => 'user_id']
        );

        $this->assertNull($metadata->getColumnName('nonExistent'));
    }

    public function testGetPropertiesColumnsReturnsAllColumns(): void
    {
        $columns = ['id' => 'user_id', 'name' => 'full_name', 'email' => 'email_address'];
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns:   $columns
        );

        $this->assertEquals($columns, $metadata->getPropertiesColumns());
    }

    public function testHasForeignKeyReturnsTrueWhenForeignKeyExists(): void
    {
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: [
                             'userId' => ['table' => 'users', 'column' => 'id'],
                             'categoryId' => ['table' => 'categories', 'column' => 'id']
                         ]
        );

        $this->assertTrue($metadata->hasForeignKey('userId'));
        $this->assertTrue($metadata->hasForeignKey('categoryId'));
    }

    public function testHasForeignKeyReturnsFalseWhenForeignKeyDoesNotExist(): void
    {
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: ['userId' => ['table' => 'users', 'column' => 'id']]
        );

        $this->assertFalse($metadata->hasForeignKey('nonExistent'));
    }

    public function testGetForeignKeyReturnsCorrectForeignKey(): void
    {
        $foreignKey = ['table' => 'users', 'column' => 'id', 'onDelete' => 'CASCADE'];
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: ['userId' => $foreignKey]
        );

        $this->assertEquals($foreignKey, $metadata->getForeignKey('userId'));
    }

    public function testGetForeignKeyReturnsNullForNonExistentProperty(): void
    {
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: ['userId' => ['table' => 'users', 'column' => 'id']]
        );

        $this->assertNull($metadata->getForeignKey('nonExistent'));
    }

    public function testGetRelationReturnsCorrectRelation(): void
    {
        $oneToManyRelation = ['targetEntity' => 'App\\Entity\\Post', 'mappedBy' => 'author'];
        $manyToOneRelation = ['targetEntity' => 'App\\Entity\\Category'];
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            relationships: [
                             'oneToMany' => ['posts' => $oneToManyRelation],
                             'manyToOne' => ['category' => $manyToOneRelation]
                         ]
        );

        $this->assertEquals($oneToManyRelation, $metadata->getRelation('oneToMany', 'posts'));
        $this->assertEquals($manyToOneRelation, $metadata->getRelation('manyToOne', 'category'));
    }

    public function testGetRelationReturnsNullForNonExistentRelation(): void
    {
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            relationships: ['oneToMany' => ['posts' => ['targetEntity' => 'App\\Entity\\Post']]]
        );

        $this->assertNull($metadata->getRelation('oneToMany', 'nonExistent'));
        $this->assertNull($metadata->getRelation('manyToOne', 'posts'));
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
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            relationships: ['oneToMany' => $oneToManyRelations, 'manyToOne' => $manyToOneRelations]
        );

        $this->assertEquals($oneToManyRelations, $metadata->getRelationsByType('oneToMany'));
        $this->assertEquals($manyToOneRelations, $metadata->getRelationsByType('manyToOne'));
    }

    public function testGetRelationsByTypeReturnsEmptyArrayForNonExistentType(): void
    {
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            relationships: ['oneToMany' => ['posts' => ['targetEntity' => 'App\\Entity\\Post']]]
        );

        $this->assertEquals([], $metadata->getRelationsByType('manyToMany'));
    }

    public function testComplexMetadataStructure(): void
    {
        $metadata = new EntityMetadata(
            className:   'App\\Entity\\User',
            tableName:   'users',
            columns:     [
                             'id' => 'user_id',
                             'name' => 'full_name',
                             'email' => 'email_address',
                             'categoryId' => 'category_id'
                         ],
            foreignKeys: [
                             'categoryId' => [
                                 'table' => 'categories',
                                 'column' => 'id',
                                 'onDelete' => 'SET NULL',
                                 'onUpdate' => 'CASCADE'
                             ]
                         ],
            relationships: [
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
                         ]
        );

        // Test all aspects
        $this->assertEquals('App\\Entity\\User', $metadata->className);
        $this->assertEquals('users', $metadata->tableName);
        $this->assertEquals('category_id', $metadata->getColumnName('categoryId'));
        $this->assertTrue($metadata->hasForeignKey('categoryId'));
        $this->assertNotNull($metadata->getForeignKey('categoryId'));
        $this->assertNotNull($metadata->getRelation('oneToMany', 'posts'));
        $this->assertNotNull($metadata->getRelation('manyToOne', 'category'));
        $this->assertNotNull($metadata->getRelation('manyToMany', 'roles'));
        $this->assertCount(1, $metadata->getRelationsByType('oneToMany'));
        $this->assertCount(1, $metadata->getRelationsByType('manyToOne'));
        $this->assertCount(1, $metadata->getRelationsByType('manyToMany'));
    }

    public function testGetRepositoryReturnsRepositoryClass(): void
    {
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            repository: 'App\\Repository\\TestEntityRepository'
        );

        $this->assertEquals('App\\Repository\\TestEntityRepository', $metadata->getRepository());
    }

    public function testGetRepositoryReturnsNullWhenNotSet(): void
    {
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities'
        );

        $this->assertNull($metadata->getRepository());
    }

    public function testGetPropertiesReturnsAllProperties(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $properties = $reflection->getProperties();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties
        );

        $this->assertEquals($properties, $metadata->getProperties());
        $this->assertCount(7, $metadata->getProperties());
    }

    public function testGetPropertyReturnsCorrectProperty(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $properties = $reflection->getProperties();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties
        );

        $idProperty = $metadata->getProperty('id');
        $this->assertInstanceOf(ReflectionProperty::class, $idProperty);
        $this->assertEquals('id', $idProperty->getName());

        $nameProperty = $metadata->getProperty('name');
        $this->assertInstanceOf(ReflectionProperty::class, $nameProperty);
        $this->assertEquals('name', $nameProperty->getName());
    }

    public function testGetPropertyReturnsNullForNonExistentProperty(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $properties = $reflection->getProperties();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties
        );

        $this->assertNull($metadata->getProperty('nonExistent'));
    }

    public function testGetGetterReturnsCorrectGetter(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $getters = $reflection->getMethods();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            getters: $getters
        );

        $this->assertEquals('getId', $metadata->getGetter('id'));
        $this->assertEquals('getName', $metadata->getGetter('name'));
        $this->assertEquals('isActive', $metadata->getGetter('active'));
        $this->assertEquals('hasPermission', $metadata->getGetter('permission'));
    }

    public function testGetGetterReturnsNullForNonExistentGetter(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $getters = $reflection->getMethods();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            getters: $getters
        );

        $this->assertNull($metadata->getGetter('nonExistent'));
    }

    public function testGetSetterReturnsCorrectSetter(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $setters = $reflection->getMethods();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            setters: $setters
        );

        $this->assertEquals('setId', $metadata->getSetter('id'));
        $this->assertEquals('setName', $metadata->getSetter('name'));
        $this->assertEquals('setActive', $metadata->getSetter('active'));
    }

    public function testGetSetterReturnsNullForNonExistentSetter(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $setters = $reflection->getMethods();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            setters: $setters
        );

        $this->assertNull($metadata->getSetter('nonExistent'));
    }

    public function testGetColumnTypeReturnsCorrectTypes(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $properties = $reflection->getProperties();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties
        );

        $this->assertEquals(ColumnType::INT, $metadata->getColumnType('id'));
        $this->assertEquals(ColumnType::VARCHAR, $metadata->getColumnType('name'));
        $this->assertEquals(ColumnType::TINYINT, $metadata->getColumnType('active'));
        $this->assertEquals(ColumnType::DATETIME, $metadata->getColumnType('createdAt'));
        $this->assertEquals(ColumnType::JSON, $metadata->getColumnType('metadata'));
        $this->assertEquals(ColumnType::FLOAT, $metadata->getColumnType('score'));
    }

    public function testGetColumnTypeReturnsNullForNonExistentProperty(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $properties = $reflection->getProperties();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties
        );

        $this->assertNull($metadata->getColumnType('nonExistent'));
    }

    public function testGetColumnTypeReturnsNullForUnsupportedType(): void
    {
        $reflection = new ReflectionClass(TestEntityForReflection::class);
        $properties = $reflection->getProperties();
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties
        );

        // Test with a property that has no type or unsupported type
        $this->assertNull($metadata->getColumnType('untypedProperty'));
    }
}

class TestEntityForReflection
{
    public int $id;
    public string $name;
    public bool $active;
    public DateTime $createdAt;
    public array $metadata;
    public float $score;
    public $untypedProperty;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function hasPermission(): bool
    {
        return true;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}