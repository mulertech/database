<?php

namespace MulerTech\Database\Tests\Cache;

use MulerTech\Database\Cache\CacheConfig;
use MulerTech\Database\Cache\MemoryCache;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MemoryCache
 * @package MulerTech\Database\Tests\Cache
 * @author Sébastien Muler
 */
class MemoryCacheTest extends TestCase
{
    /**
     * @var MemoryCache
     */
    private MemoryCache $cache;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cache = new MemoryCache(
            new CacheConfig(
                maxSize:        3,
                ttl:            3600,
                enableStats:    true,
                evictionPolicy: 'lru'
            )
        );
    }

    /**
     * @return void
     */
    public function testBasicSetAndGet(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertTrue($this->cache->has('key1'));
    }

    /**
     * @return void
     */
    public function testGetNonExistentKey(): void
    {
        $this->assertNull($this->cache->get('non_existent'));
        $this->assertFalse($this->cache->has('non_existent'));
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));

        $this->cache->delete('key1');
        $this->assertFalse($this->cache->has('key1'));
        $this->assertNull($this->cache->get('key1'));
    }

    /**
     * @return void
     */
    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->cache->clear();

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    /**
     * @return void
     */
    public function testTtlExpiration(): void
    {
        $this->cache->set('key1', 'value1', 1); // 1 second TTL
        $this->assertTrue($this->cache->has('key1'));

        sleep(2);

        $this->assertFalse($this->cache->has('key1'));
        $this->assertNull($this->cache->get('key1'));
    }

    /**
     * @return void
     */
    public function testMultipleOperations(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->cache->setMultiple($values);

        $retrieved = $this->cache->getMultiple(['key1', 'key2', 'key3', 'key4']);

        $this->assertEquals('value1', $retrieved['key1']);
        $this->assertEquals('value2', $retrieved['key2']);
        $this->assertEquals('value3', $retrieved['key3']);
        $this->assertNull($retrieved['key4']);

        $this->cache->deleteMultiple(['key1', 'key3']);

        $this->assertFalse($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    /**
     * @return void
     */
    public function testLruEviction(): void
    {
        // Cache size is 3
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        // Access key1 and key2 to make them more recently used
        sleep(1); // Ensure time passes for LRU
        $this->cache->get('key1');
        $this->cache->get('key2');

        // Adding key4 should evict key3 (least recently used)
        $this->cache->set('key4', 'value4');

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3')); // Evicted
        $this->assertTrue($this->cache->has('key4'));
    }

    /**
     * @return void
     */
    public function testTagging(): void
    {
        $this->cache->set('user:1', ['name' => 'John']);
        $this->cache->set('user:2', ['name' => 'Jane']);
        $this->cache->set('post:1', ['title' => 'Hello']);

        $this->cache->tag('user:1', ['users', 'active']);
        $this->cache->tag('user:2', ['users', 'active']);
        $this->cache->tag('post:1', ['posts']);

        // Invalidate all users
        $this->cache->invalidateTag('users');

        $this->assertFalse($this->cache->has('user:1'));
        $this->assertFalse($this->cache->has('user:2'));
        $this->assertTrue($this->cache->has('post:1')); // Not affected
    }

    /**
     * @return void
     */
    public function testStatistics(): void
    {
        $this->cache->set('key1', 'value1');

        // Generate some hits and misses
        $this->cache->get('key1'); // Hit
        $this->cache->get('key2'); // Miss
        $this->cache->get('key1'); // Hit
        $this->cache->get('key3'); // Miss

        $stats = $this->cache->getStatistics();

        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(2, $stats['misses']);
        $this->assertEquals(50.0, $stats['hit_rate']);
        $this->assertEquals(1, $stats['size']);
        $this->assertEquals(0, $stats['evictions']);
    }

    /**
     * @return void
     */
    public function testLfuEviction(): void
    {
        $cache = new MemoryCache(
            new CacheConfig(
                maxSize:        3,
                ttl:            3600,
                enableStats:    true,
                evictionPolicy: 'lfu'
            )
        );

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');

        // Access key1 and key2 multiple times
        $cache->get('key1');
        $cache->get('key1');
        $cache->get('key2');

        // key3 has the least frequency (0 accesses)
        $cache->set('key4', 'value4');

        $this->assertTrue($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
        $this->assertFalse($cache->has('key3')); // Evicted
        $this->assertTrue($cache->has('key4'));
    }

    /**
     * @return void
     */
    public function testFifoEviction(): void
    {
        $cache = new MemoryCache(
            new CacheConfig(
                maxSize:        3,
                ttl:            3600,
                enableStats:    true,
                evictionPolicy: 'fifo'
            )
        );

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');

        // Regardless of access patterns, key1 was inserted first
        $cache->get('key1');
        $cache->get('key1');

        $cache->set('key4', 'value4');

        $this->assertFalse($cache->has('key1')); // Evicted (first in)
        $this->assertTrue($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
        $this->assertTrue($cache->has('key4'));
    }
}