<?php

namespace MulerTech\Database\Query;

use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\SqlOperations;
use RuntimeException;

/**
 * DELETE query builder with JOIN support and batch operations
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class DeleteBuilder extends AbstractQueryBuilder
{
    /**
     * @var array<int, array{table: string, alias: string|null}>
     */
    private array $from = [];

    /**
     * @var array<int|string, string>
     */
    private array $deleteFrom = [];

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
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function from(string $table, ?string $alias = null): self
    {
        if ($alias === null) {
            $parsed = $this->parseTableAlias($table);
            $this->from[] = $parsed;
        } else {
            $this->from[] = ['table' => $table, 'alias' => $alias];
        }

        return $this;
    }

    /**
     * @param string ...$tables
     * @return self
     */
    public function deleteFrom(string ...$tables): self
    {
        $this->deleteFrom = array_merge($this->deleteFrom, $tables);
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
     * @param string $column
     * @param array<mixed> $values
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new RuntimeException('WHERE IN values cannot be empty');
        }

        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->addNamedParameter($value);
        }

        $condition = $this->escapeIdentifier($column) . ' IN (' . implode(', ', $placeholders) . ')';
        return $this->andWhere($condition);
    }

    /**
     * @param string $column
     * @param array<mixed> $values
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            throw new RuntimeException('WHERE NOT IN values cannot be empty');
        }

        $placeholders = [];
        foreach ($values as $value) {
            $placeholders[] = $this->addNamedParameter($value);
        }

        $condition = $this->escapeIdentifier($column) . ' NOT IN (' . implode(', ', $placeholders) . ')';
        return $this->andWhere($condition);
    }

    /**
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     * @return self
     */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $condition = $this->escapeIdentifier($column) . ' BETWEEN ' .
            $this->addNamedParameter($min) . ' AND ' .
            $this->addNamedParameter($max);
        return $this->andWhere($condition);
    }

    /**
     * @param string $column
     * @return self
     */
    public function whereNull(string $column): self
    {
        $condition = $this->escapeIdentifier($column) . ' IS NULL';
        return $this->andWhere($condition);
    }

    /**
     * @param string $column
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $condition = $this->escapeIdentifier($column) . ' IS NOT NULL';
        return $this->andWhere($condition);
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

    /**
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->from)) {
            throw new RuntimeException('No table specified for DELETE');
        }

        $sql = 'DELETE';

        // Modifiers
        if ($this->lowPriority) {
            $sql .= ' LOW_PRIORITY';
        }
        if ($this->quick) {
            $sql .= ' QUICK';
        }
        if ($this->ignore) {
            $sql .= ' IGNORE';
        }

        // DELETE FROM specific tables (for multi-table deletes)
        if (!empty($this->deleteFrom)) {
            $sql .= ' ' . implode(', ', array_map([$this, 'escapeIdentifier'], $this->deleteFrom));
        }

        // FROM clause
        $sql .= ' FROM ' . $this->buildFromClause();

        // JOINs
        if (!empty($this->joins)) {
            $sql .= ' ' . $this->buildJoinClauses();
        }

        // WHERE clause
        if ($this->where !== null) {
            $sql .= ' WHERE' . $this->where->generateOperation();
        }

        // ORDER BY clause (only for single-table deletes without JOINs)
        if (!empty($this->orderBy) && empty($this->joins) && empty($this->deleteFrom)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        // LIMIT clause (only for single-table deletes without JOINs)
        if ($this->limit > 0 && empty($this->joins) && empty($this->deleteFrom)) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        return $sql;
    }

    /**
     * @return string
     */
    public function getQueryType(): string
    {
        return 'DELETE';
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
                'condition' => $condition
            ];
        } else {
            $this->joins[] = [
                'type' => $type,
                'table' => $table,
                'alias' => $alias,
                'condition' => $condition
            ];
        }

        return $this;
    }

    /**
     * @return string
     */
    private function buildFromClause(): string
    {
        $fromParts = [];

        foreach ($this->from as $table) {
            $part = $this->escapeIdentifier($table['table']);
            if ($table['alias'] !== null) {
                $part .= ' AS ' . $table['alias'];
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
            $part = $join['type'] . ' ' . $this->escapeIdentifier($join['table']);

            if ($join['alias'] !== null) {
                $part .= ' AS ' . $join['alias'];
            }

            if ($join['condition'] !== null) {
                $part .= ' ON ' . $join['condition'];
            }

            $joinParts[] = $part;
        }

        return implode(' ', $joinParts);
    }

    /**
     * @return bool
     */
    public function isMultiTable(): bool
    {
        return !empty($this->deleteFrom) || !empty($this->joins);
    }

    /**
     * @return bool
     */
    public function hasJoins(): bool
    {
        return !empty($this->joins);
    }

    /**
     * @return bool
     */
    public function hasWhere(): bool
    {
        return $this->where !== null;
    }

    /**
     * @return array<string>
     */
    public function getDeleteTables(): array
    {
        if (!empty($this->deleteFrom)) {
            return $this->deleteFrom;
        }

        // For single table delete, return the first table
        if (!empty($this->from)) {
            return [$this->from[0]['table']];
        }

        return [];
    }
}
