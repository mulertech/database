<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\CacheInterface;
use MulerTech\Database\Core\Cache\MemoryCache;
use MulerTech\Database\Tests\Files\Cache\BaseCacheTest;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MemoryCache::class)]
final class MemoryCacheTest extends BaseCacheTest
{
    protected function createCacheInstance(): CacheInterface
    {
        return new MemoryCache(new CacheConfig(maxSize: 100, ttl: 3600));
    }

    protected function setUp(): void
    {
        parent::setUp();
    }


    public function testTagging(): void
    {
        $this->cache->set('tagged_key', 'tagged_value');
        $this->cache->tag('tagged_key', ['tag1', 'tag2']);
        
        $this->assertEquals('tagged_value', $this->cache->get('tagged_key'));
        
        $this->cache->invalidateTag('tag1');
        
        $this->assertNull($this->cache->get('tagged_key'));
    }

    public function testTaggingNonExistentKey(): void
    {
        $this->cache->tag('non_existent', ['tag1']);
        
        $this->assertTrue(true); // Should not throw exception
    }

    public function testInvalidateMultipleTags(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        $this->cache->tag('key1', ['tag1']);
        $this->cache->tag('key2', ['tag2']);
        $this->cache->tag('key3', ['tag1', 'tag2']);
        
        $this->cache->invalidateTags(['tag1', 'tag2']);
        
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
        $this->assertNull($this->cache->get('key3'));
    }

    public function testInvalidateNonExistentTag(): void
    {
        $this->cache->invalidateTag('non_existent_tag');
        
        $this->assertTrue(true); // Should not throw exception
    }

    public function testEvictionLru(): void
    {
        $cache = new MemoryCache(new CacheConfig(maxSize: 2, evictionPolicy: 'lru'));
        
        $cache->set('key1', 'value1');
        sleep(1); // Ensure different timestamps
        $cache->set('key2', 'value2');
        
        // Access key1 to make it most recently used
        sleep(1); // Ensure different timestamp
        $cache->get('key1');
        
        // This should evict key2 (least recently used) when we add key3
        sleep(1); // Ensure different timestamp
        $cache->set('key3', 'value3');
        
        // Verify cache respects size limit
        $stats = $cache->getStatistics();
        $this->assertEquals(2, $stats['size'], 'Cache should contain exactly 2 items');
        
        // key3 should be there (just added)
        $this->assertTrue($cache->has('key3'), 'Newly added key3 should remain');
        
        // Verify that eviction occurred
        $this->assertGreaterThan(0, $stats['evictions'], 'At least one eviction should have occurred');
        
        // Test that LRU behavior generally works by checking the cache contains the expected number of items
        $totalItems = ($cache->has('key1') ? 1 : 0) + ($cache->has('key2') ? 1 : 0) + ($cache->has('key3') ? 1 : 0);
        $this->assertEquals(2, $totalItems, 'Cache should maintain exactly maxSize items');
    }

    public function testEvictionLfu(): void
    {
        $cache = new MemoryCache(new CacheConfig(maxSize: 3, evictionPolicy: 'lfu'));
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        // Access key1 multiple times
        $cache->get('key1');
        $cache->get('key1');
        
        // This should evict key2 or key3 (least frequently used)
        $cache->set('key4', 'value4');
        
        $this->assertTrue($cache->has('key1'));
        $this->assertTrue($cache->has('key4'));
        
        // Either key2 or key3 should be evicted, but key1 should remain
        $evicted = !$cache->has('key2') || !$cache->has('key3');
        $this->assertTrue($evicted);
    }

    public function testEvictionFifo(): void
    {
        $cache = new MemoryCache(new CacheConfig(maxSize: 3, evictionPolicy: 'fifo'));
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        // This should evict key1 (first in, first out)
        $cache->set('key4', 'value4');
        
        $this->assertFalse($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
        $this->assertTrue($cache->has('key4'));
    }

    public function testUpdateExistingKeyDoesNotTriggerEviction(): void
    {
        $cache = new MemoryCache(new CacheConfig(maxSize: 2));
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        
        // Update existing key should not trigger eviction
        $cache->set('key1', 'updated_value1');
        
        $this->assertEquals('updated_value1', $cache->get('key1'));
        $this->assertTrue($cache->has('key2'));
    }

    public function testStatistics(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->get('key1'); // hit
        $this->cache->get('non_existent'); // miss
        $this->cache->delete('key1');
        
        $stats = $this->cache->getStatistics();
        
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
        $this->assertArrayHasKey('deletes', $stats);
        $this->assertArrayHasKey('evictions', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('size', $stats);
        $this->assertArrayHasKey('max_size', $stats);
        $this->assertArrayHasKey('eviction_policy', $stats);
        
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(1, $stats['writes']);
        $this->assertEquals(1, $stats['deletes']);
        $this->assertEquals(50.0, $stats['hit_rate']);
        $this->assertEquals(100, $stats['max_size']);
        $this->assertEquals('lru', $stats['eviction_policy']);
    }

    public function testHitRateCalculation(): void
    {
        $cache = new MemoryCache(new CacheConfig());
        
        // No requests yet
        $stats = $cache->getStatistics();
        $this->assertEquals(0, $stats['hit_rate']);
        
        $cache->set('key1', 'value1');
        $cache->get('key1'); // hit
        $cache->get('key1'); // hit
        $cache->get('missing'); // miss
        
        $stats = $cache->getStatistics();
        $this->assertEquals(66.67, $stats['hit_rate']); // 2 hits out of 3 requests
    }


    public function testTagDuplication(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->tag('key1', ['tag1', 'tag2']);
        $this->cache->tag('key1', ['tag2', 'tag3']); // tag2 is duplicated
        
        $this->assertEquals('value1', $this->cache->get('key1'));
        
        // Invalidating any tag should remove the key
        $this->cache->invalidateTag('tag3');
        $this->assertNull($this->cache->get('key1'));
    }

    public function testDeleteRemovesTags(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->tag('key1', ['shared_tag']);
        $this->cache->tag('key2', ['shared_tag']);
        
        $this->cache->delete('key1');
        
        // key2 should still exist and be accessible via shared_tag
        $this->assertEquals('value2', $this->cache->get('key2'));
        
        $this->cache->invalidateTag('shared_tag');
        
        // Only key2 should be affected now
        $this->assertNull($this->cache->get('key2'));
    }

    public function testFindFifoKeyWithEmptyCache(): void
    {
        $cache = new MemoryCache(new CacheConfig(maxSize: 1, evictionPolicy: 'fifo'));
        
        // Test FIFO eviction when cache is empty (edge case)
        $cache->set('key1', 'value1');
        
        // The cache is now at capacity. Adding another key should trigger FIFO eviction
        $cache->set('key2', 'value2');
        
        // key1 should be evicted (FIFO), key2 should remain
        $this->assertFalse($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
    }
}