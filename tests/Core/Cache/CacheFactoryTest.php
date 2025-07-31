<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\MemoryCache;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Core\Cache\QueryStructureCache;
use MulerTech\Database\Core\Cache\ResultSetCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CacheFactory::class)]
final class CacheFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        CacheFactory::reset();
    }

    public function testCreateMemoryCache(): void
    {
        $cache = CacheFactory::createMemoryCache('test_memory');

        $this->assertInstanceOf(MemoryCache::class, $cache);
        $this->assertSame($cache, CacheFactory::get('test_memory'));
    }

    public function testCreateMemoryCacheWithCustomConfig(): void
    {
        $config = new CacheConfig(maxSize: 5000, ttl: 1800);
        $cache = CacheFactory::createMemoryCache('test_custom', $config);

        $this->assertInstanceOf(MemoryCache::class, $cache);
        
        $stats = $cache->getStatistics();
        $this->assertEquals(5000, $stats['max_size']);
    }

    public function testCreateMemoryCacheReturnsSameInstance(): void
    {
        $cache1 = CacheFactory::createMemoryCache('test_singleton');
        $cache2 = CacheFactory::createMemoryCache('test_singleton');

        $this->assertSame($cache1, $cache2);
    }

    public function testCreateMemoryCacheThrowsExceptionForWrongType(): void
    {
        // First create a QueryStructureCache with the same name to force type conflict
        CacheFactory::createQueryStructureCache('conflict_name');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cache instance is not of type MemoryCache');

        CacheFactory::createMemoryCache('conflict_name');
    }

    public function testCreateMetadataCache(): void
    {
        $cache = CacheFactory::createMetadataCache('test_metadata');

        $this->assertInstanceOf(MetadataCache::class, $cache);
        $this->assertSame($cache, CacheFactory::get('test_metadata'));
    }

    public function testCreateMetadataCacheWithCustomConfig(): void
    {
        $config = new CacheConfig(maxSize: 3000, ttl: 900);
        $cache = CacheFactory::createMetadataCache('test_metadata_custom', $config);

        $this->assertInstanceOf(MetadataCache::class, $cache);
    }

    public function testCreateMetadataCacheReturnsSameInstance(): void
    {
        $cache1 = CacheFactory::createMetadataCache('test_metadata_singleton');
        $cache2 = CacheFactory::createMetadataCache('test_metadata_singleton');

        $this->assertSame($cache1, $cache2);
    }

    public function testCreateMetadataCacheThrowsExceptionForWrongType(): void
    {
        // First create a MemoryCache with the same name
        CacheFactory::createMemoryCache('metadata_conflict');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cache instance is not of type MetadataCache');

        CacheFactory::createMetadataCache('metadata_conflict');
    }

    public function testCreateResultSetCache(): void
    {
        $cache = CacheFactory::createResultSetCache('test_resultset');

        $this->assertInstanceOf(ResultSetCache::class, $cache);
        $this->assertSame($cache, CacheFactory::get('test_resultset'));
    }

    public function testCreateResultSetCacheWithCustomConfig(): void
    {
        $config = new CacheConfig(maxSize: 2000, ttl: 1200);
        $cache = CacheFactory::createResultSetCache('test_resultset_custom', $config);

        $this->assertInstanceOf(ResultSetCache::class, $cache);
    }

    public function testCreateResultSetCacheReturnsSameInstance(): void
    {
        $cache1 = CacheFactory::createResultSetCache('test_resultset_singleton');
        $cache2 = CacheFactory::createResultSetCache('test_resultset_singleton');

        $this->assertSame($cache1, $cache2);
    }

    public function testCreateResultSetCacheThrowsExceptionForWrongType(): void
    {
        // First create a MemoryCache with the same name
        CacheFactory::createMemoryCache('resultset_conflict');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cache instance is not of type ResultSetCache');

        CacheFactory::createResultSetCache('resultset_conflict');
    }

    public function testCreateQueryStructureCache(): void
    {
        $cache = CacheFactory::createQueryStructureCache('test_query');

        $this->assertInstanceOf(QueryStructureCache::class, $cache);
        $this->assertSame($cache, CacheFactory::get('test_query'));
    }

    public function testCreateQueryStructureCacheWithCustomConfig(): void
    {
        $config = new CacheConfig(maxSize: 1500, ttl: 2400);
        $cache = CacheFactory::createQueryStructureCache('test_query_custom', $config);

        $this->assertInstanceOf(QueryStructureCache::class, $cache);
        
        $this->assertEquals(1500, $this->getQueryStructureCacheMaxSize($cache));
    }

    public function testCreateQueryStructureCacheReturnsSameInstance(): void
    {
        $cache1 = CacheFactory::createQueryStructureCache('test_query_singleton');
        $cache2 = CacheFactory::createQueryStructureCache('test_query_singleton');

        $this->assertSame($cache1, $cache2);
    }

    public function testCreateQueryStructureCacheThrowsExceptionForWrongType(): void
    {
        // First create a MemoryCache with the same name
        CacheFactory::createMemoryCache('query_conflict');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cache instance is not of type QueryStructureCache');

        CacheFactory::createQueryStructureCache('query_conflict');
    }

    public function testGetReturnsNullForNonExistentCache(): void
    {
        $this->assertNull(CacheFactory::get('non_existent'));
    }

    public function testReset(): void
    {
        CacheFactory::createMemoryCache('test1');
        CacheFactory::createMetadataCache('test2');

        $this->assertNotNull(CacheFactory::get('test1'));
        $this->assertNotNull(CacheFactory::get('test2'));

        CacheFactory::reset();

        $this->assertNull(CacheFactory::get('test1'));
        $this->assertNull(CacheFactory::get('test2'));
    }

    public function testResetClearsDefaultConfig(): void
    {
        // Create a cache to initialize default config
        CacheFactory::createMemoryCache('test');
        
        CacheFactory::reset();
        
        // Create another cache - should use fresh default config
        $cache = CacheFactory::createMemoryCache('test2');
        $this->assertInstanceOf(MemoryCache::class, $cache);
    }

    public function testMultipleCacheTypesWithSameNameThrowsException(): void
    {
        CacheFactory::createMemoryCache('shared_name');

        $this->expectException(RuntimeException::class);
        CacheFactory::createMetadataCache('shared_name');
    }

    private function getQueryStructureCacheMaxSize(QueryStructureCache $cache): mixed
    {
        $reflection = new \ReflectionClass($cache);
        return $reflection->getProperty('maxCacheSize')->getValue($cache);
    }
}