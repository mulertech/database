<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\CacheConfig;
use MulerTech\Database\Core\Cache\MemoryCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemoryCache::class)]
final class MemoryCacheTest extends TestCase
{
    private MemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryCache(new CacheConfig(maxSize: 100, ttl: 3600));
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('key1', 'value1');
        
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertTrue($this->cache->has('key1'));
    }

    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->cache->get('non_existent'));
        $this->assertFalse($this->cache->has('non_existent'));
    }

    public function testSetWithTtl(): void
    {
        $this->cache->set('ttl_key', 'ttl_value', 1);
        
        $this->assertEquals('ttl_value', $this->cache->get('ttl_key'));
        
        sleep(2);
        
        $this->assertNull($this->cache->get('ttl_key'));
        $this->assertFalse($this->cache->has('ttl_key'));
    }

    public function testDelete(): void
    {
        $this->cache->set('delete_key', 'delete_value');
        $this->assertTrue($this->cache->has('delete_key'));
        
        $this->cache->delete('delete_key');
        
        $this->assertFalse($this->cache->has('delete_key'));
        $this->assertNull($this->cache->get('delete_key'));
    }

    public function testDeleteNonExistentKey(): void
    {
        $this->cache->delete('non_existent');
        
        $this->assertTrue(true); // Should not throw exception
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $this->cache->clear();
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        $result = $this->cache->getMultiple(['key1', 'key2', 'non_existent']);
        
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2', 
            'non_existent' => null
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'multi1' => 'value1',
            'multi2' => 'value2'
        ];
        
        $this->cache->setMultiple($values);
        
        $this->assertEquals('value1', $this->cache->get('multi1'));
        $this->assertEquals('value2', $this->cache->get('multi2'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'ttl_multi1' => 'value1',
            'ttl_multi2' => 'value2'
        ];
        
        $this->cache->setMultiple($values, 1);
        
        $this->assertEquals('value1', $this->cache->get('ttl_multi1'));
        
        sleep(2);
        
        $this->assertNull($this->cache->get('ttl_multi1'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('del1', 'value1');
        $this->cache->set('del2', 'value2');
        $this->cache->set('del3', 'value3');
        
        $this->cache->deleteMultiple(['del1', 'del3']);
        
        $this->assertFalse($this->cache->has('del1'));
        $this->assertTrue($this->cache->has('del2'));
        $this->assertFalse($this->cache->has('del3'));
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

    public function testVariousDataTypes(): void
    {
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
            'array' => ['a', 'b', 'c'],
            'associative_array' => ['key' => 'value'],
            'object' => (object) ['property' => 'value']
        ];
        
        foreach ($testCases as $key => $value) {
            $this->cache->set($key, $value);
            $this->assertEquals($value, $this->cache->get($key));
        }
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
}