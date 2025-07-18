<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Clause;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Query\SelectBuilder;
use MulerTech\Database\Relational\Sql\ComparisonOperator;
use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\Raw;
use MulerTech\Database\Relational\Sql\SqlOperator;

/**
 * Class WhereClauseBuilder
 *
 * Unified WHERE clause building functionality
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class WhereClauseBuilder
{
    use SqlFormatterTrait;

    /**
     * @var array<int, array{condition: string, link: LinkOperator}>
     */
    private array $conditions = [];

    /**
     * @var QueryParameterBag
     */
    private QueryParameterBag $parameterBag;

    /**
     * @var array<int, WhereClauseBuilder>
     */
    private array $nestedGroups = [];

    /**
     * @param QueryParameterBag $parameterBag
     */
    public function __construct(QueryParameterBag $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param ComparisonOperator|SqlOperator $operator
     * @param LinkOperator $link
     * @return self
     */
    public function add(
        string $column,
        mixed $value,
        ComparisonOperator|SqlOperator $operator = ComparisonOperator::EQUAL,
        LinkOperator $link = LinkOperator::AND
    ): self {
        $condition = $this->buildCondition($column, $value, $operator);
        $this->conditions[] = ['condition' => $condition, 'link' => $link];
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function equal(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::EQUAL, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function notEqual(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::NOT_EQUAL, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function greaterThan(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::GREATER_THAN, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function lessThan(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::LESS_THAN, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function greaterOrEqual(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::GREATER_THAN_OR_EQUAL, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function notGreaterThan(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::NOT_GREATER_THAN, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function lessOrEqual(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::LESS_THAN_OR_EQUAL, $link);
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param LinkOperator $link
     * @return self
     */
    public function notLessThan(string $column, mixed $value, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $value, ComparisonOperator::NOT_LESS_THAN, $link);
    }

    /**
     * @param string $column
     * @param string $pattern
     * @param LinkOperator $link
     * @return self
     */
    public function like(string $column, string $pattern, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $pattern, SqlOperator::LIKE, $link);
    }

    /**
     * @param string $column
     * @param string $pattern
     * @param LinkOperator $link
     * @return self
     */
    public function notLike(string $column, string $pattern, LinkOperator $link = LinkOperator::AND): self
    {
        return $this->add($column, $pattern, SqlOperator::NOT_LIKE, $link);
    }

    /**
     * @param string $column
     * @param array<int, mixed>|SelectBuilder $values
     * @param LinkOperator $link
     * @return self
     */
    public function in(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        if ($values instanceof SelectBuilder) {
            $condition = $this->formatIdentifier($column) . ' IN (' . $values->toSql() . ')';
        } elseif (empty($values)) {
            // IN with empty array is always false
            $condition = '1 = 0';
        } else {
            $placeholders = [];
            foreach ($values as $value) {
                $placeholders[] = $this->parameterBag->add($value);
            }
            $condition = $this->formatIdentifier($column) . ' IN (' . implode(', ', $placeholders) . ')';
        }

        $this->conditions[] = ['condition' => $condition, 'link' => $link];
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, mixed>|SelectBuilder $values
     * @param LinkOperator $link
     * @return self
     */
    public function notIn(string $column, array|SelectBuilder $values, LinkOperator $link = LinkOperator::AND): self
    {
        if ($values instanceof SelectBuilder) {
            $condition = $this->formatIdentifier($column) . ' NOT IN (' . $values->toSql() . ')';
        } elseif (empty($values)) {
            // NOT IN with empty array is always true
            $condition = '1 = 1';
        } else {
            $placeholders = [];
            foreach ($values as $value) {
                $placeholders[] = $this->parameterBag->add($value);
            }
            $condition = $this->formatIdentifier($column) . ' NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $this->conditions[] = ['condition' => $condition, 'link' => $link];
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param LinkOperator $link
     * @return self
     */
    public function between(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $startPlaceholder = $this->parameterBag->add($start);
        $endPlaceholder = $this->parameterBag->add($end);

        $condition = $this->formatIdentifier($column) . ' BETWEEN ' . $startPlaceholder . ' AND ' . $endPlaceholder;
        $this->conditions[] = ['condition' => $condition, 'link' => $link];

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $start
     * @param mixed $end
     * @param LinkOperator $link
     * @return self
     */
    public function notBetween(string $column, mixed $start, mixed $end, LinkOperator $link = LinkOperator::AND): self
    {
        $startPlaceholder = $this->parameterBag->add($start);
        $endPlaceholder = $this->parameterBag->add($end);

        $condition = $this->formatIdentifier($column) . ' NOT BETWEEN ' . $startPlaceholder . ' AND ' . $endPlaceholder;
        $this->conditions[] = ['condition' => $condition, 'link' => $link];

        return $this;
    }

    /**
     * @param string $column
     * @param LinkOperator $link
     * @return self
     */
    public function isNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $condition = $this->formatIdentifier($column) . ' IS NULL';
        $this->conditions[] = ['condition' => $condition, 'link' => $link];
        return $this;
    }

    /**
     * @param string $column
     * @param LinkOperator $link
     * @return self
     */
    public function isNotNull(string $column, LinkOperator $link = LinkOperator::AND): self
    {
        $condition = $this->formatIdentifier($column) . ' IS NOT NULL';
        $this->conditions[] = ['condition' => $condition, 'link' => $link];
        return $this;
    }

    /**
     * @param string $rawCondition
     * @param array<string, mixed> $parameters
     * @param LinkOperator $link
     * @return self
     */
    public function raw(string $rawCondition, array $parameters = [], LinkOperator $link = LinkOperator::AND): self
    {
        foreach ($parameters as $key => $value) {
            $this->parameterBag->addNamed($key, $value);
        }

        $this->conditions[] = ['condition' => $rawCondition, 'link' => $link];
        return $this;
    }

    /**
     * @param callable(WhereClauseBuilder): void $callback
     * @param LinkOperator $link
     * @return self
     */
    public function group(callable $callback, LinkOperator $link = LinkOperator::AND): self
    {
        $group = new self($this->parameterBag);
        $callback($group);

        if (!$group->isEmpty()) {
            $this->nestedGroups[] = $group;
            $this->conditions[] = ['condition' => '(' . $group->toSql() . ')', 'link' => $link];
        }

        return $this;
    }

    /**
     * @param SelectBuilder $subQuery
     * @param LinkOperator $link
     * @return self
     */
    public function exists(SelectBuilder $subQuery, LinkOperator $link = LinkOperator::AND): self
    {
        $condition = 'EXISTS (' . $subQuery->toSql() . ')';
        $this->conditions[] = ['condition' => $condition, 'link' => $link];
        return $this;
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $sql = '';
        foreach ($this->conditions as $index => $item) {
            if ($index === 0) {
                $sql = $item['condition'];
            } else {
                $sql .= ' ' . $item['link']->value . ' ' . $item['condition'];
            }
        }

        return $sql;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->conditions);
    }

    /**
     * @return self
     */
    public function clear(): self
    {
        $this->conditions = [];
        $this->nestedGroups = [];
        return $this;
    }

    /**
     * @param string $column
     * @param mixed $value
     * @param ComparisonOperator|SqlOperator $operator
     * @return string
     */
    private function buildCondition(string $column, mixed $value, ComparisonOperator|SqlOperator $operator): string
    {
        $formattedColumn = $this->formatIdentifier($column);

        if ($value instanceof Raw) {
            return $formattedColumn . ' ' . $operator->value . ' ' . $value->getValue();
        }

        if ($value === null && in_array($operator, [ComparisonOperator::EQUAL, ComparisonOperator::NOT_EQUAL], true)) {
            return $formattedColumn . ($operator === ComparisonOperator::EQUAL ? ' IS NULL' : ' IS NOT NULL');
        }

        $placeholder = $this->parameterBag->add($value);
        return $formattedColumn . ' ' . $operator->value . ' ' . $placeholder;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->conditions);
    }

    /**
     * @param WhereClauseBuilder $other
     * @return self
     */
    public function merge(WhereClauseBuilder $other): self
    {
        foreach ($other->conditions as $condition) {
            $this->conditions[] = $condition;
        }

        foreach ($other->nestedGroups as $group) {
            $this->nestedGroups[] = $group;
        }

        return $this;
    }
}
