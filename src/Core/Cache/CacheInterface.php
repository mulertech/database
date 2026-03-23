<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

/**
 * @author Sébastien Muler
 */
interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): void;

    public function delete(string $key): void;

    public function clear(): void;

    public function has(string $key): bool;

    /**
     * @param array<string> $keys
     *
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array;

    /**
     * @param array<string, mixed> $values
     */
    public function setMultiple(array $values, int $ttl = 0): void;

    /**
     * @param array<string> $keys
     */
    public function deleteMultiple(array $keys): void;
}
