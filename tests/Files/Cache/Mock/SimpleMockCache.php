<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache\Mock;

use MulerTech\Database\Core\Cache\CacheInterface;

/**
 * Simple cache without tagging support
 */
class SimpleMockCache implements CacheInterface
{
    private array $cache = [];

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
    }

    public function clear(): void
    {
        $this->cache = [];
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
}