<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

/**
 * Class ResultSetCache.
 *
 * @author Sébastien Muler
 */
readonly class ResultSetCache implements TaggableCacheInterface
{
    private CacheInterface $cache;

    private int $compressionThreshold;

    public function __construct(
        CacheInterface $cache,
        int $compressionThreshold = 1024,
    ) {
        $this->cache = $cache;
        $this->compressionThreshold = $compressionThreshold;
    }

    public function get(string $key): mixed
    {
        $data = $this->cache->get($key);

        if (null === $data) {
            return null;
        }

        // Validate data structure before decompressing
        if (!is_array($data) || !isset($data['compressed'], $data['data'])
            || !is_bool($data['compressed']) || !is_string($data['data'])) {
            return null;
        }

        /* @var array{compressed: bool, data: string} $data */
        return $this->decompress($data);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $compressed = $this->compress($value);
        $this->cache->set($key, $compressed, $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->delete($key);
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array
    {
        $compressed = $this->cache->getMultiple($keys);

        return array_map(function ($data) {
            if (null === $data) {
                return null;
            }

            // Validate data structure before decompressing
            if (!is_array($data) || !isset($data['compressed'], $data['data'])
                || !is_bool($data['compressed']) || !is_string($data['data'])) {
                return null;
            }

            /* @var array{compressed: bool, data: string} $data */
            return $this->decompress($data);
        }, $compressed);
    }

    /**
     * @param array<string, mixed> $values
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
     */
    public function deleteMultiple(array $keys): void
    {
        $this->cache->deleteMultiple($keys);
    }

    /**
     * @param array<string> $tags
     */
    public function tag(string $key, array $tags): void
    {
        if ($this->cache instanceof TaggableCacheInterface) {
            $this->cache->tag($key, $tags);
        }
    }

    public function invalidateTag(string $tag): void
    {
        if ($this->cache instanceof TaggableCacheInterface) {
            $this->cache->invalidateTag($tag);
        }
    }

    /**
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): void
    {
        if ($this->cache instanceof TaggableCacheInterface) {
            $this->cache->invalidateTags($tags);
        }
    }

    public function invalidateTable(string $table): void
    {
        $this->invalidateTag('table:'.$table);
    }

    /**
     * @param array<string> $tables
     */
    public function invalidateTables(array $tables): void
    {
        $this->invalidateTags(array_map(
            static fn (string $table) => 'table:'.$table,
            $tables
        ));
    }

    /**
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
        if (false === $compressed) {
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
     *
     * @return array<int|string, mixed>|bool|float|int|string|null
     */
    private function decompress(array $data): array|bool|float|int|string|null
    {
        $serialized = $this->handleCompression($data);
        if (null === $serialized) {
            return null;
        }

        return $this->deserializeAndValidate($serialized);
    }

    /**
     * @param array{compressed: bool, data: string} $data
     */
    private function handleCompression(array $data): ?string
    {
        $serialized = $data['data'];

        if (!$data['compressed']) {
            return $serialized;
        }

        try {
            $decompressed = @gzuncompress($serialized);

            return false === $decompressed ? null : $decompressed;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int|string, mixed>|bool|float|int|string|null
     */
    private function deserializeAndValidate(string $serialized): array|bool|float|int|string|null
    {
        try {
            $result = @unserialize($serialized, ['allowed_classes' => false]);

            if (false === $result && $serialized !== serialize(false)) {
                return null;
            }

            return $this->validateResultType($result);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int|string, mixed>|bool|float|int|string|null
     */
    private function validateResultType(mixed $result): array|bool|float|int|string|null
    {
        if (is_object($result)) {
            return null;
        }

        if (is_array($result) || is_bool($result) || is_float($result)
            || is_int($result) || is_string($result) || null === $result) {
            return $result;
        }

        return null;
    }
}
