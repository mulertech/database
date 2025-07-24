<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

/**
 * Trait OrderLimitTrait
 *
 * Provides common ORDER BY and LIMIT methods for query builders
 *
 * @package MulerTech\Database\Query\Builder\Traits
 * @author Sébastien Muler
 */
trait OrderLimitTrait
{
    /**
     * @var array<string>
     */
    protected array $orderBy = [];

    /**
     * @var int
     */
    protected int $limit = 0;

    /**
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy[] = $this->formatIdentifier($column) . ' ' . $direction;
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param int $limit
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        $this->isDirty = true;
        return $this;
    }

    /**
     * Build ORDER BY clause for SQL
     * @return string
     */
    protected function buildOrderByClause(): string
    {
        if (empty($this->orderBy)) {
            return '';
        }
        return 'ORDER BY ' . implode(', ', $this->orderBy);
    }

    /**
     * Build LIMIT clause for SQL
     * @return string
     */
    protected function buildLimitClause(): string
    {
        if ($this->limit <= 0) {
            return '';
        }
        return 'LIMIT ' . $this->limit;
    }
}
