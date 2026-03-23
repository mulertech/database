<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

/**
 * Trait OrderLimitTrait.
 *
 * Provides common ORDER BY and LIMIT methods for query builders
 *
 * @author Sébastien Muler
 */
trait OrderLimitTrait
{
    /**
     * @var array<string>
     */
    protected array $orderBy = [];

    protected int $limit = 0;

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = 'DESC' === strtoupper($direction) ? 'DESC' : 'ASC';
        $this->orderBy[] = $this->formatIdentifier($column).' '.$direction;
        $this->isDirty = true;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        $this->isDirty = true;

        return $this;
    }

    /**
     * Build ORDER BY clause for SQL.
     */
    protected function buildOrderByClause(): string
    {
        if (empty($this->orderBy)) {
            return '';
        }

        return 'ORDER BY '.implode(', ', $this->orderBy);
    }

    /**
     * Build LIMIT clause for SQL.
     */
    protected function buildLimitClause(): string
    {
        if ($this->limit <= 0) {
            return '';
        }

        return 'LIMIT '.$this->limit;
    }
}
