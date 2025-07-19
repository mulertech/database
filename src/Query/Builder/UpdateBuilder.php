<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Clause\ComparisonOperator;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Query\Types\JoinType;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;
use PDO;
use RuntimeException;

/**
 * UPDATE query builder with complex conditions and JOIN support
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class UpdateBuilder extends AbstractQueryBuilder
{
    /**
     * @var array<int, string>
     */
    private array $tables = [];

    /**
     * @var array<string, mixed>
     */
    private array $setValues = [];

    /**
     * @var array<string>
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
     * @var JoinClauseBuilder
     */
    private JoinClauseBuilder $joinBuilder;

    /**
     * @var WhereClauseBuilder
     */
    private WhereClauseBuilder $whereBuilder;

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
    public function table(string $table, ?string $alias = null): self
    {
        $this->tables[] = $this->formatTable($table, $alias);
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param int|null $type
     * @return self
     */
    public function set(string $column, mixed $value = null, ?int $type = PDO::PARAM_STR): self
    {
        if ($value instanceof Raw) {
            $this->setValues[$column] = $value->getValue();
        } else {
            $this->setValues[$column] = $type !== null
                ? $this->parameterBag->add($value, $type) : $this->parameterBag->add($value);
        }

        return $this;
    }

    /**
     * @param string $column
     * @param int|float $value
     * @return self
     */
    public function increment(string $column, int|float $value = 1): self
    {
        $escapedColumn = $this->escapeIdentifier($column);
        $paramValue = $this->parameterBag->add($value);
        $this->setValues[$column] = "$escapedColumn + $paramValue";
        return $this;
    }

    /**
     * @param string $column
     * @param int|float $value
     * @return self
     */
    public function decrement(string $column, int|float $value = 1): self
    {
        $escapedColumn = $this->escapeIdentifier($column);
        $paramValue = $this->parameterBag->add($value);
        $this->setValues[$column] = "$escapedColumn - $paramValue";
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
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = $this->escapeIdentifier($column) . ' ' . $direction;
        return $this;
    }

    /**
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
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

    protected function buildSql(): string
    {
        $parts = [];
        $parts[] = 'UPDATE';

        if ($this->ignore) {
            $parts[] = 'IGNORE';
        }

        $parts[] = $this->buildTablesClause();

        // JOINs - Use joinBuilder
        $joinSql = $this->joinBuilder->toSql();
        if ($joinSql !== '') {
            $parts[] = $joinSql;
        }

        // SET clause
        if (empty($this->setValues)) {
            throw new RuntimeException('No SET values specified for UPDATE');
        }

        $setParts = [];
        foreach ($this->setValues as $column => $value) {
            $setParts[] = $this->formatIdentifier($column) . ' = ' . $value;
        }
        $parts[] = 'SET ' . implode(', ', $setParts);

        // WHERE clause - Use whereBuilder
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
        return 'UPDATE';
    }

    /**
     * @return string
     */
    private function buildTablesClause(): string
    {
        if (empty($this->tables)) {
            throw new RuntimeException('No table specified for UPDATE');
        }

        return implode(', ', $this->tables);
    }
}
