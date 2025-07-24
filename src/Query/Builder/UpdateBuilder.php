<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Builder\Traits\JoinClauseTrait;
use MulerTech\Database\Query\Builder\Traits\OrderLimitTrait;
use MulerTech\Database\Query\Builder\Traits\QueryOptionsTrait;
use MulerTech\Database\Query\Builder\Traits\WhereClauseTrait;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
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
    use WhereClauseTrait;
    use JoinClauseTrait;
    use OrderLimitTrait;
    use QueryOptionsTrait;

    /**
     * @var array<int, string>
     */
    private array $tables = [];

    /**
     * @var array<string, string>
     */
    private array $setValues = [];

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
        $tableFormatted = $this->formatIdentifier($table);
        if ($alias !== null) {
            $tableFormatted .= ' AS ' . $this->formatIdentifier($alias);
        }
        $this->tables[] = $tableFormatted;
        $this->isDirty = true;
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
            $this->isDirty = true;
            return $this;
        }
        $this->setValues[$column] = $type !== null
            ? $this->parameterBag->add($value, $type) : $this->parameterBag->add($value);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param int|float $value
     * @return self
     */
    public function increment(string $column, int|float $value = 1): self
    {
        $escapedColumn = $this->formatIdentifier($column);
        $paramValue = $this->parameterBag->add($value);
        $this->setValues[$column] = "$escapedColumn + $paramValue";
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param int|float $value
     * @return self
     */
    public function decrement(string $column, int|float $value = 1): self
    {
        $escapedColumn = $this->formatIdentifier($column);
        $paramValue = $this->parameterBag->add($value);
        $this->setValues[$column] = "$escapedColumn - $paramValue";
        $this->isDirty = true;
        return $this;
    }

    protected function buildSql(): string
    {
        $parts = [];
        $parts[] = 'UPDATE';

        // Modifiers
        $modifiers = $this->buildQueryModifiers();
        if (!empty($modifiers)) {
            $parts = array_merge($parts, $modifiers);
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
        $orderBy = $this->buildOrderByClause();
        if ($orderBy !== '') {
            $parts[] = $orderBy;
        }

        // LIMIT clause
        $limitClause = $this->buildLimitClause();
        if ($limitClause !== '') {
            $parts[] = $limitClause;
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
