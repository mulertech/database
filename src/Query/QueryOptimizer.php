<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

/**
 * Automatic query optimization engine
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class QueryOptimizer
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $optimizationRules = [];

    /**
     * @var array<string, int>
     */
    private array $optimizationStats = [];

    /**
     * @var bool
     */
    private readonly bool $enableOptimizations;

    /**
     * @param bool $enableOptimizations
     */
    public function __construct(bool $enableOptimizations = true)
    {
        $this->enableOptimizations = $enableOptimizations;
        $this->initializeOptimizationRules();
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

        $optimizedBuilder = clone $builder;
        $queryType = $builder->getQueryType();

        foreach ($this->optimizationRules[$queryType] ?? [] as $ruleName => $rule) {
            if ($this->shouldApplyRule($rule, $optimizedBuilder)) {
                $optimizedBuilder = $this->applyOptimization($optimizedBuilder, $rule);
                $this->incrementOptimizationStat($ruleName);
            }
        }

        return $optimizedBuilder;
    }

    /**
     * @param SelectBuilder $builder
     * @return SelectBuilder
     */
    public function optimizeSelect(SelectBuilder $builder): SelectBuilder
    {
        $optimized = clone $builder;

        // Apply SELECT-specific optimizations
        $optimized = $this->optimizeSelectColumns($optimized);
        $optimized = $this->optimizeJoinOrder($optimized);
        $optimized = $this->optimizeWhereConditions($optimized);
        $optimized = $this->addSelectHints($optimized);

        return $optimized;
    }

    /**
     * @param InsertBuilder $builder
     * @return InsertBuilder
     */
    public function optimizeInsert(InsertBuilder $builder): InsertBuilder
    {
        $optimized = clone $builder;

        // Apply INSERT-specific optimizations
        if ($builder->isBatchInsert()) {
            $optimized = $this->optimizeBatchInsert($optimized);
        }

        return $optimized;
    }

    /**
     * @param UpdateBuilder $builder
     * @return UpdateBuilder
     */
    public function optimizeUpdate(UpdateBuilder $builder): UpdateBuilder
    {
        $optimized = clone $builder;

        // Apply UPDATE-specific optimizations
        $optimized = $this->optimizeUpdateConditions($optimized);

        if ($builder->hasJoins()) {
            $optimized = $this->optimizeUpdateJoins($optimized);
        }

        return $optimized;
    }

    /**
     * @param DeleteBuilder $builder
     * @return DeleteBuilder
     */
    public function optimizeDelete(DeleteBuilder $builder): DeleteBuilder
    {
        $optimized = clone $builder;

        // Apply DELETE-specific optimizations
        $optimized = $this->optimizeDeleteConditions($optimized);

        if ($builder->isMultiTable()) {
            $optimized = $this->optimizeMultiTableDelete($optimized);
        }

        return $optimized;
    }

    /**
     * @param string $ruleName
     * @param array<string, mixed> $ruleConfig
     * @return void
     */
    public function addOptimizationRule(string $ruleName, array $ruleConfig): void
    {
        $queryType = $ruleConfig['query_type'] ?? 'ALL';
        $this->optimizationRules[$queryType][$ruleName] = $ruleConfig;
    }

    /**
     * @param string $ruleName
     * @return void
     */
    public function removeOptimizationRule(string $ruleName): void
    {
        foreach ($this->optimizationRules as $queryType => $rules) {
            unset($this->optimizationRules[$queryType][$ruleName]);
        }
    }

    /**
     * @return array<string, int>
     */
    public function getOptimizationStats(): array
    {
        return $this->optimizationStats;
    }

    /**
     * @return void
     */
    public function resetStats(): void
    {
        $this->optimizationStats = [];
    }

    /**
     * @return void
     */
    private function initializeOptimizationRules(): void
    {
        // SELECT optimizations
        $this->optimizationRules['SELECT'] = [
            'avoid_select_star' => [
                'condition' => 'hasSelectStar',
                'action' => 'suggestSpecificColumns',
                'priority' => 1,
            ],
            'optimize_join_order' => [
                'condition' => 'hasMultipleJoins',
                'action' => 'reorderJoins',
                'priority' => 2,
            ],
            'add_limit_for_exists' => [
                'condition' => 'isExistsSubquery',
                'action' => 'addLimitOne',
                'priority' => 3,
            ],
        ];

        // INSERT optimizations
        $this->optimizationRules['INSERT'] = [
            'batch_small_inserts' => [
                'condition' => 'isSmallBatch',
                'action' => 'suggestBatching',
                'priority' => 1,
            ],
            'use_insert_ignore' => [
                'condition' => 'hasDuplicateKeyUpdate',
                'action' => 'considerInsertIgnore',
                'priority' => 2,
            ],
        ];

        // UPDATE optimizations
        $this->optimizationRules['UPDATE'] = [
            'limit_unsafe_updates' => [
                'condition' => 'hasNoWhereClause',
                'action' => 'addSafetyLimit',
                'priority' => 1,
            ],
            'optimize_update_joins' => [
                'condition' => 'hasJoinsWithoutIndex',
                'action' => 'suggestIndexes',
                'priority' => 2,
            ],
        ];

        // DELETE optimizations
        $this->optimizationRules['DELETE'] = [
            'limit_unsafe_deletes' => [
                'condition' => 'hasNoWhereClause',
                'action' => 'addSafetyLimit',
                'priority' => 1,
            ],
            'use_truncate_for_full_delete' => [
                'condition' => 'isFullTableDelete',
                'action' => 'suggestTruncate',
                'priority' => 2,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function shouldApplyRule(array $rule, AbstractQueryBuilder $builder): bool
    {
        $condition = $rule['condition'] ?? '';

        return match ($condition) {
            'hasSelectStar' => $this->hasSelectStar($builder),
            'hasMultipleJoins' => $this->hasMultipleJoins($builder),
            'isExistsSubquery' => $this->isExistsSubquery($builder),
            'isSmallBatch' => $this->isSmallBatch($builder),
            'hasDuplicateKeyUpdate' => $this->hasDuplicateKeyUpdate($builder),
            'hasNoWhereClause' => $this->hasNoWhereClause($builder),
            'hasJoinsWithoutIndex' => $this->hasJoinsWithoutIndex($builder),
            'isFullTableDelete' => $this->isFullTableDelete($builder),
            default => false
        };
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @param array<string, mixed> $rule
     * @return AbstractQueryBuilder
     */
    private function applyOptimization(AbstractQueryBuilder $builder, array $rule): AbstractQueryBuilder
    {
        $action = $rule['action'] ?? '';

        return match ($action) {
            'suggestSpecificColumns' => $this->suggestSpecificColumns($builder),
            'reorderJoins' => $this->reorderJoins($builder),
            'addLimitOne' => $this->addLimitOne($builder),
            'suggestBatching' => $this->suggestBatching($builder),
            'considerInsertIgnore' => $this->considerInsertIgnore($builder),
            'addSafetyLimit' => $this->addSafetyLimit($builder),
            'suggestIndexes' => $this->suggestIndexes($builder),
            'suggestTruncate' => $this->suggestTruncate($builder),
            default => $builder
        };
    }

    /**
     * @param SelectBuilder $builder
     * @return SelectBuilder
     */
    private function optimizeSelectColumns(SelectBuilder $builder): SelectBuilder
    {
        // Implementation would analyze and optimize SELECT columns
        return $builder;
    }

    /**
     * @param SelectBuilder $builder
     * @return SelectBuilder
     */
    private function optimizeJoinOrder(SelectBuilder $builder): SelectBuilder
    {
        // Implementation would reorder JOINs for better performance
        return $builder;
    }

    /**
     * @param SelectBuilder $builder
     * @return SelectBuilder
     */
    private function optimizeWhereConditions(SelectBuilder $builder): SelectBuilder
    {
        // Implementation would optimize WHERE conditions
        return $builder;
    }

    /**
     * @param SelectBuilder $builder
     * @return SelectBuilder
     */
    private function addSelectHints(SelectBuilder $builder): SelectBuilder
    {
        // Implementation would add MySQL query hints
        return $builder;
    }

    /**
     * @param InsertBuilder $builder
     * @return InsertBuilder
     */
    private function optimizeBatchInsert(InsertBuilder $builder): InsertBuilder
    {
        // Implementation would optimize batch INSERT operations
        return $builder;
    }

    /**
     * @param UpdateBuilder $builder
     * @return UpdateBuilder
     */
    private function optimizeUpdateConditions(UpdateBuilder $builder): UpdateBuilder
    {
        // Implementation would optimize UPDATE conditions
        return $builder;
    }

    /**
     * @param UpdateBuilder $builder
     * @return UpdateBuilder
     */
    private function optimizeUpdateJoins(UpdateBuilder $builder): UpdateBuilder
    {
        // Implementation would optimize UPDATE with JOINs
        return $builder;
    }

    /**
     * @param DeleteBuilder $builder
     * @return DeleteBuilder
     */
    private function optimizeDeleteConditions(DeleteBuilder $builder): DeleteBuilder
    {
        // Implementation would optimize DELETE conditions
        return $builder;
    }

    /**
     * @param DeleteBuilder $builder
     * @return DeleteBuilder
     */
    private function optimizeMultiTableDelete(DeleteBuilder $builder): DeleteBuilder
    {
        // Implementation would optimize multi-table DELETE operations
        return $builder;
    }

    // Condition checking methods

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function hasSelectStar(AbstractQueryBuilder $builder): bool
    {
        if (!($builder instanceof SelectBuilder)) {
            return false;
        }

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('select');
        $property->setAccessible(true);
        $select = $property->getValue($builder);

        return in_array('*', $select, true);
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function hasMultipleJoins(AbstractQueryBuilder $builder): bool
    {
        if (!($builder instanceof SelectBuilder)) {
            return false;
        }

        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('joins');
        $property->setAccessible(true);
        $joins = $property->getValue($builder);

        return count($joins) > 1;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function isExistsSubquery(AbstractQueryBuilder $builder): bool
    {
        // Implementation would check if this is an EXISTS subquery
        return false;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function isSmallBatch(AbstractQueryBuilder $builder): bool
    {
        if (!($builder instanceof InsertBuilder)) {
            return false;
        }

        return $builder->isBatchInsert() && $builder->getBatchSize() < 100;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function hasDuplicateKeyUpdate(AbstractQueryBuilder $builder): bool
    {
        // Implementation would check for ON DUPLICATE KEY UPDATE
        return false;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function hasNoWhereClause(AbstractQueryBuilder $builder): bool
    {
        if ($builder instanceof UpdateBuilder) {
            return !$builder->hasWhere();
        }

        if ($builder instanceof DeleteBuilder) {
            return !$builder->hasWhere();
        }

        return false;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function hasJoinsWithoutIndex(AbstractQueryBuilder $builder): bool
    {
        // Implementation would analyze JOIN conditions for index usage
        return false;
    }

    /**
     * @param AbstractQueryBuilder $builder
     * @return bool
     */
    private function isFullTableDelete(AbstractQueryBuilder $builder): bool
    {
        return $builder instanceof DeleteBuilder && !$builder->hasWhere();
    }

    // Optimization action methods (simplified implementations)

    private function suggestSpecificColumns(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function reorderJoins(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function addLimitOne(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function suggestBatching(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function considerInsertIgnore(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function addSafetyLimit(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function suggestIndexes(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }
    private function suggestTruncate(AbstractQueryBuilder $builder): AbstractQueryBuilder
    {
        return $builder;
    }

    /**
     * @param string $ruleName
     * @return void
     */
    private function incrementOptimizationStat(string $ruleName): void
    {
        $this->optimizationStats[$ruleName] = ($this->optimizationStats[$ruleName] ?? 0) + 1;
    }
}
