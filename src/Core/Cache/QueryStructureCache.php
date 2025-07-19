<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use DateTimeInterface;
use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use ReflectionClass;

/**
 * Class QueryStructureCache
 *
 * Cache for compiled query structures to avoid recompilation
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryStructureCache
{
    /**
     * @var array<string, array{sql: string, structure: array<string, mixed>, timestamp: int}>
     */
    private array $cache = [];

    /**
     * @var array<string, string>
     */
    private array $structureHashes = [];

    /**
     * @var int
     */
    private int $maxCacheSize;

    /**
     * @var int
     */
    private int $ttl;

    /**
     * @var bool
     */
    private bool $enabled = true;

    /**
     * @param int $maxCacheSize
     * @param int $ttl Time to live in seconds
     */
    public function __construct(int $maxCacheSize = 1000, int $ttl = 3600)
    {
        $this->maxCacheSize = $maxCacheSize;
        $this->ttl = $ttl;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string|null
     */
    public function get(AbstractQueryBuilder $builder): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $key = $this->generateKey($builder);

        if (!isset($this->cache[$key])) {
            return null;
        }

        $entry = $this->cache[$key];

        // Check TTL
        if (time() - $entry['timestamp'] > $this->ttl) {
            unset($this->cache[$key]);
            return null;
        }

        // Move to end (LRU)
        unset($this->cache[$key]);
        $this->cache[$key] = $entry;

        return $entry['sql'];
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @param string $sql
     * @return void
     */
    public function set(AbstractQueryBuilder $builder, string $sql): void
    {
        if (!$this->enabled) {
            return;
        }

        $key = $this->generateKey($builder);

        // Remove oldest entry if cache is full
        if (count($this->cache) >= $this->maxCacheSize) {
            reset($this->cache);
            $oldestKey = key($this->cache);
            if ($oldestKey !== null) {
                unset($this->cache[$oldestKey]);
            }
        }

        $this->cache[$key] = [
            'sql' => $sql,
            'structure' => $this->extractStructure($builder),
            'timestamp' => time(),
        ];
    }

    /**
     * @param string|null $prefix
     * @return void
     */
    public function clear(?string $prefix = null): void
    {
        if ($prefix === null) {
            $this->cache = [];
            $this->structureHashes = [];
            return;
        }

        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        if (!$enabled) {
            $this->clear();
        }
    }

    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->cache);
    }

    /**
     * @return array{hits: int, misses: int, size: int, enabled: bool}
     */
    public function getStats(): array
    {
        $hits = 0;
        $misses = 0;

        // This is a simplified version - in production you'd track actual hits/misses
        return [
            'hits' => $hits,
            'misses' => $misses,
            'size' => $this->size(),
            'enabled' => $this->enabled,
        ];
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function generateKey(AbstractQueryBuilder $builder): string
    {
        $builderHash = spl_object_hash($builder);

        if (!isset($this->structureHashes[$builderHash])) {
            $structure = $this->extractStructure($builder);
            $this->structureHashes[$builderHash] = md5(serialize($structure));
        }

        return get_class($builder) . ':' . $this->structureHashes[$builderHash];
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return array<string, mixed>
     */
    private function extractStructure(AbstractQueryBuilder $builder): array
    {
        $reflection = new ReflectionClass($builder);
        $structure = [
            'type' => $builder->getQueryType(),
            'class' => get_class($builder),
        ];

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($builder);

            // Skip runtime values
            if (in_array($property->getName(), ['namedParameters', 'dynamicParameters', 'parameterCounter'], true)) {
                continue;
            }

            // Extract structural information
            if (is_array($value)) {
                $structure[$property->getName()] = $this->extractArrayStructure($value);
            } elseif (is_object($value) && !$value instanceof DateTimeInterface) {
                $structure[$property->getName()] = get_class($value);
            } elseif (!is_resource($value)) {
                $structure[$property->getName()] = $value;
            }
        }

        return $structure;
    }

    /**
     * @param array<mixed, mixed> $array
     * @return array<mixed, mixed>
     */
    private function extractArrayStructure(array $array): array
    {
        $structure = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $structure[$key] = $this->extractArrayStructure($value);
            } elseif (is_object($value)) {
                $structure[$key] = get_class($value);
            } elseif (!is_resource($value)) {
                $structure[$key] = gettype($value);
            }
        }

        return $structure;
    }

    /**
     * @return void
     */
    public function purgeExpired(): void
    {
        $now = time();

        foreach ($this->cache as $key => $entry) {
            if ($now - $entry['timestamp'] > $this->ttl) {
                unset($this->cache[$key]);
            }
        }
    }
}
