<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Gestionnaire d'invalidation intelligent
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
class CacheInvalidator
{
    /**
     * @var array<string, TaggableCacheInterface>
     */
    private array $caches = [];

    /**
     * @var array<string, array<string>>
     */
    private array $dependencies = [];

    /**
     * @var array<string, callable>
     */
    private array $invalidationPatterns = [];

    /**
     * @param string $name
     * @param TaggableCacheInterface $cache
     * @return void
     */
    public function registerCache(string $name, TaggableCacheInterface $cache): void
    {
        $this->caches[$name] = $cache;
    }

    /**
     * @param string $entity
     * @param array<string> $dependencies
     * @return void
     */
    public function registerDependency(string $entity, array $dependencies): void
    {
        $this->dependencies[$entity] = array_merge(
            $this->dependencies[$entity] ?? [],
            $dependencies
        );
    }

    /**
     * @param string $pattern
     * @param callable $callback
     * @return void
     */
    public function registerPattern(string $pattern, callable $callback): void
    {
        $this->invalidationPatterns[$pattern] = $callback;
    }

    /**
     * @param string $entity
     * @param string $operation
     * @param array<string, mixed> $context
     * @return void
     */
    public function invalidate(string $entity, string $operation, array $context = []): void
    {
        // Direct invalidation
        $this->invalidateEntity($entity);

        // Dependency invalidation
        $this->invalidateDependencies($entity);

        // Pattern-based invalidation
        $this->applyPatterns($entity, $operation, $context);
    }

    /**
     * @param string $table
     * @return void
     */
    public function invalidateTable(string $table): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateTag('table:' . $table);
        }
    }

    /**
     * @param array<string> $tables
     * @return void
     */
    public function invalidateTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->invalidateTable($table);
        }
    }

    /**
     * @param array<string> $tags
     * @return void
     */
    public function invalidateTags(array $tags): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateTags($tags);
        }
    }

    /**
     * @param string $tag
     * @return void
     */
    public function invalidateTag(string $tag): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateTag($tag);
        }
    }

    /**
     * @param string $entity
     * @return void
     */
    private function invalidateEntity(string $entity): void
    {
        foreach ($this->caches as $cache) {
            $cache->invalidateTag($entity);
        }
    }

    /**
     * @param string $entity
     * @return void
     */
    private function invalidateDependencies(string $entity): void
    {
        if (!isset($this->dependencies[$entity])) {
            return;
        }

        foreach ($this->dependencies[$entity] as $dependency) {
            $this->invalidate($dependency, 'dependency');
        }
    }

    /**
     * @param string $entity
     * @param string $operation
     * @param array<string, mixed> $context
     * @return void
     */
    private function applyPatterns(string $entity, string $operation, array $context): void
    {
        foreach ($this->invalidationPatterns as $pattern => $callback) {
            if ($this->matchesPattern($entity, $pattern)) {
                $callback($entity, $operation, $context, $this);
            }
        }
    }

    /**
     * @param string $entity
     * @param string $pattern
     * @return bool
     */
    private function matchesPattern(string $entity, string $pattern): bool
    {
        // Convert pattern to regex (simple wildcards)
        $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/';

        return preg_match($regex, $entity) === 1;
    }
}
