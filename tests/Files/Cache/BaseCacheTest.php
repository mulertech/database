<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache;

use MulerTech\Database\Core\Cache\CacheInterface;
use MulerTech\Database\Tests\Files\Cache\Mock\SimpleMockCache;
use PHPUnit\Framework\TestCase;

class BaseCacheTest extends TestCase
{
    protected function createCacheInstance(): CacheInterface
    {
        return new SimpleMockCache();
    }

    protected CacheInterface $cache;

    protected function setUp(): void
    {
        $this->cache = $this->createCacheInstance();
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $this->cache->get('test_key'));
    }

    public function testGetNonExistentKey(): void
    {
        $result = $this->cache->get('non_existent_key');
        
        $this->assertNull($result);
    }

    public function testHas(): void
    {
        $this->cache->set('existing_key', 'value');
        
        $this->assertTrue($this->cache->has('existing_key'));
        $this->assertFalse($this->cache->has('non_existent_key'));
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
        $this->cache->delete('non_existent_key');
        
        $this->assertTrue(true);
    }

    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        $this->cache->clear();
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testSetWithTtl(): void
    {
        $this->cache->set('ttl_key', 'ttl_value', 1);
        
        $this->assertEquals('ttl_value', $this->cache->get('ttl_key'));
        
        sleep(2);
        
        $this->assertNull($this->cache->get('ttl_key'));
        $this->assertFalse($this->cache->has('ttl_key'));
    }

    public function testSetWithZeroTtl(): void
    {
        $this->cache->set('no_ttl_key', 'no_ttl_value', 0);
        
        $this->assertEquals('no_ttl_value', $this->cache->get('no_ttl_key'));
        $this->assertTrue($this->cache->has('no_ttl_key'));
    }

    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        $result = $this->cache->getMultiple(['key1', 'key2', 'non_existent', 'key3']);
        
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'non_existent' => null,
            'key3' => 'value3'
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testGetMultipleEmptyArray(): void
    {
        $result = $this->cache->getMultiple([]);
        
        $this->assertEquals([], $result);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'multi_key1' => 'multi_value1',
            'multi_key2' => 'multi_value2',
            'multi_key3' => 'multi_value3'
        ];
        
        $this->cache->setMultiple($values);
        
        $this->assertEquals('multi_value1', $this->cache->get('multi_key1'));
        $this->assertEquals('multi_value2', $this->cache->get('multi_key2'));
        $this->assertEquals('multi_value3', $this->cache->get('multi_key3'));
    }

    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'ttl_multi1' => 'ttl_value1',
            'ttl_multi2' => 'ttl_value2'
        ];
        
        $this->cache->setMultiple($values, 1);
        
        $this->assertEquals('ttl_value1', $this->cache->get('ttl_multi1'));
        $this->assertEquals('ttl_value2', $this->cache->get('ttl_multi2'));
        
        sleep(2);
        
        $this->assertNull($this->cache->get('ttl_multi1'));
        $this->assertNull($this->cache->get('ttl_multi2'));
    }

    public function testSetMultipleEmptyArray(): void
    {
        $this->cache->setMultiple([]);
        
        $this->assertTrue(true);
    }

    public function testDeleteMultiple(): void
    {
        $this->cache->set('del_key1', 'del_value1');
        $this->cache->set('del_key2', 'del_value2');
        $this->cache->set('del_key3', 'del_value3');
        $this->cache->set('keep_key', 'keep_value');
        
        $this->cache->deleteMultiple(['del_key1', 'del_key3', 'non_existent']);
        
        $this->assertFalse($this->cache->has('del_key1'));
        $this->assertTrue($this->cache->has('del_key2'));
        $this->assertFalse($this->cache->has('del_key3'));
        $this->assertTrue($this->cache->has('keep_key'));
    }

    public function testDeleteMultipleEmptyArray(): void
    {
        $this->cache->deleteMultiple([]);
        
        $this->assertTrue(true);
    }

    public function testOverwriteExistingValue(): void
    {
        $this->cache->set('overwrite_key', 'original_value');
        $this->assertEquals('original_value', $this->cache->get('overwrite_key'));
        
        $this->cache->set('overwrite_key', 'new_value');
        $this->assertEquals('new_value', $this->cache->get('overwrite_key'));
    }

    public function testDifferentDataTypes(): void
    {
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14159,
            'boolean_true' => true,
            'boolean_false' => false,
            'null_value' => null,
            'array' => ['a', 'b', 'c'],
            'associative_array' => ['key' => 'value', 'nested' => ['inner' => 'value']],
            'object' => (object) ['property' => 'value', 'number' => 123]
        ];
        
        foreach ($testCases as $key => $value) {
            $this->cache->set($key, $value);
            $this->assertEquals($value, $this->cache->get($key), "Failed to get correct value for key: {$key}");
            if ($value !== null) {
                $this->assertTrue($this->cache->has($key), "Failed to find existing key: {$key}");
            }
        }
    }

    public function testKeysCaseSensitivity(): void
    {
        $this->cache->set('CamelCase', 'value1');
        $this->cache->set('camelcase', 'value2');
        $this->cache->set('CAMELCASE', 'value3');
        
        $this->assertEquals('value1', $this->cache->get('CamelCase'));
        $this->assertEquals('value2', $this->cache->get('camelcase'));
        $this->assertEquals('value3', $this->cache->get('CAMELCASE'));
        
        $this->assertTrue($this->cache->has('CamelCase'));
        $this->assertTrue($this->cache->has('camelcase'));
        $this->assertTrue($this->cache->has('CAMELCASE'));
    }

    public function testSpecialCharactersInKeys(): void
    {
        $specialKeys = [
            'key-with-hyphens',
            'key_with_underscores',
            'key.with.dots',
            'key:with:colons',
            'key|with|pipes',
            'key with spaces'
        ];
        
        foreach ($specialKeys as $key) {
            $value = "value_for_{$key}";
            $this->cache->set($key, $value);
            $this->assertEquals($value, $this->cache->get($key));
            $this->assertTrue($this->cache->has($key));
        }
    }

    public function testConcurrentOperations(): void
    {
        $operations = [];
        
        for ($i = 0; $i < 100; $i++) {
            $key = "concurrent_key_{$i}";
            $value = "concurrent_value_{$i}";
            $operations[$key] = $value;
        }
        
        $this->cache->setMultiple($operations);
        
        $results = $this->cache->getMultiple(array_keys($operations));
        
        $this->assertEquals($operations, $results);
        
        $keysToDelete = array_slice(array_keys($operations), 0, 50);
        $this->cache->deleteMultiple($keysToDelete);
        
        foreach ($keysToDelete as $key) {
            $this->assertFalse($this->cache->has($key));
        }
        
        $remainingKeys = array_slice(array_keys($operations), 50);
        foreach ($remainingKeys as $key) {
            $this->assertTrue($this->cache->has($key));
        }
    }

    public function testEmptyStringKey(): void
    {
        $this->cache->set('', 'empty_key_value');
        
        $this->assertEquals('empty_key_value', $this->cache->get(''));
        $this->assertTrue($this->cache->has(''));
    }

    public function testNumericStringKeys(): void
    {
        $this->cache->set('123', 'numeric_string_value');
        $this->cache->set('0', 'zero_value');
        $this->cache->set('-1', 'negative_value');
        
        $this->assertEquals('numeric_string_value', $this->cache->get('123'));
        $this->assertEquals('zero_value', $this->cache->get('0'));
        $this->assertEquals('negative_value', $this->cache->get('-1'));
    }

    public function testLargeData(): void
    {
        $largeString = str_repeat('A', 10000);
        $largeArray = array_fill(0, 1000, 'data');
        
        $this->cache->set('large_string', $largeString);
        $this->cache->set('large_array', $largeArray);
        
        $this->assertEquals($largeString, $this->cache->get('large_string'));
        $this->assertEquals($largeArray, $this->cache->get('large_array'));
    }
}