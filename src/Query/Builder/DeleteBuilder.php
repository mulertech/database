<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\SQL\Operator\ComparisonOperator;
use MulerTech\Database\SQL\Type\JoinType;
use MulerTech\Database\SQL\Operator\LinkOperator;
use MulerTech\Database\SQL\Operator\SqlOperator;

/**
 * DELETE query builder with JOIN support and batch operations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DeleteBuilder extends AbstractQueryBuilder
{
    /**
     * @var array<int, string>
     */
    private array $from = [];

    /**
     * @var array<int, string>
     */
    private array $orderBy = [];

    /**
     * @var int
     */
    private int $limit = 0;

    /**
     * @var bool
     */
    private bool $ignore = false;

    /**
     * @var bool
     */
    private bool $quick = false;

    /**
     * @var bool
     */
    private bool $lowPriority = false;

    /**
     * @var WhereClauseBuilder
     */
    private WhereClauseBuilder $whereBuilder;

    /**
     * @var JoinClauseBuilder
     */
    private JoinClauseBuilder $joinBuilder;

    /**
     * DeleteBuilder constructor.
     *
     * @param EmEngine|null $emEngine
     */
    public function __construct(?EmEngine $emEngine = null)
    {
        parent::__construct($emEngine);
        $this->whereBuilder = new WhereClauseBuilder($this->parameterBag);
        $this->joinBuilder = new JoinClauseBuilder($this->parameterBag);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->from[] = $this->formatIdentifier($table) . ($alias ? ' AS ' . $this->formatIdentifier($alias) : '');
        $this->isDirty = true;
        return $this;
    }

    // WhereClauseBuilder methods

    /**
     * @param string $column
     * @param mixed $value
     * @param ComparisonOperator|SqlOperator $operator
     * @param LinkOperator $link
     * @return self
     */
    public function where(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->add($column, $value, $operator, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->equal($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notEqual($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereGreaterThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->greaterThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereLessThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->lessThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereGreaterOrEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->greaterOrEqual($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotGreaterThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notGreaterThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereLessOrEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->lessOrEqual($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotLessThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notLessThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $pattern
     * @param LinkOperator $link
     * @return self
     */
    public function whereLike(
        string $column,
        mixed $pattern = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->like($column, $pattern, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $pattern
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotLike(
        string $column,
        mixed $pattern = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notLike($column, $pattern, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed> $values
     * @param LinkOperator $link
     * @return self
     */
    public function whereIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->in($column, $values, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed> $values
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->notIn($column, $values, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param LinkOperator $link
     * @return self
     */
    public function whereBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->between($column, $start, $end, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->notBetween($column, $start, $end, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param LinkOperator $link
     * @return self
     */
    public function whereNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->isNull($column, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->isNotNull($column, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $rawCondition
     * @param array<string, mixed> $parameters
     * @param LinkOperator $link
     * @return self
     */
    public function whereRaw(string $rawCondition, array $parameters = [], LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->raw($rawCondition, $parameters, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param callable(WhereClauseBuilder): void $callback
     * @param LinkOperator $link
     * @return self
     */
    public function whereGroup(callable $callback, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->group($callback, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param SelectBuilder $subQuery
     * @param LinkOperator $link
     * @return self
     */
    public function whereExists(SelectBuilder $subQuery, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->exists($subQuery, $link);
        $this->isDirty = true;
        return $this;
    }

    // JoinClauseBuilder methods

    /**
     * @param JoinType $type
     * @param string $table
     * @param string $leftColumn
     * @param string $rightColumn
     * @param string|null $alias
     * @return self
     */
    public function join(
        JoinType $type,
        string $table,
        string $leftColumn,
        string $rightColumn,
        ?string $alias = null
    ): self {
        $this->joinBuilder->add($type, $table, $alias)->on($leftColumn, $rightColumn);
        $this->isDirty = true;
        return $this;
    }
    /**
     * @param string $table
     * @param string $leftColumn
     * @param string $rightColumn
     * @param string|null $alias
     * @return self
     */
    public function innerJoin(string $table, string $leftColumn, string $rightColumn, ?string $alias = null): self
    {
        $this->joinBuilder->inner($table, $alias)->on($leftColumn, $rightColumn);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $table
     * @param string $leftColumn
     * @param string $rightColumn
     * @param string|null $alias
     * @return self
     */
    public function leftJoin(string $table, string $leftColumn, string $rightColumn, ?string $alias = null): self
    {
        $this->joinBuilder->left($table, $alias)->on($leftColumn, $rightColumn);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $table
     * @param string $leftColumn
     * @param string $rightColumn
     * @param string|null $alias
     * @return self
     */
    public function rightJoin(string $table, string $leftColumn, string $rightColumn, ?string $alias = null): self
    {
        $this->joinBuilder->right($table, $alias)->on($leftColumn, $rightColumn);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $table
     * @param string|null $leftColumn
     * @param string|null $rightColumn
     * @param string|null $alias
     * @return self
     */
    public function crossJoin(string $table, ?string $leftColumn = null, ?string $rightColumn = null, ?string $alias = null): self
    {
        $join = $this->joinBuilder->cross($table, $alias);

        if ($leftColumn !== null && $rightColumn !== null) {
            $join->on($leftColumn, $rightColumn);
        }
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = $this->formatIdentifier($column) . ' ' . strtoupper($direction);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param bool $ignore
     * @return self
     */
    public function ignore(bool $ignore = true): self
    {
        $this->ignore = $ignore;
        return $this;
    }

    /**
     * @param bool $quick
     * @return self
     */
    public function quick(bool $quick = true): self
    {
        $this->quick = $quick;
        return $this;
    }

    /**
     * @param bool $lowPriority
     * @return self
     */
    public function lowPriority(bool $lowPriority = true): self
    {
        $this->lowPriority = $lowPriority;
        return $this;
    }

    protected function buildSql(): string
    {
        $parts = [];
        $parts[] = 'DELETE';

        // Modifiers
        if ($this->lowPriority) {
            $parts[] = 'LOW_PRIORITY';
        }
        if ($this->quick) {
            $parts[] = 'QUICK';
        }
        if ($this->ignore) {
            $parts[] = 'IGNORE';
        }

        $parts[] = 'FROM ' . implode(', ', $this->from);

        // JOIN clauses
        $joinSql = $this->joinBuilder->toSql();
        if ($joinSql !== '') {
            $parts[] = $joinSql;
        }

        // WHERE clause
        $whereSql = $this->whereBuilder->toSql();
        if ($whereSql !== '') {
            $parts[] = 'WHERE ' . $whereSql;
        }

        // ORDER BY clause
        if (!empty($this->orderBy)) {
            $parts[] = 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        // LIMIT clause
        if ($this->limit > 0) {
            $parts[] = 'LIMIT ' . $this->limit;
        }

        return implode(' ', $parts);
    }

    /**
     * @return string
     */
    public function getQueryType(): string
    {
        return 'DELETE';
    }
}
