<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use DateTime;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\FkRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
        $this->assertEquals([], $this->metadata->oneToManyRelations);
        $this->assertEquals([], $this->metadata->manyToOneRelations);
        $this->assertEquals([], $this->metadata->oneToOneRelations);
        $this->assertEquals([], $this->metadata->manyToManyRelations);
        $this->assertNull($this->metadata->entity);
    }

    public function testSetAndGetProperties(): void
    {
        $idColumn = new MtColumn(columnName: 'id', columnType: ColumnType::INT);
        $nameColumn = new MtColumn(columnName: 'user_name', columnType: ColumnType::VARCHAR);
        
        $metadata = new EntityMetadata(
            className: 'App\\Entity\\User',
            tableName: 'users',
            columns:   ['id' => $idColumn, 'name' => $nameColumn]
        );

        $this->assertEquals('App\\Entity\\User', $metadata->className);
        $this->assertEquals('users', $metadata->tableName);
        $this->assertEquals(['id' => 'id', 'name' => 'user_name'], $metadata->getPropertiesColumns());
    }

    public function testGetColumnNameReturnsCorrectColumn(): void
    {
        $idColumn = new MtColumn(columnName: 'user_id', columnType: ColumnType::INT);
        $nameColumn = new MtColumn(columnName: 'full_name', columnType: ColumnType::VARCHAR);
        $emailColumn = new MtColumn(columnName: 'email_address', columnType: ColumnType::VARCHAR);
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns:   [
                           'id' => $idColumn,
                           'name' => $nameColumn,
                           'email' => $emailColumn
                       ]
        );

        $this->assertEquals('user_id', $metadata->getColumnName('id'));
        $this->assertEquals('full_name', $metadata->getColumnName('name'));
        $this->assertEquals('email_address', $metadata->getColumnName('email'));
    }

    public function testGetColumnNameReturnsNullForNonExistentColumn(): void
    {
        $idColumn = new MtColumn(columnName: 'user_id', columnType: ColumnType::INT);
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns:   ['id' => $idColumn]
        );

        // Should return null when column doesn't exist (no mapping defined)
        $this->assertNull($metadata->getColumnName('nonExistent'));
    }

    public function testGetPropertiesColumnsReturnsAllColumns(): void
    {
        $idColumn = new MtColumn(columnName: 'user_id', columnType: ColumnType::INT);
        $nameColumn = new MtColumn(columnName: 'full_name', columnType: ColumnType::VARCHAR);
        $emailColumn = new MtColumn(columnName: 'email_address', columnType: ColumnType::VARCHAR);
        
        $columns = ['id' => $idColumn, 'name' => $nameColumn, 'email' => $emailColumn];
        $expected = ['id' => 'user_id', 'name' => 'full_name', 'email' => 'email_address'];
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns:   $columns
        );

        $this->assertEquals($expected, $metadata->getPropertiesColumns());
    }

    public function testHasForeignKeyReturnsTrueWhenForeignKeyExists(): void
    {
        $userFk = new MtFk(
            referencedTable: 'users',
            referencedColumn: 'id'
        );
        $categoryFk = new MtFk(
            referencedTable: 'categories',
            referencedColumn: 'id'
        );
        
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: [
                             'userId' => $userFk,
                             'categoryId' => $categoryFk
                         ]
        );

        $this->assertTrue($metadata->hasForeignKey('userId'));
        $this->assertTrue($metadata->hasForeignKey('categoryId'));
    }

    public function testHasForeignKeyReturnsFalseWhenForeignKeyDoesNotExist(): void
    {
        $userFk = new MtFk(
            referencedTable: 'users',
            referencedColumn: 'id'
        );
        
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: ['userId' => $userFk]
        );

        $this->assertFalse($metadata->hasForeignKey('nonExistent'));
    }

    public function testGetForeignKeyReturnsNullForNonExistentProperty(): void
    {
        $userFk = new MtFk(
            referencedTable: 'users',
            referencedColumn: 'id'
        );
        
        $metadata = new EntityMetadata(
            className:   'TestEntity',
            tableName:   'test_entities',
            foreignKeys: ['userId' => $userFk]
        );

        $this->assertNull($metadata->getForeignKey('nonExistent'));
    }

    public function testGetRelationsReturnsCorrectRelations(): void
    {
        $oneToManyRelation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Post',
            inverseJoinProperty: 'author'
        );
        $manyToOneRelation = new MtManyToOne(
            targetEntity: 'App\\Entity\\Category'
        );
        $oneToOneRelation = new MtOneToOne(
            targetEntity: 'App\\Entity\\Profile'
        );
        $manyToManyRelation = new MtManyToMany(
            targetEntity: 'App\\Entity\\Role',
            joinProperty: 'userId',
            inverseJoinProperty: 'roleId'
        );
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            oneToManyRelations: ['posts' => $oneToManyRelation],
            manyToOneRelations: ['category' => $manyToOneRelation],
            oneToOneRelations: ['profile' => $oneToOneRelation],
            manyToManyRelations: ['roles' => $manyToManyRelation]
        );

        $this->assertEquals($oneToManyRelation, $metadata->getOneToManyRelation('posts'));
        $this->assertEquals($manyToOneRelation, $metadata->getManyToOneRelation('category'));
        $this->assertEquals($oneToOneRelation, $metadata->getOneToOneRelation('profile'));
        $this->assertEquals($manyToManyRelation, $metadata->getManyToManyRelation('roles'));
        
        $this->assertTrue($metadata->hasRelation('posts'));
        $this->assertTrue($metadata->hasRelation('category'));
        $this->assertTrue($metadata->hasRelation('profile'));
        $this->assertTrue($metadata->hasRelation('roles'));
        $this->assertFalse($metadata->hasRelation('nonExistent'));
    }

    public function testGetRelationReturnsNullForNonExistentRelation(): void
    {
        $oneToManyRelation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Post'
        );
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            oneToManyRelations: ['posts' => $oneToManyRelation]
        );

        $this->assertNull($metadata->getOneToManyRelation('nonExistent'));
        $this->assertNull($metadata->getManyToOneRelation('posts'));
        $this->assertNull($metadata->getOneToOneRelation('posts'));
        $this->assertNull($metadata->getManyToManyRelation('posts'));
    }

    public function testGetRelationsByTypeReturnsCorrectRelations(): void
    {
        $postsRelation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Post',
            inverseJoinProperty: 'author'
        );
        $commentsRelation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Comment',
            inverseJoinProperty: 'user'
        );
        $categoryRelation = new MtManyToOne(
            targetEntity: 'App\\Entity\\Category'
        );
        
        $oneToManyRelations = [
            'posts' => $postsRelation,
            'comments' => $commentsRelation
        ];
        $manyToOneRelations = [
            'category' => $categoryRelation
        ];
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            oneToManyRelations: $oneToManyRelations,
            manyToOneRelations: $manyToOneRelations
        );

        $this->assertEquals($oneToManyRelations, $metadata->getOneToManyRelations());
        $this->assertEquals($manyToOneRelations, $metadata->getManyToOneRelations());
        $this->assertEquals([], $metadata->getOneToOneRelations());
        $this->assertEquals([], $metadata->getManyToManyRelations());
    }

    public function testGetRelationsByTypeReturnsEmptyArrayForNonExistentType(): void
    {
        $postsRelation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Post'
        );
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            oneToManyRelations: ['posts' => $postsRelation]
        );

        $this->assertEquals([], $metadata->getManyToManyRelations());
        $this->assertEquals([], $metadata->getManyToOneRelations());
        $this->assertEquals([], $metadata->getOneToOneRelations());
        $this->assertCount(1, $metadata->getOneToManyRelations());
    }

    public function testComplexMetadataStructure(): void
    {
        // Create columns
        $idColumn = new MtColumn(columnName: 'user_id', columnType: ColumnType::INT);
        $nameColumn = new MtColumn(columnName: 'full_name', columnType: ColumnType::VARCHAR);
        $emailColumn = new MtColumn(columnName: 'email_address', columnType: ColumnType::VARCHAR);
        $categoryIdColumn = new MtColumn(columnName: 'category_id', columnType: ColumnType::INT);
        
        // Create foreign keys
        $categoryFk = new MtFk(
            referencedTable: 'categories',
            referencedColumn: 'id',
            deleteRule: FkRule::SET_NULL,
            updateRule: FkRule::CASCADE
        );
        
        // Create relations
        $postsRelation = new MtOneToMany(
            targetEntity: 'App\\Entity\\Post',
            inverseJoinProperty: 'author'
        );
        $categoryRelation = new MtManyToOne(
            targetEntity: 'App\\Entity\\Category'
        );
        $rolesRelation = new MtManyToMany(
            targetEntity: 'App\\Entity\\Role',
            joinProperty: 'userId',
            inverseJoinProperty: 'roleId'
        );
        
        // Create entity attribute
        $entityAttr = new MtEntity(
            repository: 'App\\Repository\\UserRepository',
            tableName: 'users'
        );
        
        $metadata = new EntityMetadata(
            className: 'App\\Entity\\User',
            tableName: 'users',
            entity: $entityAttr,
            columns: [
                'id' => $idColumn,
                'name' => $nameColumn,
                'email' => $emailColumn,
                'categoryId' => $categoryIdColumn
            ],
            foreignKeys: [
                'categoryId' => $categoryFk
            ],
            oneToManyRelations: [
                'posts' => $postsRelation
            ],
            manyToOneRelations: [
                'category' => $categoryRelation
            ],
            manyToManyRelations: [
                'roles' => $rolesRelation
            ]
        );

        // Test all aspects
        $this->assertEquals('App\\Entity\\User', $metadata->className);
        $this->assertEquals('users', $metadata->tableName);
        $this->assertEquals('category_id', $metadata->getColumnName('categoryId'));
        $this->assertTrue($metadata->hasForeignKey('categoryId'));
        $this->assertNotNull($metadata->getForeignKey('categoryId'));
        $this->assertNotNull($metadata->getOneToManyRelation('posts'));
        $this->assertNotNull($metadata->getManyToOneRelation('category'));
        $this->assertNotNull($metadata->getManyToManyRelation('roles'));
        $this->assertCount(1, $metadata->getOneToManyRelations());
        $this->assertCount(1, $metadata->getManyToOneRelations());
        $this->assertCount(1, $metadata->getManyToManyRelations());
        $this->assertEquals('App\\Repository\\UserRepository', $metadata->getRepository());
        $this->assertNotNull($metadata->getEntity());
    }

    public function testGetRepositoryReturnsRepositoryClass(): void
    {
        // Test with repository in constructor parameter
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            repository: 'App\\Repository\\TestEntityRepository'
        );
        $this->assertEquals('App\\Repository\\TestEntityRepository', $metadata->getRepository());
        
        // Test with repository in MtEntity (should take precedence)
        $entityWithRepo = new MtEntity(
            repository: 'App\\Repository\\EntityRepository'
        );
        $metadata2 = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            entity: $entityWithRepo,
            repository: 'App\\Repository\\TestEntityRepository'
        );
        $this->assertEquals('App\\Repository\\EntityRepository', $metadata2->getRepository());
    }

    public function testGetRepositoryReturnsNullWhenNotSet(): void
    {
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities'
        );

        $this->assertNull($metadata->getRepository());
    }

    public function testGetColumnAndColumnsMethods(): void
    {
        $idColumn = new MtColumn(columnName: 'id', columnType: ColumnType::INT);
        $nameColumn = new MtColumn(columnName: 'name', columnType: ColumnType::VARCHAR);
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            columns: ['id' => $idColumn, 'name' => $nameColumn]
        );

        $this->assertEquals($idColumn, $metadata->getColumn('id'));
        $this->assertEquals($nameColumn, $metadata->getColumn('name'));
        $this->assertNull($metadata->getColumn('nonExistent'));
        
        $expectedColumns = ['id' => $idColumn, 'name' => $nameColumn];
        $this->assertEquals($expectedColumns, $metadata->getColumns());
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
        
        // Test with MtColumn attributes (should take priority)
        $idColumn = new MtColumn(columnType: ColumnType::BIGINT); // Override from INT to BIGINT
        $nameColumn = new MtColumn(columnType: ColumnType::TEXT); // Override from VARCHAR to TEXT
        
        $metadata = new EntityMetadata(
            className: 'TestEntity',
            tableName: 'test_entities',
            properties: $properties,
            columns: [
                'id' => $idColumn,
                'name' => $nameColumn
            ]
        );

        // Should use MtColumn types when available
        $this->assertEquals(ColumnType::BIGINT, $metadata->getColumnType('id'));
        $this->assertEquals(ColumnType::TEXT, $metadata->getColumnType('name'));
        
        // Should fallback to PHP type inference
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