<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Relational\Sql\ComparisonOperator;
use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\SqlOperator;

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
     * @var array<string>
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
        $this->select = array_merge($this->select, $columns);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function from(string $table, ?string $alias = null): self
    {
        $this->from[] = $this->formatTable($table, $alias);
        $this->isDirty = true;
        return $this;
    }

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
     * @param string $column
     * @param array<mixed> $values
     * @return self
     */
    public function whereIn(string $column, array|SelectBuilder $values): self
    {
        $this->whereBuilder->in($column, $values);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<mixed> $values
     * @return self
     */
    public function whereNotIn(string $column, array|SelectBuilder $values): self
    {
        $this->whereBuilder->notIn($column, $values);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @return self
     */
    public function whereBetween(string $column, mixed $start, mixed $end): self
    {
        $this->whereBuilder->between($column, $start, $end);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->whereBuilder->isNull($column);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->whereBuilder->isNotNull($column);
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
    public function offset(int $offset): self
    {
        $this->offset = $offset;
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
            $parts[] = 'FROM ' . implode(', ', $this->from);
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
     * @return WhereClauseBuilder
     */
    public function getWhereBuilder(): WhereClauseBuilder
    {
        return $this->whereBuilder;
    }

    /**
     * @return JoinClauseBuilder
     */
    public function getJoinBuilder(): JoinClauseBuilder
    {
        return $this->joinBuilder;
    }
}