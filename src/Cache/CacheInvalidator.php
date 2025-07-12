<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Intelligent invalidation manager
 * @package MulerTech\Database
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
