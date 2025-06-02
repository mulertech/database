<?php

namespace MulerTech\Database\Query;

use MulerTech\Database\Cache\QueryCache;

/**
 * Query compiler with caching and optimization capabilities
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class QueryCompiler
{
    /**
     * @var QueryCache
     */
    private readonly QueryCache $cache;

    /**
     * @var bool
     */
    private readonly bool $enableCache;

    /**
     * @var array<string, int>
     */
    private array $compilationStats = [];

    /**
     * @param QueryCache $cache
     * @param bool $enableCache
     */
    public function __construct(QueryCache $cache, bool $enableCache = true)
    {
        $this->cache = $cache;
        $this->enableCache = $enableCache;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    public function compile(AbstractQueryBuilder $builder): string
    {
        $queryType = $builder->getQueryType();
        $this->incrementStat($queryType);

        if (!$this->enableCache) {
            return $this->doCompile($builder);
        }

        $cacheKey = $this->generateCacheKey($builder);

        $cachedSql = $this->cache->get($cacheKey);
        if ($cachedSql !== null) {
            $this->incrementStat($queryType . '_cached');
            return $cachedSql;
        }

        $sql = $this->doCompile($builder);
        $this->cache->set($cacheKey, $sql);

        return $sql;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    public function compileWithoutCache(AbstractQueryBuilder $builder): string
    {
        return $this->doCompile($builder);
    }

    /**
     * @param string $queryType
     * @return void
     */
    public function invalidateCache(string $queryType = ''): void
    {
        if ($queryType === '') {
            $this->cache->clear();
        } else {
            $this->cache->clearByPattern($queryType . '_*');
        }
    }

    /**
     * @return array<string, int>
     */
    public function getCompilationStats(): array
    {
        return $this->compilationStats;
    }

    /**
     * @return void
     */
    public function resetStats(): void
    {
        $this->compilationStats = [];
    }

    /**
     * @param string $queryType
     * @return float
     */
    public function getCacheHitRate(string $queryType): float
    {
        $total = $this->compilationStats[$queryType] ?? 0;
        $cached = $this->compilationStats[$queryType . '_cached'] ?? 0;

        if ($total === 0) {
            return 0.0;
        }

        return ($cached / $total) * 100;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function doCompile(AbstractQueryBuilder $builder): string
    {
        $sql = $builder->toSql();

        // Apply optimizations
        $sql = $this->optimizeQuery($sql, $builder);

        // Validate SQL
        $this->validateSql($sql);

        return $sql;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function generateCacheKey(AbstractQueryBuilder $builder): string
    {
        // Create a hash based on query structure, not parameter values
        $structure = $this->extractQueryStructure($builder);
        return md5(serialize($structure));
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return array<string, mixed>
     */
    private function extractQueryStructure(AbstractQueryBuilder $builder): array
    {
        $reflection = new \ReflectionClass($builder);
        $structure = [
            'type' => $builder->getQueryType(),
            'class' => get_class($builder)
        ];

        // Extract only structural properties, not parameter values
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PRIVATE) as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($builder);

            // Skip parameter arrays as they contain values, not structure
            if (in_array($property->getName(), ['namedParameters', 'dynamicParameters'], true)) {
                continue;
            }

            $structure[$property->getName()] = $this->normalizeStructureValue($value);
        }

        return $structure;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeStructureValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'normalizeStructureValue'], $value);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return get_class($value);
        }

        return $value;
    }

    /**
     * @param string $sql
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function optimizeQuery(string $sql, AbstractQueryBuilder $builder): string
    {
        // Remove unnecessary whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        if (!is_string($sql) || $sql === '') {
            return '';
        }

        // Apply specific optimizations based on query type
        return match ($builder->getQueryType()) {
            'SELECT' => $this->optimizeSelectQuery($sql, $builder),
            'INSERT' => $this->optimizeInsertQuery($sql, $builder),
            'UPDATE' => $this->optimizeUpdateQuery($sql, $builder),
            'DELETE' => $this->optimizeDeleteQuery($sql, $builder),
            default => $sql
        };
    }

    /**
     * @param string $sql
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function optimizeSelectQuery(string $sql, AbstractQueryBuilder $builder): string
    {
        // Add query hints for large datasets
        if ($builder instanceof SelectBuilder && $this->shouldAddQueryHints($builder)) {
            $sql = str_replace('SELECT', 'SELECT /*+ USE_INDEX */', $sql);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function optimizeInsertQuery(string $sql, AbstractQueryBuilder $builder): string
    {
        // Add INSERT optimization hints for batch operations
        if ($builder instanceof InsertBuilder && $builder->isBatchInsert()) {
            if ($builder->getBatchSize() > 1000) {
                $sql = str_replace('INSERT', 'INSERT /*+ DELAYED */', $sql);
            }
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function optimizeUpdateQuery(string $sql, AbstractQueryBuilder $builder): string
    {
        // Add optimization hints for UPDATE with JOINs
        if ($builder instanceof UpdateBuilder && $builder->hasJoins()) {
            $sql = str_replace('UPDATE', 'UPDATE /*+ USE_NL */', $sql);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function optimizeDeleteQuery(string $sql, AbstractQueryBuilder $builder): string
    {
        // Add optimization hints for multi-table deletes
        if ($builder instanceof DeleteBuilder && $builder->isMultiTable()) {
            $sql = str_replace('DELETE', 'DELETE /*+ USE_INDEX */', $sql);
        }

        return $sql;
    }

    /**
     * @param SelectBuilder $builder
     * @return bool
     */
    private function shouldAddQueryHints(SelectBuilder $builder): bool
    {
        // Add hints for queries that might benefit from them
        // This is a simplified heuristic - in practice, you'd want more sophisticated logic
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('joins');
        $property->setAccessible(true);
        $joins = $property->getValue($builder);

        return !empty($joins);
    }

    /**
     * @param string $sql
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateSql(string $sql): void
    {
        // Basic SQL validation
        if (empty(trim($sql))) {
            throw new \InvalidArgumentException('Generated SQL cannot be empty');
        }

        // Check for dangerous patterns
        $dangerousPatterns = [
            '/;\s*DROP\s+/i',
            '/;\s*DELETE\s+FROM\s+\w+\s*$/i',
            '/;\s*TRUNCATE\s+/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                throw new \InvalidArgumentException('Potentially dangerous SQL pattern detected');
            }
        }
    }

    /**
     * @param string $statType
     * @return void
     */
    private function incrementStat(string $statType): void
    {
        $this->compilationStats[$statType] = ($this->compilationStats[$statType] ?? 0) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCacheStats(): array
    {
        return [
            'enabled' => $this->enableCache,
            'hit_rates' => [
                'SELECT' => $this->getCacheHitRate('SELECT'),
                'INSERT' => $this->getCacheHitRate('INSERT'),
                'UPDATE' => $this->getCacheHitRate('UPDATE'),
                'DELETE' => $this->getCacheHitRate('DELETE'),
            ],
            'compilation_stats' => $this->compilationStats,
            'cache_size' => $this->cache->size(),
        ];
    }
}
