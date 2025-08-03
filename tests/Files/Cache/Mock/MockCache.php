<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache\Mock;

use MulerTech\Database\Core\Cache\TaggableCacheInterface;

/**
 * Mock cache implementation for testing
 */
class MockCache implements TaggableCacheInterface
{
    private array $cache = [];
    private array $tags = [];

    public function get(string $key): mixed
    {
        return $this->cache[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->cache[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->cache[$key]);
        
        // Remove from tags
        foreach ($this->tags as $tag => $keys) {
            unset($this->tags[$tag][$key]);
            if (empty($this->tags[$tag])) {
                unset($this->tags[$tag]);
            }
        }
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->tags = [];
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]);
    }

    public function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 0): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    public function deleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function tag(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $this->tags[$tag][$key] = true;
        }
    }

    public function invalidateTag(string $tag): void
    {
        if (isset($this->tags[$tag])) {
            $keys = array_keys($this->tags[$tag]);
            $this->deleteMultiple($keys);
        }
    }

    public function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->invalidateTag($tag);
        }
    }

    public function getStoredData(): array
    {
        return $this->cache;
    }

    public function getStoredTags(): array
    {
        return $this->tags;
    }
}