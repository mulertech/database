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
    private array $ttl = [];

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }
        
        if ($this->isExpired($key)) {
            $this->delete($key);
            return null;
        }
        
        return $this->cache[$key];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->cache[$key] = $value;
        $this->ttl[$key] = $ttl > 0 ? time() + $ttl : 0;
    }

    public function delete(string $key): void
    {
        unset($this->cache[$key], $this->ttl[$key]);
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->ttl = [];
    }

    public function has(string $key): bool
    {
        return isset($this->cache[$key]) && !$this->isExpired($key);
    }

    private function isExpired(string $key): bool
    {
        if (!isset($this->ttl[$key]) || $this->ttl[$key] === 0) {
            return false;
        }
        
        return time() > $this->ttl[$key];
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