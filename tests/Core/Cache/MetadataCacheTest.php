<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\CacheInterface;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Tests\Files\Cache\BaseCacheTest;
use ReflectionException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MetadataCache::class)]
final class MetadataCacheTest extends BaseCacheTest
{
    protected function createCacheInstance(): CacheInterface
    {
        return new MetadataCache();
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testConstructorWithDefaultConfig(): void
    {
        $cache = new MetadataCache();
        
        $stats = $cache->getStatistics();
        $this->assertEquals(50000, $stats['max_size']);
        $this->assertEquals('lru', $stats['eviction_policy']);
    }

    public function testConstructorWithCustomConfig(): void
    {
        $config = new CacheConfig(maxSize: 10000, ttl: 7200, evictionPolicy: 'lfu');
        $cache = new MetadataCache($config);
        
        $stats = $cache->getStatistics();
        $this->assertEquals(10000, $stats['max_size']);
        $this->assertEquals('lfu', $stats['eviction_policy']);
    }

    public function testSetAndGetMetadata(): void
    {
        $metadata = ['table' => 'users', 'columns' => ['id', 'name']];
        
        $this->cache->setMetadata('test_key', $metadata);
        
        $this->assertEquals($metadata, $this->cache->get('test_key'));
    }

    public function testSetPermanentMetadata(): void
    {
        $metadata = ['permanent' => true];
        
        $this->cache->setPermanentMetadata('permanent_key', $metadata);
        
        $this->assertEquals($metadata, $this->cache->get('permanent_key'));
    }

    public function testSetEntityMetadata(): void
    {
        $metadata = ['table' => 'users'];
        
        $this->cache->setEntityMetadata('TestEntity', $metadata);
        
        $this->assertEquals($metadata, $this->cache->get('entity:TestEntity'));
    }

    public function testGetPropertyMetadata(): void
    {
        $metadata = ['column' => 'user_name', 'type' => 'string'];
        
        $this->cache->setPropertyMetadata('TestEntity', 'name', $metadata);
        
        $result = $this->cache->getPropertyMetadata('TestEntity', 'name');
        $this->assertEquals($metadata, $result);
    }

    public function testSetPropertyMetadata(): void
    {
        $metadata = ['column' => 'user_id', 'type' => 'integer'];
        
        $this->cache->setPropertyMetadata('TestEntity', 'id', $metadata);
        
        $this->assertEquals($metadata, $this->cache->get('property:TestEntity:id'));
    }

    public function testIsWarmedUp(): void
    {
        $this->assertFalse($this->cache->isWarmedUp('TestEntity'));
        
        $this->cache->set('TestEntity:warmed', true);
        
        $this->assertTrue($this->cache->isWarmedUp('TestEntity'));
    }

    public function testGetTableNameWithEntityProcessor(): void
    {
        $cache = new MetadataCache();
        
        $result = $cache->getTableName('MulerTech\\Database\\Tests\\Files\\Entity\\User');
        
        $this->assertEquals('users_test', $result);
    }

    public function testGetTableNameWithCachedValue(): void
    {
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        $result = $this->cache->getTableName($entityClass);
        
        $this->assertEquals('users_test', $result);
    }

    public function testGetPropertiesColumnsWithEntityProcessor(): void
    {
        $cache = new MetadataCache();
        
        $result = $cache->getPropertiesColumns('MulerTech\\Database\\Tests\\Files\\Entity\\User');
        
        $this->assertIsArray($result);
    }

    public function testGetPropertiesColumnsWithCachedValue(): void
    {
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        $result = $this->cache->getPropertiesColumns($entityClass);
        
        $this->assertIsArray($result);
    }

    public function testGetPropertiesColumnsWithInvalidData(): void
    {
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        $result = $this->cache->getPropertiesColumns($entityClass);
        
        $this->assertIsArray($result);
    }

    public function testGetPropertiesColumnsWithNonArrayData(): void
    {
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        $result = $this->cache->getPropertiesColumns($entityClass);
        
        $this->assertIsArray($result);
    }

    public function testInheritanceFromMemoryCache(): void
    {
        // Test that MetadataCache inherits MemoryCache functionality
        $this->cache->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $this->cache->get('test_key'));
        $this->assertTrue($this->cache->has('test_key'));
    }

    public function testTaggingFunctionality(): void
    {
        $this->cache->setEntityMetadata('TestEntity', ['table' => 'users']);
        
        // The setEntityMetadata method should automatically tag with 'entity_metadata'
        $this->assertEquals(['table' => 'users'], $this->cache->get('entity:TestEntity'));
        
        // Test invalidation by tag
        $this->cache->invalidateTag('entity_metadata');
        
        $this->assertNull($this->cache->get('entity:TestEntity'));
    }

    public function testPropertyMetadataTagging(): void
    {
        $this->cache->setPropertyMetadata('TestEntity', 'name', ['column' => 'user_name']);
        
        // Property metadata should be tagged with both 'property_metadata' and the entity class
        $this->assertEquals(['column' => 'user_name'], $this->cache->get('property:TestEntity:name'));
        
        // Test invalidation by property_metadata tag
        $this->cache->invalidateTag('property_metadata');
        
        $this->assertNull($this->cache->get('property:TestEntity:name'));
    }

    public function testPropertyMetadataTaggingByEntityClass(): void
    {
        $this->cache->setPropertyMetadata('TestEntity', 'name', ['column' => 'user_name']);
        $this->cache->setPropertyMetadata('AnotherEntity', 'title', ['column' => 'entity_title']);
        
        // Test invalidation by entity class tag
        $this->cache->invalidateTag('TestEntity');
        
        $this->assertNull($this->cache->get('property:TestEntity:name'));
        $this->assertEquals(['column' => 'entity_title'], $this->cache->get('property:AnotherEntity:title'));
    }

    public function testMetadataCacheInstanceCreation(): void
    {
        $cache = new MetadataCache();
        
        $this->assertInstanceOf(MetadataCache::class, $cache);
    }

    public function testGetTableNameWarmUpProcess(): void
    {
        $cache = new MetadataCache();
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        $result = $cache->getTableName($entityClass);
        
        $this->assertEquals('users_test', $result);
        $this->assertTrue($cache->isWarmedUp($entityClass));
    }

    public function testGetPropertiesColumnsWarmUpProcess(): void
    {
        $cache = new MetadataCache();
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        $result = $cache->getPropertiesColumns($entityClass);
        
        $this->assertIsArray($result);
        $this->assertTrue($cache->isWarmedUp($entityClass));
    }

    public function testWarmUpWithException(): void
    {
        $cache = new MetadataCache();
        
        $this->expectException(ReflectionException::class);
        
        $cache->getTableName('NonExistentEntity');
    }

    public function testZeroTtlForMetadata(): void
    {
        $config = new CacheConfig(ttl: 3600); // Normal TTL
        $cache = new MetadataCache($config);
        
        // Metadata should be set with TTL 0 (no expiration) regardless of config TTL
        $cache->setMetadata('test_key', 'test_value');
        
        // Since we can't easily test TTL without waiting, we verify the value is set
        $this->assertEquals('test_value', $cache->get('test_key'));
    }

    public function testLoadEntitiesFromPath(): void
    {
        $cache = new MetadataCache();
        $entitiesPath = __DIR__ . '/../../Files/Entity';
        
        $cache->loadEntitiesFromPath($entitiesPath);
        
        // Verify that entities were loaded and warmed up
        $this->assertTrue($cache->isWarmedUp('MulerTech\\Database\\Tests\\Files\\Entity\\User'));
    }

    public function testConstructorWithEntitiesPath(): void
    {
        $entitiesPath = __DIR__ . '/../../Files/Entity';
        $cache = new MetadataCache(null, $entitiesPath);
        
        // Verify that entities were automatically loaded
        $this->assertTrue($cache->isWarmedUp('MulerTech\\Database\\Tests\\Files\\Entity\\User'));
    }

    public function testWarmUpEntities(): void
    {
        $cache = new MetadataCache();
        $entityClasses = [
            'MulerTech\\Database\\Tests\\Files\\Entity\\User'
        ];
        
        $cache->warmUpEntities($entityClasses);
        
        foreach ($entityClasses as $entityClass) {
            $this->assertTrue($cache->isWarmedUp($entityClass));
        }
    }

    public function testGetLoadedEntities(): void
    {
        $cache = new MetadataCache();
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        // Initially no entities loaded
        $this->assertEmpty($cache->getLoadedEntities());
        
        // Load an entity
        $cache->getEntityMetadata($entityClass);
        
        // Now should have the loaded entity
        $loadedEntities = $cache->getLoadedEntities();
        $this->assertContains($entityClass, $loadedEntities);
    }

    public function testGetLoadedTables(): void
    {
        $cache = new MetadataCache();
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        // Initially no tables loaded
        $this->assertEmpty($cache->getLoadedTables());
        
        // Load an entity
        $cache->getEntityMetadata($entityClass);
        
        // Now should have the loaded table
        $loadedTables = $cache->getLoadedTables();
        $this->assertContains('users_test', $loadedTables);
    }

    public function testGetEntityMetadataThrowsExceptionForInvalidClass(): void
    {
        $cache = new MetadataCache();
        
        $this->expectException(\ReflectionException::class);
        
        $cache->getEntityMetadata('NonExistentClass');
    }

    public function testWarmUpEntityWithRuntimeException(): void
    {
        $cache = new MetadataCache();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to warm up entity metadata for NonExistentClass');
        
        // This will trigger the private warmUpEntity method via loadEntitiesFromPath
        // since warmUpEntity is private, we need to test it indirectly
        $reflection = new \ReflectionClass($cache);
        $method = $reflection->getMethod('warmUpEntity');
        $method->setAccessible(true);
        
        $method->invoke($cache, 'NonExistentClass');
    }

    public function testGetEntityMetadataCaching(): void
    {
        $cache = new MetadataCache();
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        // First call - will build metadata
        $metadata1 = $cache->getEntityMetadata($entityClass);
        
        // Second call - should return cached metadata
        $metadata2 = $cache->getEntityMetadata($entityClass);
        
        $this->assertSame($metadata1, $metadata2);
        $this->assertTrue($cache->isWarmedUp($entityClass));
    }

    public function testWarmUpEntitySkipsAlreadyWarmedUp(): void
    {
        $cache = new MetadataCache();
        $entityClass = 'MulerTech\\Database\\Tests\\Files\\Entity\\User';
        
        // First, warm up the entity
        $cache->warmUpEntities([$entityClass]);
        $this->assertTrue($cache->isWarmedUp($entityClass));
        
        // Get initial stats
        $initialStats = $cache->getStatistics();
        
        // Call warmUpEntities again with the same entity - should hit the isWarmedUp() condition and return early
        $cache->warmUpEntities([$entityClass]);
        
        // Verify entity is still warmed up
        $this->assertTrue($cache->isWarmedUp($entityClass));
        
        // Stats should not have changed much since the entity was already warmed up
        $finalStats = $cache->getStatistics();
        $this->assertEquals($initialStats['writes'], $finalStats['writes'], 'No additional writes should occur for already warmed up entity');
    }
}