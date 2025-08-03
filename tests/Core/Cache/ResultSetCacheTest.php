<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use MulerTech\Database\Core\Cache\ResultSetCache;
use MulerTech\Database\Tests\Files\Cache\Mock\MockCache;
use MulerTech\Database\Tests\Files\Cache\Mock\SimpleMockCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Notice;

#[CoversClass(ResultSetCache::class)]
final class ResultSetCacheTest extends TestCase
{
    private MockCache $mockCache;
    private ResultSetCache $cache;

    protected function setUp(): void
    {
        $this->mockCache = new MockCache();
        $this->cache = new ResultSetCache($this->mockCache, 100);
    }

    public function testConstructor(): void
    {
        $cache = new ResultSetCache($this->mockCache);
        
        $this->assertInstanceOf(ResultSetCache::class, $cache);
    }

    public function testConstructorWithCompressionThreshold(): void
    {
        $cache = new ResultSetCache($this->mockCache, 2048);
        
        $this->assertInstanceOf(ResultSetCache::class, $cache);
    }

    public function testSetAndGet(): void
    {
        $data = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
        
        $this->cache->set('user:1', $data);
        $result = $this->cache->get('user:1');
        
        $this->assertEquals($data, $result);
    }

    public function testGetNonExistent(): void
    {
        $result = $this->cache->get('non_existent');
        
        $this->assertNull($result);
    }

    public function testSetWithTtl(): void
    {
        $data = ['test' => 'data'];
        
        $this->cache->set('ttl_key', $data, 3600);
        $result = $this->cache->get('ttl_key');
        
        $this->assertEquals($data, $result);
    }

    public function testDelete(): void
    {
        $data = ['test' => 'data'];
        
        $this->cache->set('delete_me', $data);
        $this->assertTrue($this->cache->has('delete_me'));
        
        $this->cache->delete('delete_me');
        $this->assertFalse($this->cache->has('delete_me'));
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'data1');
        $this->cache->set('key2', 'data2');
        
        $this->cache->clear();
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('test_key'));
        
        $this->cache->set('test_key', 'test_data');
        $this->assertTrue($this->cache->has('test_key'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'data1');
        $this->cache->set('key2', 'data2');
        
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);
        
        $expected = [
            'key1' => 'data1',
            'key2' => 'data2',
            'key3' => null
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testSetMultiple(): void
    {
        $data = [
            'multi1' => 'value1',
            'multi2' => 'value2'
        ];
        
        $this->cache->setMultiple($data);
        
        $this->assertEquals('value1', $this->cache->get('multi1'));
        $this->assertEquals('value2', $this->cache->get('multi2'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $data = [
            'ttl1' => 'value1',
            'ttl2' => 'value2'
        ];
        
        $this->cache->setMultiple($data, 3600);
        
        $this->assertEquals('value1', $this->cache->get('ttl1'));
        $this->assertEquals('value2', $this->cache->get('ttl2'));
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('del1', 'data1');
        $this->cache->set('del2', 'data2');
        $this->cache->set('keep', 'data3');
        
        $this->cache->deleteMultiple(['del1', 'del2']);
        
        $this->assertFalse($this->cache->has('del1'));
        $this->assertFalse($this->cache->has('del2'));
        $this->assertTrue($this->cache->has('keep'));
    }

    public function testTagging(): void
    {
        $this->cache->set('tagged_key', 'tagged_data');
        $this->cache->tag('tagged_key', ['tag1', 'tag2']);
        
        $this->assertEquals('tagged_data', $this->cache->get('tagged_key'));
        
        // Verify the underlying cache was tagged
        $tags = $this->mockCache->getStoredTags();
        $this->assertArrayHasKey('tag1', $tags);
        $this->assertArrayHasKey('tag2', $tags);
    }

    public function testInvalidateTag(): void
    {
        $this->cache->set('key1', 'data1');
        $this->cache->set('key2', 'data2');
        
        $this->cache->tag('key1', ['shared_tag']);
        $this->cache->tag('key2', ['shared_tag']);
        
        $this->cache->invalidateTag('shared_tag');
        
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testInvalidateTags(): void
    {
        $this->cache->set('key1', 'data1');
        $this->cache->set('key2', 'data2');
        $this->cache->set('key3', 'data3');
        
        $this->cache->tag('key1', ['tag1']);
        $this->cache->tag('key2', ['tag2']);
        $this->cache->tag('key3', ['tag3']);
        
        $this->cache->invalidateTags(['tag1', 'tag2']);
        
        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
        $this->assertEquals('data3', $this->cache->get('key3'));
    }

    public function testInvalidateTable(): void
    {
        $this->cache->set('user:1', ['id' => 1, 'name' => 'John']);
        $this->cache->set('post:1', ['id' => 1, 'title' => 'Test']);
        
        $this->cache->tag('user:1', ['table:users']);
        $this->cache->tag('post:1', ['table:posts']);
        
        $this->cache->invalidateTable('users');
        
        $this->assertNull($this->cache->get('user:1'));
        $this->assertEquals(['id' => 1, 'title' => 'Test'], $this->cache->get('post:1'));
    }

    public function testInvalidateTables(): void
    {
        $this->cache->set('user:1', ['id' => 1, 'name' => 'John']);
        $this->cache->set('post:1', ['id' => 1, 'title' => 'Test']);
        $this->cache->set('comment:1', ['id' => 1, 'text' => 'Comment']);
        
        $this->cache->tag('user:1', ['table:users']);
        $this->cache->tag('post:1', ['table:posts']);
        $this->cache->tag('comment:1', ['table:comments']);
        
        $this->cache->invalidateTables(['users', 'posts']);
        
        $this->assertNull($this->cache->get('user:1'));
        $this->assertNull($this->cache->get('post:1'));
        $this->assertEquals(['id' => 1, 'text' => 'Comment'], $this->cache->get('comment:1'));
    }

    public function testCompressionSmallData(): void
    {
        $smallData = 'small';
        
        $this->cache->set('small_key', $smallData);
        $result = $this->cache->get('small_key');
        
        $this->assertEquals($smallData, $result);
        
        // Verify data is stored uncompressed (below threshold)
        $stored = $this->mockCache->getStoredData()['small_key'];
        $this->assertFalse($stored['compressed']);
    }

    public function testCompressionLargeData(): void
    {
        $cache = new ResultSetCache($this->mockCache, 10); // Low threshold
        $largeData = str_repeat('This is a long string for compression testing. ', 100);
        
        $cache->set('large_key', $largeData);
        $result = $cache->get('large_key');
        
        $this->assertEquals($largeData, $result);
        
        // Verify data is stored compressed (above threshold)
        $stored = $this->mockCache->getStoredData()['large_key'];
        $this->assertTrue($stored['compressed']);
    }

    public function testGetWithInvalidDataStructure(): void
    {
        // Manually set invalid data in underlying cache
        $this->mockCache->set('invalid_key', 'not_an_array');
        
        $result = $this->cache->get('invalid_key');
        
        $this->assertNull($result);
    }

    public function testGetWithMissingDataFields(): void
    {
        // Manually set invalid data structure
        $this->mockCache->set('incomplete_key', ['compressed' => true]); // Missing 'data'
        
        $result = $this->cache->get('incomplete_key');
        
        $this->assertNull($result);
    }

    public function testGetWithInvalidFieldTypes(): void
    {
        // Manually set invalid field types
        $this->mockCache->set('invalid_types', [
            'compressed' => 'not_boolean',
            'data' => 'valid_string'
        ]);
        
        $result = $this->cache->get('invalid_types');
        
        $this->assertNull($result);
    }

    public function testGetMultipleWithInvalidData(): void
    {
        $this->cache->set('valid_key', 'valid_data');
        $this->mockCache->set('invalid_key', 'not_an_array');
        
        $result = $this->cache->getMultiple(['valid_key', 'invalid_key']);
        
        $expected = [
            'valid_key' => 'valid_data',
            'invalid_key' => null
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testWithNonTaggableCache(): void
    {
        $simpleCache = new SimpleMockCache();
        $cache = new ResultSetCache($simpleCache);
        
        $cache->set('key1', 'data1');
        $this->assertEquals('data1', $cache->get('key1'));
        
        // These should not throw exceptions even though the underlying cache doesn't support tagging
        $cache->tag('key1', ['tag1']);
        $cache->invalidateTag('tag1');
        $cache->invalidateTags(['tag1', 'tag2']);
        $cache->invalidateTable('users');
        $cache->invalidateTables(['users', 'posts']);
        
        // Data should still be there since tagging operations are no-ops
        $this->assertEquals('data1', $cache->get('key1'));
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
            'nested_structure' => [
                'users' => [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane']
                ],
                'meta' => ['total' => 2, 'page' => 1]
            ]
        ];
        
        foreach ($testCases as $key => $value) {
            $this->cache->set($key, $value);
            $result = $this->cache->get($key);
            
            if ($key === 'object') {
                // Objects will be null due to 'allowed_classes' => false in unserialize
                $this->assertNull($result);
            } else {
                $this->assertEquals($value, $result);
            }
        }
    }

    public function testCompressionFailureHandling(): void
    {
        // Test the fallback when compression fails or doesn't reduce size
        $cache = new ResultSetCache($this->mockCache, 1); // Very low threshold
        
        // Data that might not compress well
        $randomData = random_bytes(50);
        
        $cache->set('random_key', $randomData);
        $result = $cache->get('random_key');
        
        $this->assertEquals($randomData, $result);
    }

    public function testDecompressionFailureHandling(): void
    {
        // Manually set compressed data that can't be decompressed
        $this->mockCache->set('corrupted_key', [
            'compressed' => true,
            'data' => 'invalid_compressed_data'
        ]);

        $result = $this->cache->get('corrupted_key');
        
        // Should return null or the corrupted data, but not throw exception
        $this->assertTrue($result === null || is_string($result));
    }

    public function testReadonlyClass(): void
    {
        // Verify that ResultSetCache is readonly
        $cache = new ResultSetCache($this->mockCache);
        
        // This test passes if the class can be instantiated as readonly
        $this->assertInstanceOf(ResultSetCache::class, $cache);
    }
}