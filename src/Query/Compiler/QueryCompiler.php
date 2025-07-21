<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Compiler;

use InvalidArgumentException;
use MulerTech\Database\Core\Cache\CacheFactory;
use MulerTech\Database\Core\Cache\ResultSetCache;
use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use MulerTech\Database\Query\Builder\DeleteBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Builder\UpdateBuilder;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class QueryCompiler
 *
 * Query compiler with unified caching system
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryCompiler
{
    /**
     * @var ResultSetCache
     */
    private readonly ResultSetCache $cache;

    /**
     * @var bool
     */
    private readonly bool $enableCache;

    /**
     * @var array<string, int>
     */
    private array $compilationStats = [];

    /**
     * @var array<string, string>
     */
    private array $queryStructureCache = [];

    /**
     * @param ResultSetCache|null $cache
     * @param CacheFactory|null $cacheFactory
     * @param bool $enableCache
     */
    public function __construct(?ResultSetCache $cache = null, private readonly ?CacheFactory $cacheFactory = null, bool $enableCache = true)
    {
        $factory = $this->cacheFactory ?? new CacheFactory();
        $this->cache = $cache ?? $factory::createResultSetCache('query_compiler');
        $this->enableCache = $enableCache;
    }

    /**
     * Create a QueryCompiler with caching enabled
     */
    public static function withCache(?ResultSetCache $cache = null, ?CacheFactory $cacheFactory = null): self
    {
        return new self($cache, $cacheFactory, true);
    }

    /**
     * Create a QueryCompiler with caching disabled
     */
    public static function withoutCache(): self
    {
        return new self(null, null, false);
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    public function compile(AbstractQueryBuilder $builder): string
    {
        $queryType = $builder->getQueryType();
        $this->incrementStat($queryType . '_requests');

        $cacheKey = $this->generateCacheKey($builder);

        // Try to get from cache
        $cachedSql = $this->cache->get($cacheKey);
        if (is_string($cachedSql)) {
            $this->incrementStat($queryType . '_cached');
            return $cachedSql;
        }

        // Compile and cache
        $sql = $this->doCompile($builder);

        // Extract tables for cache invalidation
        $tables = $this->extractTables($builder);

        // Store in cache with table tags
        $this->cache->set($cacheKey, $sql);
        $this->cache->tag($cacheKey, array_map(static fn ($table) => 'table:' . $table, $tables));

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
            // Invalidate by query type tag
            $this->cache->invalidateTag('query_type:' . $queryType);
        }
    }

    /**
     * @param string $table
     * @return void
     */
    public function invalidateTable(string $table): void
    {
        $this->cache->invalidateTable($table);
    }

    /**
     * @param array<string> $tables
     * @return void
     */
    public function invalidateTables(array $tables): void
    {
        $this->cache->invalidateTables($tables);
    }

    /**
     * @return array<string, mixed>
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
        ];
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

        // Tag with query type for grouped invalidation
        $queryType = $builder->getQueryType();
        $cacheKey = $this->generateCacheKey($builder);
        $this->cache->tag($cacheKey, ['query_type:' . $queryType]);

        return $sql;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function generateCacheKey(AbstractQueryBuilder $builder): string
    {
        // Get or create query structure hash
        $builderHash = spl_object_hash($builder);

        if (!isset($this->queryStructureCache[$builderHash])) {
            $structure = $this->extractQueryStructure($builder);
            $this->queryStructureCache[$builderHash] = md5(serialize($structure));
        }

        return 'compiled_query:' . $this->queryStructureCache[$builderHash];
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return array<string, mixed>
     */
    private function extractQueryStructure(AbstractQueryBuilder $builder): array
    {
        $reflection = new ReflectionClass($builder);
        $structure = [
            'type' => $builder->getQueryType(),
            'class' => get_class($builder),
        ];

        // Extract only structural properties, not parameter values
        foreach ($reflection->getProperties(ReflectionProperty::IS_PRIVATE) as $property) {
            $value = $property->getValue($builder);

            // Skip parameter arrays as they contain values, not structure
            if (in_array($property->getName(), ['namedParameters', 'dynamicParameters'], true)) {
                continue;
            }

            // For arrays, only store structure indicators
            if (is_array($value)) {
                $structure[$property->getName()] = array_keys($value);
            } elseif (is_object($value)) {
                $structure[$property->getName()] = get_class($value);
            } elseif (!is_resource($value)) {
                // Store scalar values that affect structure
                $structure[$property->getName()] = $value;
            }
        }

        return $structure;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return array<string>
     */
    private function extractTables(AbstractQueryBuilder $builder): array
    {
        $tables = [];
        $sql = $builder->toSql();

        // Extract table names from SQL
        $patterns = [
            '/FROM\s+`?(\w+)`?/i',
            '/JOIN\s+`?(\w+)`?/i',
            '/INTO\s+`?(\w+)`?/i',
            '/UPDATE\s+`?(\w+)`?/i',
            '/DELETE\s+FROM\s+`?(\w+)`?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches)) {
                array_push($tables, ...$matches[1]);
            }
        }

        return array_unique(array_map('strtolower', $tables));
    }

    /**
     * @param string $sql
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    private function optimizeQuery(string $sql, AbstractQueryBuilder $builder): string
    {
        // Apply query-specific optimizations
        if ($builder instanceof SelectBuilder) {
            $sql = $this->optimizeSelectQuery($sql, $builder);
        } elseif ($builder instanceof UpdateBuilder) {
            $sql = $this->optimizeUpdateQuery($sql, $builder);
        } elseif ($builder instanceof DeleteBuilder) {
            $sql = $this->optimizeDeleteQuery($sql, $builder);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param SelectBuilder $builder
     * @return string
     */
    private function optimizeSelectQuery(string $sql, SelectBuilder $builder): string
    {
        // Add query hints for complex joins
        if ($this->shouldAddQueryHints($builder)) {
            $sql = str_replace('SELECT', 'SELECT /*+ USE_INDEX */', $sql);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param UpdateBuilder $builder
     * @return string
     */
    private function optimizeUpdateQuery(string $sql, UpdateBuilder $builder): string
    {
        // Add low priority for bulk updates
        if ($this->isBulkUpdate($builder)) {
            $sql = str_replace('UPDATE', 'UPDATE LOW_PRIORITY', $sql);
        }

        return $sql;
    }

    /**
     * @param string $sql
     * @param DeleteBuilder $builder
     * @return string
     */
    private function optimizeDeleteQuery(string $sql, DeleteBuilder $builder): string
    {
        // Add quick modifier for simple deletes
        if ($this->isSimpleDelete($builder)) {
            $sql = str_replace('DELETE', 'DELETE QUICK', $sql);
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
        $reflection = new ReflectionClass($builder);
        $property = $reflection->getProperty('joins');
        $joins = $property->getValue($builder);

        return is_array($joins) && count($joins) > 2;
    }

    /**
     * @param UpdateBuilder $builder
     * @return bool
     */
    private function isBulkUpdate(UpdateBuilder $builder): bool
    {
        $reflection = new ReflectionClass($builder);
        $property = $reflection->getProperty('where');
        $where = $property->getValue($builder);

        // If no where clause or very simple where, consider it bulk
        return !is_array($where) || empty($where) || count($where) <= 1;
    }

    /**
     * @param DeleteBuilder $builder
     * @return bool
     */
    private function isSimpleDelete(DeleteBuilder $builder): bool
    {
        $reflection = new ReflectionClass($builder);
        $property = $reflection->getProperty('where');
        $where = $property->getValue($builder);

        // Simple delete has a single WHERE condition
        return is_array($where) && count($where) === 1;
    }

    /**
     * @param string $sql
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateSql(string $sql): void
    {
        // Basic SQL validation
        if (empty(trim($sql))) {
            throw new InvalidArgumentException('Generated SQL cannot be empty');
        }

        // Check for dangerous patterns
        $dangerousPatterns = [
            '/;\s*DROP\s+/i',
            '/;\s*DELETE\s+FROM\s+\w+\s*$/i',
            '/;\s*TRUNCATE\s+/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                throw new InvalidArgumentException('Potentially dangerous SQL pattern detected');
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
}
