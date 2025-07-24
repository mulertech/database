<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

use InvalidArgumentException;
use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;

/**
 * Trait WhereClauseTrait
 *
 * Provides common WHERE clause methods for query builders
 *
 * @package MulerTech\Database\Query\Builder\Traits
 * @author Sébastien Muler
 */
trait WhereClauseTrait
{
    /**
     * @var WhereClauseBuilder
     */
    protected WhereClauseBuilder $whereBuilder;

    /**
     * @param string $column
     * @param mixed $value
     * @param ComparisonOperator|SqlOperator $operator
     * @param LinkOperator $link
     * @return self
     */
    public function where(
        string $column,
        mixed $value = null,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->add($column, $value, $operator, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->equal($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notEqual($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereGreaterThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->greaterThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereLessThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->lessThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereGreaterOrEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->greaterOrEqual($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotGreaterThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notGreaterThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereLessOrEqual(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->lessOrEqual($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotLessThan(
        string $column,
        mixed $value = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $this->whereBuilder->notLessThan($column, $value, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $pattern
     * @param LinkOperator $link
     * @return self
     */
    public function whereLike(
        string $column,
        mixed $pattern = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $patternStr = match (true) {
            is_string($pattern) => $pattern,
            is_null($pattern) => '',
            is_scalar($pattern) => (string)$pattern,
            default => throw new InvalidArgumentException('Pattern must be a string or scalar value')
        };
        $this->whereBuilder->like($column, $patternStr, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $pattern
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotLike(
        string $column,
        mixed $pattern = null,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $patternStr = match (true) {
            is_string($pattern) => $pattern,
            is_null($pattern) => '',
            is_scalar($pattern) => (string)$pattern,
            default => throw new InvalidArgumentException('Pattern must be a string or scalar value')
        };
        $this->whereBuilder->notLike($column, $patternStr, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed>|SelectBuilder $values
     * @param LinkOperator $link
     * @return self
     */
    public function whereIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->in($column, $values, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed>|SelectBuilder $values
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->notIn($column, $values, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param LinkOperator $link
     * @return self
     */
    public function whereBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->between($column, $start, $end, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->notBetween($column, $start, $end, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param LinkOperator $link
     * @return self
     */
    public function whereNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->isNull($column, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $column
     * @param LinkOperator $link
     * @return self
     */
    public function whereNotNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->isNotNull($column, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param string $rawCondition
     * @param array<string, mixed> $parameters
     * @param LinkOperator $link
     * @return self
     */
    public function whereRaw(string $rawCondition, array $parameters = [], LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->raw($rawCondition, $parameters, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param callable(WhereClauseBuilder): void $callback
     * @param LinkOperator $link
     * @return self
     */
    public function whereGroup(callable $callback, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->group($callback, $link);
        $this->isDirty = true;
        return $this;
    }

    /**
     * @param SelectBuilder $subQuery
     * @param LinkOperator $link
     * @return self
     */
    public function whereExists(SelectBuilder $subQuery, LinkOperator $link = LinkOperator::AND): self
    {
        $this->whereBuilder->exists($subQuery, $link);
        $this->isDirty = true;
        return $this;
    }
}
