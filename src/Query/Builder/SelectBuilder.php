<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\SQL\Operator\ComparisonOperator;
use MulerTech\Database\SQL\Type\JoinType;
use MulerTech\Database\SQL\Operator\LinkOperator;
use MulerTech\Database\SQL\Operator\SqlOperator;
use RuntimeException;

/**
 * Class SelectBuilder
 *
 * Refactored SELECT query builder using new components
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SelectBuilder extends AbstractQueryBuilder
{
    /**
     * @var WhereClauseBuilder
     */
    private WhereClauseBuilder $whereBuilder;

    /**
     * @var JoinClauseBuilder
     */
    private JoinClauseBuilder $joinBuilder;

    /**
     * @var array<string>
     */
    private array $select = [];

    /**
     * @var array<int, array{table: string, alias: string|null}|array{subquery: SelectBuilder, alias: string|null}>
     */
    private array $from = [];

    /**
     * @var array<string>
     */
    private array $groupBy = [];

    /**
     * @var array<string>
     */
    private array $orderBy = [];

    /**
     * @var WhereClauseBuilder|null
     */
    private ?WhereClauseBuilder $havingBuilder = null;

    /**
     * @var int|null
     */
    private ?int $limit = null;

    /**
     * @var int|null
     */
    private ?int $offset = null;

    /**
     * @var bool
     */
    private bool $distinct = false;

    /**
     * @inheritDoc
     */
    public function __construct(?EmEngine $emEngine = null)
    {
        parent::__construct($emEngine);
        $this->whereBuilder = new WhereClauseBuilder($this->parameterBag);
        $this->joinBuilder = new JoinClauseBuilder($this->parameterBag);
    }

    /**
     * @inheritDoc
     */
    public function getQueryType(): string
    {
        return 'SELECT';
    }

    /**
     * @param string ...$columns
     * @return self
     */
    public function select(string ...$columns): self
    {
        $columns = array_map(
            fn (string $column) => $this->formatIdentifierWithAlias($column),
            $columns
        );
        $this->select = array_merge($this->select, $columns);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string|SelectBuilder $table
     * @param string|null $alias
     * @return self
     */
    public function from(string|SelectBuilder $table, ?string $alias = null): self
    {
        $this->from[] = $table instanceof self
            ? ['subquery' => $table, 'alias' => $alias]
            : [
                'table' => $this->formatIdentifier($table),
                'alias' => $alias !== null ? $this->formatIdentifier($alias) : null,
            ];
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
     * @param string ...$columns
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param ComparisonOperator|SqlOperator $operator
     * @return self
     */
    public function having(string $column, mixed $value = null, ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL): self
    {
        if ($this->havingBuilder === null) {
            $this->havingBuilder = new WhereClauseBuilder($this->parameterBag);
        }
        $this->havingBuilder->add($column, $value, $operator);
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
     * @param int $offset
     * @return self
     */
    public function offset(?int $offset, ?int $page = null): self
    {
        if ($this->limit <= 0) {
            throw new RuntimeException('Cannot set offset without a limit.');
        }

        $offset = $page === null ? $offset : ($page - 1) * $this->limit;

        $this->offset = max(0, $offset);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param bool $distinct
     * @return self
     */
    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;
        $this->isDirty = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function buildSql(): string
    {
        $parts = [];

        // SELECT clause
        $selectClause = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $selectClause .= !empty($this->select)
            ? implode(', ', array_map([$this, 'formatIdentifier'], $this->select))
            : '*';
        $parts[] = $selectClause;

        // FROM clause
        if (!empty($this->from)) {
            $parts[] = 'FROM ' . implode(', ', $this->generateFromParts());
        }

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

        // GROUP BY clause
        if (!empty($this->groupBy)) {
            $parts[] = 'GROUP BY ' . implode(', ', array_map([$this, 'formatIdentifier'], $this->groupBy));
        }

        // HAVING clause
        if ($this->havingBuilder !== null) {
            $havingSql = $this->havingBuilder->toSql();
            if ($havingSql !== '') {
                $parts[] = 'HAVING ' . $havingSql;
            }
        }

        // ORDER BY clause
        if (!empty($this->orderBy)) {
            $parts[] = 'ORDER BY ' . implode(', ', $this->orderBy);
        }

        // LIMIT clause
        if ($this->limit > 0) {
            $parts[] = 'LIMIT ' . $this->limit;
            if ($this->offset > 0) {
                $parts[] = 'OFFSET ' . $this->offset;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Generate the FROM clause parts.
     *
     * @return array<string>
     * @throws RuntimeException
     */
    public function generateFromParts(): array
    {
        $fromParts = [];
        foreach ($this->from as $table) {
            $fromSubject = $table['table'] ?? $table['subquery'] ?? null;

            if ($fromSubject === null) {
                throw new RuntimeException('Invalid FROM clause: missing table or subquery.');
            }

            $aliasPart = $table['alias'] !== null ? ' AS ' . $this->formatIdentifier($table['alias']) : '';

            if ($fromSubject instanceof self) {
                $fromSubject->setParameterBag($this->parameterBag);
                $fromParts[] = '(' . $fromSubject->toSql() . ')' . $aliasPart;
                continue;
            }

            $fromParts[] = $this->formatIdentifier($fromSubject) . $aliasPart;
        }
        return $fromParts;
    }

    /**
     * Set the parameter bag for this query.
     *
     * @param QueryParameterBag $parameterBag
     * @return self
     */
    public function setParameterBag(QueryParameterBag $parameterBag): self
    {
        $this->parameterBag = $parameterBag;
        return $this;
    }
}
