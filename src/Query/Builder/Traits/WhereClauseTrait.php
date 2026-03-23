<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;

/**
 * Trait WhereClauseTrait.
 *
 * Provides common WHERE clause methods for query builders
 *
 * @author Sébastien Muler
 */
trait WhereClauseTrait
{
    protected WhereClauseBuilder $whereBuilder;

    /**
     * @param string|int|float|bool|null $value
     */
    public function where(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->add($column, $value, $operator, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param string|int|float|bool|null $value
     */
    public function whereEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->equal($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param string|int|float|bool|null $value
     */
    public function whereNotEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->notEqual($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param string|int|float|bool|null $value
     */
    public function whereGreaterThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->greaterThan($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereLessThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->lessThan($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereGreaterOrEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->greaterOrEqual($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereNotGreaterThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->notGreaterThan($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereLessOrEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->lessOrEqual($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereNotLessThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $this->whereBuilder->notLessThan($column, $value, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereLike(
        string $column,
        mixed $pattern = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $patternStr = match (true) {
            is_string($pattern) => $pattern,
            is_null($pattern) => '',
            is_scalar($pattern) => (string) $pattern,
            default => throw new \InvalidArgumentException('Pattern must be a string or scalar value'),
        };
        $this->whereBuilder->like($column, $patternStr, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereNotLike(
        string $column,
        mixed $pattern = null,
        LinkOperator $link = LinkOperator::AND,
    ): self {
        $patternStr = match (true) {
            is_string($pattern) => $pattern,
            is_null($pattern) => '',
            is_scalar($pattern) => (string) $pattern,
            default => throw new \InvalidArgumentException('Pattern must be a string or scalar value'),
        };
        $this->whereBuilder->notLike($column, $patternStr, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param array<int, mixed>|SelectBuilder $values
     */
    public function whereIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->in($column, $values, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param array<int, mixed>|SelectBuilder $values
     */
    public function whereNotIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->notIn($column, $values, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->between($column, $start, $end, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereNotBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->notBetween($column, $start, $end, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->isNull($column, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereNotNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->isNotNull($column, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function whereRaw(string $rawCondition, array $parameters = [], LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->raw($rawCondition, $parameters, $link);
        $this->isDirty = true;

        return $this;
    }

    /**
     * @param callable(WhereClauseBuilder): void $callback
     */
    public function whereGroup(callable $callback, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->group($callback, $link);
        $this->isDirty = true;

        return $this;
    }

    public function whereExists(SelectBuilder $subQuery, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->exists($subQuery, $link);
        $this->isDirty = true;

        return $this;
    }
}
