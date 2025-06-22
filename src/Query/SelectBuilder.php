<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\Database\Relational\Sql\QueryBuilder;

/**
 * Optimized SELECT query builder with caching support
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class SelectBuilder extends AbstractQueryBuilder
{
    /**
     * @var array<int, string>
     */
    private array $select = ['*'];

    /**
     * @var bool
     */
    private bool $distinct = false;

    /**
     * @var array<int, array{table: string|SelectBuilder, alias: string|null}>
     */
    private array $from = [];

    /**
     * @var array<int, array{type: string, table: string, alias: string|null, condition: string|null}>
     */
    private array $joins = [];

    /**
     * @var SqlOperations|null
     */
    private ?SqlOperations $where = null;

    /**
     * @var array<int, string>
     */
    private array $groupBy = [];

    /**
     * @var bool
     */
    private bool $withRollup = false;

    /**
     * @var SqlOperations|null
     */
    private ?SqlOperations $having = null;

    /**
     * @var array<int, string>
     */
    private array $orderBy = [];

    /**
     * @var int
     */
    private int $limit = 0;

    /**
     * @var int
     */
    private int $offset = 0;

    /**
     * @var array<int, SelectBuilder>
     */
    private array $unionQueries = [];

    /**
     * @var bool
     */
    private bool $unionAll = false;

    /**
     * @var string
     */
    private string $subQueryAlias = '';

    /**
     * @param string ...$columns
     * @return self
     */
    public function select(string ...$columns): self
    {
        if (empty($columns)) {
            $this->select = ['*'];
            return $this;
        }

        // Handle DISTINCT
        if (str_starts_with($columns[0], 'DISTINCT ')) {
            $this->distinct = true;
            $columns[0] = substr($columns[0], 9);
        }

        $this->select = array_values(array_map([self::class, 'escapeIdentifier'], $columns));
        return $this;
    }

    /**
     * @param string $subQueryAlias
     * @return self
     */
    public function alias(string $subQueryAlias): self
    {
        $this->subQueryAlias = $subQueryAlias;
        return $this;
    }

    /**
     * @return string
     */
    public function getSubQueryAlias(): string
    {
        return $this->subQueryAlias;
    }

    /**
     * @param bool $distinct
     * @return self
     */
    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * @param string|SelectBuilder $table
     * @param string|null $alias
     * @return self
     */
    public function from(string|SelectBuilder $table, ?string $alias = null): self
    {
        if ($table instanceof SelectBuilder) {
            // Handle subquery
            $subQueryAlias = $alias ?? $table->getSubQueryAlias();
            if (empty($subQueryAlias)) {
                throw new \RuntimeException('Subquery must have an alias');
            }
            $this->from[] = ['table' => $table, 'alias' => $subQueryAlias];
        } else {
            if ($alias === null) {
                $parsed = $this->parseTableAlias($table);
                $this->from[] = $parsed;
            } else {
                $this->from[] = ['table' => $table, 'alias' => $alias];
            }
        }

        return $this;
    }

    /**
     * @param string $table
     * @param string|null $condition
     * @param string|null $alias
     * @return self
     */
    public function innerJoin(string $table, ?string $condition = null, ?string $alias = null): self
    {
        return $this->addJoin('INNER JOIN', $table, $condition, $alias);
    }

    /**
     * @param string $table
     * @param string|null $condition
     * @param string|null $alias
     * @return self
     */
    public function leftJoin(string $table, ?string $condition = null, ?string $alias = null): self
    {
        return $this->addJoin('LEFT JOIN', $table, $condition, $alias);
    }

    /**
     * @param string $table
     * @param string|null $condition
     * @param string|null $alias
     * @return self
     */
    public function rightJoin(string $table, ?string $condition = null, ?string $alias = null): self
    {
        return $this->addJoin('RIGHT JOIN', $table, $condition, $alias);
    }

    /**
     * @param string $table
     * @param string|null $condition
     * @param string|null $alias
     * @return self
     */
    public function fullJoin(string $table, ?string $condition = null, ?string $alias = null): self
    {
        return $this->addJoin('FULL OUTER JOIN', $table, $condition, $alias);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function crossJoin(string $table, ?string $alias = null): self
    {
        return $this->addJoin('CROSS JOIN', $table, null, $alias);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function naturalJoin(string $table, ?string $alias = null): self
    {
        return $this->addJoin('NATURAL JOIN', $table, null, $alias);
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function where(SqlOperations|string $condition): self
    {
        $this->where = is_string($condition) ? new SqlOperations($condition) : $condition;
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function andWhere(SqlOperations|string $condition): self
    {
        if ($this->where !== null) {
            $this->where->addOperation($condition, LinkOperator::AND);
        } else {
            $this->where($condition);
        }

        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function orWhere(SqlOperations|string $condition): self
    {
        if ($this->where !== null) {
            $this->where->addOperation($condition, LinkOperator::OR);
        } else {
            $this->where($condition);
        }

        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function andNotWhere(SqlOperations|string $condition): self
    {
        if ($this->where !== null) {
            $this->where->addOperation($condition, LinkOperator::AND_NOT);
        } else {
            $this->where($condition);
        }

        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function orNotWhere(SqlOperations|string $condition): self
    {
        if ($this->where !== null) {
            $this->where->addOperation($condition, LinkOperator::OR_NOT);
        } else {
            $this->where($condition);
        }

        return $this;
    }

    /**
     * @param string ...$columns
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_values(array_map([self::class, 'escapeIdentifier'], $columns));
        return $this;
    }

    /**
     * @param bool $withRollup
     * @return self
     */
    public function withRollup(bool $withRollup = true): self
    {
        $this->withRollup = $withRollup;
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function having(SqlOperations|string $condition): self
    {
        $this->having = is_string($condition) ? new SqlOperations($condition) : $condition;
        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function andHaving(SqlOperations|string $condition): self
    {
        if ($this->having !== null) {
            $this->having->addOperation($condition, LinkOperator::AND);
        } else {
            $this->having($condition);
        }

        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function orHaving(SqlOperations|string $condition): self
    {
        if ($this->having !== null) {
            $this->having->addOperation($condition, LinkOperator::OR);
        } else {
            $this->having($condition);
        }

        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function andNotHaving(SqlOperations|string $condition): self
    {
        if ($this->having !== null) {
            $this->having->addOperation($condition, LinkOperator::AND_NOT);
        } else {
            $this->having($condition);
        }

        return $this;
    }

    /**
     * @param SqlOperations|string $condition
     * @return self
     */
    public function orNotHaving(SqlOperations|string $condition): self
    {
        if ($this->having !== null) {
            $this->having->addOperation($condition, LinkOperator::OR_NOT);
        } else {
            $this->having($condition);
        }

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
        $this->orderBy[] = self::escapeIdentifier($column) . ' ' . $direction;
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
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int|null $page
     * @param int $manuallyOffset
     * @return self
     */
    public function offset(?int $page = 1, int $manuallyOffset = 0): self
    {
        if ($this->limit <= 0) {
            throw new \RuntimeException('Cannot set offset without a limit.');
        }

        $offset = $page === null ? $manuallyOffset : ($page - 1) * $this->limit;

        $this->offset = max(0, $offset);
        return $this;
    }

    /**
     * @param SelectBuilder ...$queries
     * @return self
     */
    public function union(SelectBuilder ...$queries): self
    {
        foreach ($queries as $query) {
            $this->unionQueries[] = $query;
        }
        $this->unionAll = false;
        return $this;
    }

    /**
     * @param SelectBuilder ...$queries
     * @return self
     */
    public function unionAll(SelectBuilder ...$queries): self
    {
        foreach ($queries as $query) {
            $this->unionQueries[] = $query;
        }
        $this->unionAll = true;
        return $this;
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        if (!empty($this->unionQueries)) {
            return $this->buildUnionQuery();
        }

        return $this->buildSelectQuery();
    }

    /**
     * @return string
     */
    public function getQueryType(): string
    {
        return 'SELECT';
    }

    /**
     * @param string $type
     * @param string $table
     * @param string|null $condition
     * @param string|null $alias
     * @return self
     */
    private function addJoin(string $type, string $table, ?string $condition, ?string $alias): self
    {
        if ($alias === null) {
            $parsed = $this->parseTableAlias($table);
            $this->joins[] = [
                'type' => $type,
                'table' => $parsed['table'],
                'alias' => $parsed['alias'],
                'condition' => $condition,
            ];
        } else {
            $this->joins[] = [
                'type' => $type,
                'table' => $table,
                'alias' => $alias,
                'condition' => $condition,
            ];
        }

        return $this;
    }

    /**
     * @return string
     */
    private function buildSelectQuery(): string
    {
        $sql = 'SELECT';

        if ($this->distinct) {
            $sql .= ' DISTINCT';
        }

        $sql .= ' ' . implode(', ', $this->select);

        // FROM clause
        if (!empty($this->from)) {
            $sql .= ' FROM ' . $this->buildFromClause();
        }

        // JOIN clauses
        if (!empty($this->joins)) {
            $sql .= ' ' . $this->buildJoinClauses();
        }

        // WHERE clause
        if ($this->where !== null) {
            $sql .= ' WHERE' . $this->where->generateOperation();
        }

        // GROUP BY clause
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
            if ($this->withRollup) {
                $sql .= ' WITH ROLLUP';
            }
        }

        // HAVING clause
        if ($this->having !== null) {
            $sql .= ' HAVING' . $this->having->generateOperation();
        }

        // ORDER BY clause
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        // LIMIT clause
        if ($this->limit > 0) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset > 0) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return $sql;
    }

    /**
     * @return string
     */
    private function buildFromClause(): string
    {
        $fromParts = [];

        foreach ($this->from as $table) {
            if ($table['table'] instanceof SelectBuilder) {
                // Handle subquery
                $subQuery = '(' . $table['table']->toSql() . ')';
                $part = $subQuery . ' AS ' . $table['alias'];

                // Merge named parameters from subquery
                $subQueryParams = $table['table']->getNamedParameters();
                foreach ($subQueryParams as $key => $value) {
                    $this->namedParameters[$key] = $value;
                }
            } else {
                $part = self::escapeIdentifier($table['table']);
                if ($table['alias'] !== null) {
                    $part .= ' AS ' . $table['alias'];
                }
            }
            $fromParts[] = $part;
        }

        return implode(', ', $fromParts);
    }

    /**
     * @return string
     */
    private function buildJoinClauses(): string
    {
        $joinParts = [];

        foreach ($this->joins as $join) {
            $part = $join['type'] . ' ' . self::escapeIdentifier($join['table']);

            if ($join['alias'] !== null) {
                $part .= ' AS ' . $join['alias'];
            }

            if ($join['condition'] !== null) {
                $explodeString = str_contains($join['condition'], ' = ') ? ' = ' : '=';
                $conditionParts = array_map([self::class, 'escapeIdentifier'], explode($explodeString, $join['condition']));
                $part .= ' ON ' . $conditionParts[0] . '=' . $conditionParts[1];
            }

            $joinParts[] = $part;
        }

        return implode(' ', $joinParts);
    }

    /**
     * @return string
     */
    private function buildUnionQuery(): string
    {
        $queries = [$this->buildSelectQuery()];

        foreach ($this->unionQueries as $unionQuery) {
            $queries[] = $unionQuery->toSql();

            // Merge named parameters from union queries
            $unionParams = $unionQuery->getNamedParameters();
            foreach ($unionParams as $key => $value) {
                $this->namedParameters[$key] = $value;
            }
        }

        $operator = $this->unionAll ? ' UNION ALL ' : ' UNION ';
        return implode($operator, $queries);
    }
}
