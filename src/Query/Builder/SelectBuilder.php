<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Builder\Traits\JoinClauseTrait;
use MulerTech\Database\Query\Builder\Traits\WhereClauseTrait;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\SqlOperator;

/**
 * Class SelectBuilder.
 *
 * Refactored SELECT query builder using new components
 *
 * @author Sébastien Muler
 */
class SelectBuilder extends AbstractQueryBuilder
{
    use WhereClauseTrait;
    use JoinClauseTrait;

    /**
     * @var array<string>
     */
    private array $select = [];

    /**
     * @var array<int, array{table?: string, subquery?: SelectBuilder, alias: string|null}>
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

    private ?WhereClauseBuilder $havingBuilder = null;

    private ?int $limit = null;

    private ?int $offset = null;

    private bool $distinct = false;

    public function __construct(?EmEngine $emEngine = null)
    {
        parent::__construct($emEngine);
        $this->whereBuilder = new WhereClauseBuilder($this->parameterBag);
        $this->joinBuilder = new JoinClauseBuilder($this->parameterBag);
    }

    public function getQueryType(): string
    {
        return 'SELECT';
    }

    public function select(string ...$columns): self
    {
        $formattedColumns = array_map([$this, 'formatIdentifierWithAlias'], $columns);
        $this->select = array_merge($this->select, $formattedColumns);
        $this->isDirty = true;

        return $this;
    }

    public function from(string|SelectBuilder $table, ?string $alias = null): self
    {
        $this->from[] = $table instanceof self
            ? ['subquery' => $table, 'alias' => $alias]
            : [
                'table' => $this->formatIdentifier($table),
                'alias' => null !== $alias ? $this->formatIdentifier($alias) : null,
            ];
        $this->isDirty = true;

        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        $this->isDirty = true;

        return $this;
    }

    public function having(string $column, mixed $value = null, ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL): self
    {
        if (null === $this->havingBuilder) {
            $this->havingBuilder = new WhereClauseBuilder($this->parameterBag);
        }
        $this->havingBuilder->add($column, $value, $operator);
        $this->isDirty = true;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = $this->formatIdentifier($column).' '.strtoupper($direction);
        $this->isDirty = true;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        $this->isDirty = true;

        return $this;
    }

    public function offset(?int $offset, ?int $page = null): self
    {
        if ($this->limit <= 0) {
            throw new \RuntimeException('Cannot set offset without a limit.');
        }

        $offset = null === $page ? $offset : ($page - 1) * $this->limit;

        $this->offset = max(0, $offset);
        $this->isDirty = true;

        return $this;
    }

    /**
     * Enable DISTINCT option.
     */
    public function distinct(): self
    {
        $this->distinct = true;
        $this->isDirty = true;

        return $this;
    }

    /**
     * Disable DISTINCT option.
     */
    public function withoutDistinct(): self
    {
        $this->distinct = false;
        $this->isDirty = true;

        return $this;
    }

    protected function buildSql(): string
    {
        $parts = [];

        // Build each clause using dedicated methods
        $parts[] = $this->buildSelectClause();
        $this->addFromClause($parts);
        $this->addJoinClause($parts);
        $this->addWhereClause($parts);
        $this->addGroupByClause($parts);
        $this->addHavingClause($parts);
        $this->addOrderByClause($parts);
        $this->addLimitClause($parts);

        return implode(' ', $parts);
    }

    /**
     * Build SELECT clause.
     */
    private function buildSelectClause(): string
    {
        $selectClause = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $selectClause .= !empty($this->select)
            ? implode(', ', $this->select)  // Les colonnes sont déjà formatées dans select()
            : '*';

        return $selectClause;
    }

    /**
     * Add FROM clause to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addFromClause(array &$parts): void
    {
        if (!empty($this->from)) {
            $parts[] = 'FROM '.implode(', ', $this->generateFromParts());
        }
    }

    /**
     * Add JOIN clause to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addJoinClause(array &$parts): void
    {
        $joinSql = $this->joinBuilder->toSql();
        if ('' !== $joinSql) {
            $parts[] = $joinSql;
        }
    }

    /**
     * Add WHERE clause to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addWhereClause(array &$parts): void
    {
        $whereSql = $this->whereBuilder->toSql();
        if ('' !== $whereSql) {
            $parts[] = 'WHERE '.$whereSql;
        }
    }

    /**
     * Add GROUP BY clause to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addGroupByClause(array &$parts): void
    {
        if (!empty($this->groupBy)) {
            $parts[] = 'GROUP BY '.implode(', ', array_map([$this, 'formatIdentifier'], $this->groupBy));
        }
    }

    /**
     * Add HAVING clause to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addHavingClause(array &$parts): void
    {
        if (null !== $this->havingBuilder) {
            $havingSql = $this->havingBuilder->toSql();
            if ('' !== $havingSql) {
                $parts[] = 'HAVING '.$havingSql;
            }
        }
    }

    /**
     * Add ORDER BY clause to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addOrderByClause(array &$parts): void
    {
        if (!empty($this->orderBy)) {
            $parts[] = 'ORDER BY '.implode(', ', $this->orderBy);
        }
    }

    /**
     * Add LIMIT and OFFSET clauses to parts if needed.
     *
     * @param array<string> $parts
     */
    private function addLimitClause(array &$parts): void
    {
        if ($this->limit > 0) {
            $parts[] = 'LIMIT '.$this->limit;
            if ($this->offset > 0) {
                $parts[] = 'OFFSET '.$this->offset;
            }
        }
    }

    /**
     * Generate the FROM clause parts.
     *
     * @return array<string>
     *
     * @throws \RuntimeException
     */
    public function generateFromParts(): array
    {
        $fromParts = [];
        foreach ($this->from as $table) {
            $fromSubject = $table['table'] ?? $table['subquery'] ?? null;

            if (null === $fromSubject) {
                throw new \RuntimeException('Invalid FROM clause: missing table or subquery.');
            }

            $aliasPart = null !== $table['alias'] ? ' AS '.$this->formatIdentifier($table['alias']) : '';

            if ($fromSubject instanceof self) {
                $fromSubject->setParameterBag($this->parameterBag);
                $fromParts[] = '('.$fromSubject->toSql().')'.$aliasPart;
                continue;
            }

            $fromParts[] = $this->formatIdentifier($fromSubject).$aliasPart;
        }

        return $fromParts;
    }

    /**
     * Set the parameter bag for this query.
     */
    public function setParameterBag(QueryParameterBag $parameterBag): self
    {
        $this->parameterBag = $parameterBag;

        return $this;
    }
}
