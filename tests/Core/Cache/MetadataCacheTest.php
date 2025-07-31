<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use Exception;
use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Core\Cache\MetadataReflectionHelper;
use MulerTech\Database\Core\Cache\MetadataRelationsHelper;
use MulerTech\Database\Mapping\DbMappingInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MetadataCache::class)]
final class MetadataCacheTest extends TestCase
{
    private MetadataCache $cache;

    protected function setUp(): void
    {
        $this->cache = new MetadataCache();
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

    public function testGetTableNameWithoutDbMapping(): void
    {
        $cache = new MetadataCache();
        
        $result = $cache->getTableName('TestEntity');
        
        $this->assertNull($result);
    }

    public function testGetTableNameWithCachedValue(): void
    {
        $this->cache->set('TestEntity:table', 'users');
        
        $result = $this->cache->getTableName('TestEntity');
        
        $this->assertEquals('users', $result);
    }

    public function testGetPropertiesColumnsWithoutDbMapping(): void
    {
        $cache = new MetadataCache();
        
        $result = $cache->getPropertiesColumns('TestEntity');
        
        $this->assertNull($result);
    }

    public function testGetPropertiesColumnsWithCachedValue(): void
    {
        $properties = ['id' => 'user_id', 'name' => 'user_name'];
        $this->cache->set('TestEntity:properties', $properties);
        
        $result = $this->cache->getPropertiesColumns('TestEntity');
        
        $this->assertEquals($properties, $result);
    }

    public function testGetPropertiesColumnsWithInvalidData(): void
    {
        // Set invalid data (non-string values)
        $invalidProperties = ['id' => 123, 'name' => 'user_name'];
        $this->cache->set('TestEntity:properties', $invalidProperties);
        
        $result = $this->cache->getPropertiesColumns('TestEntity');
        
        $this->assertNull($result);
    }

    public function testGetPropertiesColumnsWithNonArrayData(): void
    {
        $this->cache->set('TestEntity:properties', 'not_an_array');
        
        $result = $this->cache->getPropertiesColumns('TestEntity');
        
        $this->assertNull($result);
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

    public function testWithDbMappingAndHelpers(): void
    {
        $dbMapping = $this->createMock(DbMappingInterface::class);
        $relationsHelper = new MetadataRelationsHelper();
        $reflectionHelper = new MetadataReflectionHelper();
        
        $cache = new MetadataCache(
            config: null,
            dbMapping: $dbMapping,
            relationsHelper: $relationsHelper,
            reflectionHelper: $reflectionHelper
        );
        
        $this->assertInstanceOf(MetadataCache::class, $cache);
    }

    public function testGetTableNameWarmUpProcess(): void
    {
        // Test avec une classe simple sans dépendances de DbMapping
        $cache = new MetadataCache();
        
        // Préparer manuellement le cache comme si le warm-up avait eu lieu
        $cache->set('TestEntity:table', 'users_test');
        $cache->set('TestEntity:warmed', true);
        
        $result = $cache->getTableName('TestEntity');
        
        $this->assertEquals('users_test', $result);
        $this->assertTrue($cache->isWarmedUp('TestEntity'));
    }

    public function testGetPropertiesColumnsWarmUpProcess(): void
    {
        $properties = ['id' => 'id', 'username' => 'username', 'size' => 'size'];
        
        // Test avec une classe simple sans dépendances de DbMapping
        $cache = new MetadataCache();
        
        // Préparer manuellement le cache comme si le warm-up avait eu lieu
        $cache->set('TestEntity:properties', $properties);
        $cache->set('TestEntity:warmed', true);
        
        $result = $cache->getPropertiesColumns('TestEntity');
        
        $this->assertEquals($properties, $result);
        $this->assertTrue($cache->isWarmedUp('TestEntity'));
    }

    public function testWarmUpWithException(): void
    {
        $dbMapping = $this->createMock(DbMappingInterface::class);
        $dbMapping->method('getTableName')->willThrowException(new Exception('DB error'));
        
        $cache = new MetadataCache(config: null, dbMapping: $dbMapping);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to warm up entity metadata for TestEntity');
        
        $cache->getTableName('TestEntity');
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
}