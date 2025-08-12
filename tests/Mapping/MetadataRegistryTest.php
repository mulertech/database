<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Mapping;

use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use ReflectionException;

final class MetadataRegistryTest extends TestCase
{
    private MetadataRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new MetadataRegistry();
    }

    public function testConstructorWithDefaultConfig(): void
    {
        $registry = new MetadataRegistry();
        
        $this->assertEquals(0, $registry->count());
        $this->assertEmpty($registry->getRegisteredClasses());
    }

    public function testConstructorWithEntitiesPath(): void
    {
        $entitiesPath = __DIR__ . '/../Files/Entity';
        $registry = new MetadataRegistry($entitiesPath);
        
        // Verify that entities were automatically loaded by accessing metadata
        // The loadEntitiesFromPath method loads classes but doesn't pre-build metadata
        $metadata = $registry->getEntityMetadata(User::class);
        $this->assertInstanceOf(EntityMetadata::class, $metadata);
        
        // After accessing metadata, it should be registered
        $this->assertTrue($registry->hasMetadata(User::class));
        $this->assertGreaterThan(0, $registry->count());
    }

    public function testGetEntityMetadata(): void
    {
        $entityClass = User::class;
        
        $metadata = $this->registry->getEntityMetadata($entityClass);
        
        $this->assertInstanceOf(EntityMetadata::class, $metadata);
        $this->assertEquals($entityClass, $metadata->className);
        $this->assertEquals('users_test', $metadata->tableName);
    }

    public function testGetEntityMetadataThrowsExceptionForInvalidClass(): void
    {
        $this->expectException(ReflectionException::class);
        
        $this->registry->getEntityMetadata('NonExistentClass');
    }

    public function testGetEntityMetadataCaching(): void
    {
        $entityClass = User::class;
        
        // First call - will build metadata
        $metadata1 = $this->registry->getEntityMetadata($entityClass);
        
        // Second call - should return cached metadata
        $metadata2 = $this->registry->getEntityMetadata($entityClass);
        
        $this->assertSame($metadata1, $metadata2);
        $this->assertTrue($this->registry->hasMetadata($entityClass));
    }

    public function testHasMetadata(): void
    {
        $entityClass = User::class;
        
        $this->assertFalse($this->registry->hasMetadata($entityClass));
        
        // Load metadata
        $this->registry->getEntityMetadata($entityClass);
        
        $this->assertTrue($this->registry->hasMetadata($entityClass));
    }

    public function testRegisterMetadata(): void
    {
        $entityClass = 'TestEntity';
        $metadata = new EntityMetadata(
            className: $entityClass,
            tableName: 'test_table',
            columns: ['id' => 'id', 'name' => 'name']
        );
        
        $this->registry->registerMetadata($entityClass, $metadata);
        
        $this->assertTrue($this->registry->hasMetadata($entityClass));
        $this->assertSame($metadata, $this->registry->getEntityMetadata($entityClass));
    }

    public function testGetRegisteredClasses(): void
    {
        $this->assertEmpty($this->registry->getRegisteredClasses());
        
        // Load an entity
        $this->registry->getEntityMetadata(User::class);
        
        $classes = $this->registry->getRegisteredClasses();
        $this->assertContains(User::class, $classes);
    }

    public function testGetAllMetadata(): void
    {
        $this->assertEmpty($this->registry->getAllMetadata());
        
        // Load an entity
        $metadata = $this->registry->getEntityMetadata(User::class);
        
        $allMetadata = $this->registry->getAllMetadata();
        $this->assertArrayHasKey(User::class, $allMetadata);
        $this->assertSame($metadata, $allMetadata[User::class]);
    }

    public function testClear(): void
    {
        // Load an entity
        $this->registry->getEntityMetadata(User::class);
        $this->assertTrue($this->registry->hasMetadata(User::class));
        
        // Clear registry
        $this->registry->clear();
        
        $this->assertFalse($this->registry->hasMetadata(User::class));
        $this->assertEquals(0, $this->registry->count());
        $this->assertEmpty($this->registry->getRegisteredClasses());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->registry->count());
        
        // Load entities
        $this->registry->getEntityMetadata(User::class);
        $this->assertEquals(1, $this->registry->count());
    }

    public function testGetLoadedEntities(): void
    {
        $this->assertEmpty($this->registry->getLoadedEntities());
        
        // Load an entity
        $this->registry->getEntityMetadata(User::class);
        
        $loadedEntities = $this->registry->getLoadedEntities();
        $this->assertContains(User::class, $loadedEntities);
        $this->assertCount(1, $loadedEntities);
    }

    public function testLoadEntitiesFromPath(): void
    {
        $entitiesPath = __DIR__ . '/../Files/Entity';
        
        $this->registry->loadEntitiesFromPath($entitiesPath);
        
        // The loadEntitiesFromPath method loads classes but doesn't pre-build metadata
        // Metadata is built on first access
        $metadata = $this->registry->getEntityMetadata(User::class);
        $this->assertInstanceOf(EntityMetadata::class, $metadata);
        
        // After accessing metadata, it should be registered
        $this->assertTrue($this->registry->hasMetadata(User::class));
        $this->assertGreaterThan(0, $this->registry->count());
    }

    // Legacy compatibility methods tests

    public function testGetTableName(): void
    {
        $result = $this->registry->getTableName(User::class);
        
        $this->assertEquals('users_test', $result);
        $this->assertTrue($this->registry->hasMetadata(User::class));
    }

    public function testGetPropertiesColumns(): void
    {
        $result = $this->registry->getPropertiesColumns(User::class);
        
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('id', $result); // withoutId = true by default
    }

    public function testGetPropertiesColumnsWithId(): void
    {
        $result = $this->registry->getPropertiesColumns(User::class, false);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
    }

    public function testGetPropertyMetadata(): void
    {
        // This method should return column information for a property
        $result = $this->registry->getPropertyMetadata(User::class, 'username');
        
        // The result should be the column definition for 'username'
        $this->assertNotNull($result);
    }

    public function testMultipleEntityRegistration(): void
    {
        $entity1 = 'Entity1';
        $entity2 = 'Entity2';
        
        $metadata1 = new EntityMetadata(
            className: $entity1,
            tableName: 'table1',
            columns: ['id' => 'id']
        );
        
        $metadata2 = new EntityMetadata(
            className: $entity2,
            tableName: 'table2',
            columns: ['id' => 'id']
        );
        
        $this->registry->registerMetadata($entity1, $metadata1);
        $this->registry->registerMetadata($entity2, $metadata2);
        
        $this->assertEquals(2, $this->registry->count());
        $this->assertTrue($this->registry->hasMetadata($entity1));
        $this->assertTrue($this->registry->hasMetadata($entity2));
        
        $classes = $this->registry->getRegisteredClasses();
        $this->assertContains($entity1, $classes);
        $this->assertContains($entity2, $classes);
    }

    public function testGetLoadedEntitiesSorted(): void
    {
        // Register entities in non-alphabetical order
        $this->registry->getEntityMetadata(User::class);
        
        $entities = ['ZEntity', 'AEntity', 'MEntity'];
        foreach ($entities as $entity) {
            $metadata = new EntityMetadata(
                className: $entity,
                tableName: strtolower($entity),
                columns: ['id' => 'id']
            );
            $this->registry->registerMetadata($entity, $metadata);
        }
        
        $loadedEntities = $this->registry->getLoadedEntities();
        
        // Should be sorted alphabetically
        $expectedOrder = ['AEntity', 'MEntity', User::class, 'ZEntity'];
        $this->assertEquals($expectedOrder, $loadedEntities);
    }

    public function testImmutableRegistry(): void
    {
        // Load an entity
        $metadata = $this->registry->getEntityMetadata(User::class);
        
        // Get all metadata
        $allMetadata = $this->registry->getAllMetadata();
        
        // Modify the returned array (should not affect the registry)
        $allMetadata['FakeEntity'] = $metadata;
        
        // Registry should be unchanged
        $this->assertFalse($this->registry->hasMetadata('FakeEntity'));
        $this->assertArrayNotHasKey('FakeEntity', $this->registry->getAllMetadata());
    }

    public function testRegistryPersistenceAcrossCalls(): void
    {
        // Load an entity
        $this->registry->getEntityMetadata(User::class);
        
        // Multiple calls should return consistent data
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->registry->hasMetadata(User::class));
            $this->assertEquals(1, $this->registry->count());
            $this->assertContains(User::class, $this->registry->getLoadedEntities());
        }
    }
}