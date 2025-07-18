<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface CacheInterface
{
    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     */
    public function set(string $key, mixed $value, int $ttl = 0): void;

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void;

    /**
     * @return void
     */
    public function clear(): void;

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * @param array<string> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(array $keys): array;

    /**
     * @param array<string, mixed> $values
     * @param int $ttl
     * @return void
     */
    public function setMultiple(array $values, int $ttl = 0): void;

    /**
     * @param array<string> $keys
     * @return void
     */
    public function deleteMultiple(array $keys): void;
}
