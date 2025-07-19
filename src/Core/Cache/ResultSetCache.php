<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

/**
 * Class ResultSetCache
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class ResultSetCache implements TaggableCacheInterface
{
    /**
     * @var CacheInterface
     */
    private CacheInterface $cache;

    /**
     * @var int
     */
    private int $compressionThreshold;

    /**
     * @param CacheInterface $cache
     * @param int $compressionThreshold
     */
    public function __construct(
        CacheInterface $cache,
        int $compressionThreshold = 1024
    ) {
        $this->cache = $cache;
        $this->compressionThreshold = $compressionThreshold;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $data = $this->cache->get($key);

        if ($data === null) {
            return null;
        }

        // Validate data structure before decompressing
        if (!is_array($data) || !isset($data['compressed'], $data['data']) ||
            !is_bool($data['compressed']) || !is_string($data['data'])) {
            return null;
        }

        /** @var array{compressed: bool, data: string} $data */
        return $this->decompress($data);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $compressed = $this->compress($value);
        $this->cache->set($key, $compressed, $ttl);
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->cache->clear();
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array
    {
        $compressed = $this->cache->getMultiple($keys);

        return array_map(function ($data) {
            if ($data === null) {
                return null;
            }

            // Validate data structure before decompressing
            if (!is_array($data) || !isset($data['compressed'], $data['data']) ||
                !is_bool($data['compressed']) || !is_string($data['data'])) {
                return null;
            }

            /** @var array{compressed: bool, data: string} $data */
            return $this->decompress($data);
        }, $compressed);
    }

    /**
     * @param array<string, mixed> $values
     * @param int $ttl
     * @return void
     */
    public function setMultiple(array $values, int $ttl = 0): void
    {
        $compressed = array_map(function ($value) {
            return $this->compress($value);
        }, $values);

        $this->cache->setMultiple($compressed, $ttl);
    }

    /**
     * @param array<string> $keys
     * @return void
     */
    public function deleteMultiple(array $keys): void
    {
        $this->cache->deleteMultiple($keys);
    }

    /**
     * @param string $key
     * @param array<string> $tags
     * @return void
     */
    public function tag(string $key, array $tags): void
    {
        if ($this->cache instanceof TaggableCacheInterface) {
            $this->cache->tag($key, $tags);
        }
    }

    /**
     * @param string $tag
     * @return void
     */
    public function invalidateTag(string $tag): void
    {
        if ($this->cache instanceof TaggableCacheInterface) {
            $this->cache->invalidateTag($tag);
        }
    }

    /**
     * @param array<string> $tags
     * @return void
     */
    public function invalidateTags(array $tags): void
    {
        if ($this->cache instanceof TaggableCacheInterface) {
            $this->cache->invalidateTags($tags);
        }
    }

    /**
     * @param string $table
     * @return void
     */
    public function invalidateTable(string $table): void
    {
        $this->invalidateTag('table:' . $table);
    }

    /**
     * @param array<string> $tables
     * @return void
     */
    public function invalidateTables(array $tables): void
    {
        $this->invalidateTags(array_map(
            static fn (string $table) => 'table:' . $table,
            $tables
        ));
    }

    /**
     * @param mixed $data
     * @return array{compressed: bool, data: string}
     */
    private function compress(mixed $data): array
    {
        $serialized = serialize($data);

        if (strlen($serialized) < $this->compressionThreshold) {
            return ['compressed' => false, 'data' => $serialized];
        }

        $compressed = gzcompress($serialized, 9);

        // Handle potential compression failure
        if ($compressed === false) {
            return ['compressed' => false, 'data' => $serialized];
        }

        // Only use compression if it actually reduces size
        if (strlen($compressed) < strlen($serialized)) {
            return ['compressed' => true, 'data' => $compressed];
        }

        return ['compressed' => false, 'data' => $serialized];
    }

    /**
     * @param array{compressed: bool, data: string} $data
     * @return mixed
     */
    private function decompress(array $data): mixed
    {
        if ($data['compressed']) {
            $decompressed = gzuncompress($data['data']);
            $serialized = $decompressed !== false ? $decompressed : $data['data'];
        } else {
            $serialized = $data['data'];
        }

        return unserialize($serialized, ['allowed_classes' => false]) ?: null;
    }
}
