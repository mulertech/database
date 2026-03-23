<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Clause;

use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\LinkOperator;

/**
 * Class JoinConditionBuilder.
 *
 * Fluent interface for building join conditions
 *
 * @author Sébastien Muler
 */
readonly class JoinConditionBuilder
{
    public function __construct(
        private JoinClauseBuilder $joinBuilder,
        private int $joinIndex,
    ) {
    }

    public function on(string $leftColumn, string $rightColumn): self
    {
        $this->joinBuilder->addCondition(
            $this->joinIndex,
            $leftColumn,
            ComparisonOperator::EQUAL,
            $rightColumn
        );

        return $this;
    }

    public function onCondition(string $leftColumn, ComparisonOperator $operator, mixed $rightColumn): self
    {
        $this->joinBuilder->addCondition(
            $this->joinIndex,
            $leftColumn,
            $operator,
            $rightColumn
        );

        return $this;
    }

    public function andOn(string $leftColumn, string $rightColumn): self
    {
        $this->joinBuilder->addCondition(
            $this->joinIndex,
            $leftColumn,
            ComparisonOperator::EQUAL,
            $rightColumn
        );

        return $this;
    }

    public function orOn(string $leftColumn, string $rightColumn): self
    {
        $this->joinBuilder->addCondition(
            $this->joinIndex,
            $leftColumn,
            ComparisonOperator::EQUAL,
            $rightColumn,
            LinkOperator::OR
        );

        return $this;
    }

    public function andOnCondition(string $leftColumn, ComparisonOperator $operator, mixed $rightColumn): self
    {
        $this->joinBuilder->addCondition(
            $this->joinIndex,
            $leftColumn,
            $operator,
            $rightColumn
        );

        return $this;
    }

    public function orOnCondition(string $leftColumn, ComparisonOperator $operator, mixed $rightColumn): self
    {
        $this->joinBuilder->addCondition(
            $this->joinIndex,
            $leftColumn,
            $operator,
            $rightColumn,
            LinkOperator::OR
        );

        return $this;
    }
}
