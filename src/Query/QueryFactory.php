<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\Cache\CacheFactory;
use MulerTech\Database\ORM\EmEngine;

/**
 * Factory for creating optimized query builders with caching support
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class QueryFactory
{
    /**
     * @var EmEngine|null
     */
    private readonly ?EmEngine $emEngine;

    /**
     * @var QueryCompiler
     */
    private readonly QueryCompiler $compiler;

    /**
     * @var QueryOptimizer
     */
    private readonly QueryOptimizer $optimizer;

    /**
     * @var bool
     */
    private readonly bool $enableOptimizations;

    /**
     * @var array<string, int>
     */
    private array $creationStats = [];

    /**
     * @param EmEngine|null $emEngine
     * @param QueryCompiler $compiler
     * @param QueryOptimizer $optimizer
     * @param bool $enableOptimizations
     */
    public function __construct(
        ?EmEngine $emEngine = null,
        ?QueryCompiler $compiler = null,
        ?QueryOptimizer $optimizer = null,
        bool $enableOptimizations = true
    ) {
        $this->emEngine = $emEngine;
        $this->compiler = $compiler ?? $this->createDefaultCompiler();
        $this->optimizer = $optimizer ?? new QueryOptimizer($enableOptimizations);
        $this->enableOptimizations = $enableOptimizations;
    }

    /**
     * @param string ...$columns
     * @return SelectBuilder
     */
    public function select(string ...$columns): SelectBuilder
    {
        $builder = new SelectBuilder($this->emEngine);

        if (!empty($columns)) {
            $builder->select(...$columns);
        }

        $this->incrementCreationStat('SELECT');
        return $this->applyOptimizations($builder);
    }

    /**
     * @param string $table
     * @return InsertBuilder
     */
    public function insert(string $table): InsertBuilder
    {
        $builder = new InsertBuilder($this->emEngine);
        $builder->into($table);

        $this->incrementCreationStat('INSERT');
        return $this->applyOptimizations($builder);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return UpdateBuilder
     */
    public function update(string $table, ?string $alias = null): UpdateBuilder
    {
        $builder = new UpdateBuilder($this->emEngine);
        $builder->table($table, $alias);

        $this->incrementCreationStat('UPDATE');
        return $this->applyOptimizations($builder);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return DeleteBuilder
     */
    public function delete(string $table, ?string $alias = null): DeleteBuilder
    {
        $builder = new DeleteBuilder($this->emEngine);
        $builder->from($table, $alias);

        $this->incrementCreationStat('DELETE');
        return $this->applyOptimizations($builder);
    }

    /**
     * @param string $sql
     * @return RawQueryBuilder
     */
    public function raw(string $sql): RawQueryBuilder
    {
        $builder = new RawQueryBuilder($this->emEngine, $sql);

        $this->incrementCreationStat('RAW');
        return $builder;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return string
     */
    public function compile(AbstractQueryBuilder $builder): string
    {
        return $this->compiler->compile($builder);
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return AbstractQueryBuilder
     */
    public function optimize(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        if (!$this->enableOptimizations) {
            return $builder;
        }

        return $this->optimizer->optimize($builder);
    }

    /**
     * @return array<string, int>
     */
    public function getCreationStats(): array
    {
        return $this->creationStats;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompilerStats(): array
    {
        return $this->compiler->getCacheStats();
    }

    /**
     * @return array<string, int>
     */
    public function getOptimizerStats(): array
    {
        return $this->optimizer->getOptimizationStats();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllStats(): array
    {
        return [
            'creation' => $this->getCreationStats(),
            'compilation' => $this->getCompilerStats(),
            'optimization' => $this->getOptimizerStats(),
            'total_queries_created' => array_sum($this->creationStats),
        ];
    }

    /**
     * @return void
     */
    public function resetStats(): void
    {
        $this->creationStats = [];
        $this->compiler->resetStats();
        $this->optimizer->resetStats();
    }

    /**
     * @param string $queryType
     * @return void
     */
    public function invalidateCache(string $queryType = ''): void
    {
        $this->compiler->invalidateCache($queryType);
    }

    /**
     * @param EmEngine $emEngine
     * @return self
     */
    public function withEmEngine(EmEngine $emEngine): self
    {
        return new self($emEngine, $this->compiler, $this->optimizer, $this->enableOptimizations);
    }

    /**
     * @param bool $enableOptimizations
     * @return self
     */
    public function withOptimizations(bool $enableOptimizations): self
    {
        return new self($this->emEngine, $this->compiler, $this->optimizer, $enableOptimizations);
    }

    /**
     * @param QueryCompiler $compiler
     * @return self
     */
    public function withCompiler(QueryCompiler $compiler): self
    {
        return new self($this->emEngine, $compiler, $this->optimizer, $this->enableOptimizations);
    }

    /**
     * @param QueryOptimizer $optimizer
     * @return self
     */
    public function withOptimizer(QueryOptimizer $optimizer): self
    {
        return new self($this->emEngine, $this->compiler, $optimizer, $this->enableOptimizations);
    }

    /**
     * @template T of AbstractQueryBuilder
     * @param T $builder
     * @return T
     */
    private function applyOptimizations(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        if (!$this->enableOptimizations) {
            /** @var T $builder */
            return $builder;
        }

        /** @var T $optimized */
        $optimized = $this->optimizer->optimize($builder);
        return $optimized;
    }

    /**
     * @return QueryCompiler
     */
    private function createDefaultCompiler(): QueryCompiler
    {
        $cache = CacheFactory::createResultSetCache();
        return new QueryCompiler($cache);
    }

    /**
     * @param string $type
     * @return void
     */
    private function incrementCreationStat(string $type): void
    {
        $this->creationStats[$type] = ($this->creationStats[$type] ?? 0) + 1;
    }
}
