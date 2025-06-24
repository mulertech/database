<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Relational\Sql\ComparisonOperator;
use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\Database\Relational\Sql\Raw;
use MulerTech\Database\Relational\Sql\SqlOperator;
use PDO;
use RuntimeException;

/**
 * UPDATE query builder with complex conditions and JOIN support
 *
 * @package MulerTech\Database\Query
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
     * @var array<int, array{type: string, table: string, alias: string|null, condition: string|null}>
     */
    private array $joins = [];

    /**
     * @var SqlOperations|null
     */
    private ?SqlOperations $where = null;

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
                ? $this->addNamedParameter($value, $type) : $this->addNamedParameter($value);
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
        $paramValue = $this->addNamedParameter($value);
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
        $paramValue = $this->addNamedParameter($value);
        $this->setValues[$column] = "$escapedColumn - $paramValue";
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
     * @param mixed|null $value
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
     * @param mixed|null $value
     * @param ComparisonOperator|SqlOperator $operator
     * @return self
     */
    public function andWhere(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
    ): self
    {
        $this->whereBuilder->add($column, $value, $operator);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed|null $value
     * @param ComparisonOperator|SqlOperator $operator
     * @return self
     */
    public function orWhere(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
    ): self
    {
        $this->whereBuilder->add($column, $value, $operator, LinkOperator::OR);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed|null $value
     * @param ComparisonOperator|SqlOperator $operator
     * @return self
     */
    public function andNotWhere(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
    ): self
    {
        $this->whereBuilder->add($column, $value, $operator, LinkOperator::AND_NOT);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed|null $value
     * @param ComparisonOperator|SqlOperator $operator
     * @return self
     */
    public function orNotWhere(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
    ): self
    {
        $this->whereBuilder->add($column, $value, $operator, LinkOperator::OR_NOT);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed> $values
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $this->whereBuilder->in($column, $values);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed> $values
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->whereBuilder->notIn($column, $values);
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
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->whereBuilder->between($column, $min, $max);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     * @return self
     */
    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $this->whereBuilder->notBetween($column, $min, $max);
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
