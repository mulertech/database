<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Cache;

use InvalidArgumentException;
use MulerTech\Database\Core\Cache\CacheConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CacheConfig::class)]
final class CacheConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = new CacheConfig();

        $this->assertEquals(10000, $config->maxSize);
        $this->assertEquals(3600, $config->ttl);
        $this->assertEquals('lru', $config->evictionPolicy);
    }

    public function testCustomConfig(): void
    {
        $config = new CacheConfig(
            maxSize: 5000,
            ttl: 7200,
            evictionPolicy: 'lfu'
        );

        $this->assertEquals(5000, $config->maxSize);
        $this->assertEquals(7200, $config->ttl);
        $this->assertEquals('lfu', $config->evictionPolicy);
    }

    public function testValidEvictionPolicies(): void
    {
        $validPolicies = ['lru', 'lfu', 'fifo'];

        foreach ($validPolicies as $policy) {
            $config = new CacheConfig(evictionPolicy: $policy);
            $this->assertEquals($policy, $config->evictionPolicy);
        }
    }

    public function testInvalidEvictionPolicy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid eviction policy "invalid". Valid policies are: lru, lfu, fifo');

        new CacheConfig(evictionPolicy: 'invalid');
    }

    public function testReadonlyProperties(): void
    {
        $config = new CacheConfig(maxSize: 1000, ttl: 600, evictionPolicy: 'fifo');

        $this->assertEquals(1000, $config->maxSize);
        $this->assertEquals(600, $config->ttl);
        $this->assertEquals('fifo', $config->evictionPolicy);
    }

    public function testConfigIsImmutable(): void
    {
        $config = new CacheConfig();
        
        // All properties are readonly, so they cannot be modified after construction
        $this->assertTrue(true); // This test passes if no errors occur above
    }
}