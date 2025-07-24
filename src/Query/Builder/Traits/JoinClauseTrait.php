<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Types\JoinType;

/**
 * Trait JoinClauseTrait
 *
 * Provides common JOIN clause methods for query builders
 *
 * @package MulerTech\Database\Query\Builder\Traits
 * @author Sébastien Muler
 */
trait JoinClauseTrait
{
    /**
     * @var JoinClauseBuilder
     */
    protected JoinClauseBuilder $joinBuilder;

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
}
