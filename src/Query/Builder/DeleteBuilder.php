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

/**
 * DELETE query builder with JOIN support and batch operations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DeleteBuilder extends AbstractQueryBuilder
{
    use WhereClauseTrait;
    use JoinClauseTrait;
    use OrderLimitTrait;
    use QueryOptionsTrait;

    /**
     * @var array<int, string>
     */
    private array $from = [];

    /**
     * @var bool
     */
    private bool $quick = false;

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

    /**
     * Enable QUICK option
     * @return self
     */
    public function quick(): self
    {
        $this->quick = true;
        return $this;
    }

    /**
     * Disable QUICK option
     * @return self
     */
    public function withoutQuick(): self
    {
        $this->quick = false;
        return $this;
    }

    protected function buildSql(): string
    {
        $parts = [];
        $parts[] = 'DELETE';

        // Modifiers
        $modifiers = $this->buildQueryModifiers();
        if ($this->quick) {
            $modifiers[] = 'QUICK';
        }
        if (!empty($modifiers)) {
            $parts = array_merge($parts, $modifiers);
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
        return 'DELETE';
    }
}
