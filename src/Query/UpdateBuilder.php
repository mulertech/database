<?php

namespace MulerTech\Database\Query;

use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\Database\Relational\Sql\Raw;
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
     * @var array<int, array{table: string, alias: string|null}>
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
     * @param string $table
     * @param string|null $alias
     * @return self
     */
    public function table(string $table, ?string $alias = null): self
    {
        if ($alias === null) {
            $parsed = $this->parseTableAlias($table);
            $this->tables[] = $parsed;
        } else {
            $this->tables[] = ['table' => $table, 'alias' => $alias];
        }

        return $this;
    }

    /**
     * @param array<string, mixed>|string $column
     * @param mixed $value
     * @return self
     */
    public function set(array|string $column, mixed $value = null): self
    {
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->setSingleValue($col, $val);
            }
        } else {
            $this->setSingleValue($column, $value);
        }

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @return void
     */
    private function setSingleValue(string $column, mixed $value): void
    {
        if ($value instanceof Raw) {
            $this->setValues[$column] = $value->getValue();
        } else {
            $this->setValues[$column] = $value; // Will be processed in buildSetClause
        }
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param string|null $type
     * @return self
     */
    public function setValue(string $column, mixed $value, ?string $type = null): self
    {
        return $this->set($column, $value);
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
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->tables)) {
            throw new RuntimeException('No table specified for UPDATE');
        }

        if (empty($this->setValues)) {
            throw new RuntimeException('No SET values specified for UPDATE');
        }

        $sql = 'UPDATE';

        if ($this->ignore) {
            $sql .= ' IGNORE';
        }

        // Tables
        $sql .= ' ' . $this->buildTablesClause();

        // JOINs
        if (!empty($this->joins)) {
            $sql .= ' ' . $this->buildJoinClauses();
        }

        // SET clause
        $sql .= ' SET ' . $this->buildSetClause();

        // WHERE clause
        if ($this->where !== null) {
            $sql .= ' WHERE' . $this->where->generateOperation();
        }

        // ORDER BY clause
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        // LIMIT clause
        if ($this->limit > 0) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        return $sql;
    }

    /**
     * @return string
     */
    public function getQueryType(): string
    {
        return 'UPDATE';
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
    private function buildTablesClause(): string
    {
        $tableParts = [];

        foreach ($this->tables as $table) {
            $part = $this->escapeIdentifier($table['table']);
            if ($table['alias'] !== null) {
                $part .= ' AS ' . $table['alias'];
            }
            $tableParts[] = $part;
        }

        return implode(', ', $tableParts);
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
     * @return string
     */
    private function buildSetClause(): string
    {
        $setParts = [];

        foreach ($this->setValues as $column => $value) {
            $escapedColumn = $this->escapeIdentifier($column);

            if (is_string($value) && (str_contains($value, $escapedColumn) || str_contains($value, '('))) {
                // Raw expression (like increment/decrement or function calls)
                $setParts[] = "$escapedColumn = $value";
            } else {
                // Regular value - parameterize it
                $setParts[] = "$escapedColumn = " . $this->addNamedParameter($value);
            }
        }

        return implode(', ', $setParts);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSetValues(): array
    {
        return $this->setValues;
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
}
