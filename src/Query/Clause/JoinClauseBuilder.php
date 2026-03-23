<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Clause;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\JoinType;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;

/**
 * Class JoinClauseBuilder.
 *
 * Centralized JOIN clause building functionality
 *
 * @author Sébastien Muler
 */
class JoinClauseBuilder
{
    use SqlFormatterTrait;

    /**
     * @var array<int, array{
     *       type: JoinType,
     *       table: string,
     *       alias: string|null,
     *       conditions: array<array{
     *         left: string,
     *         operator: SqlOperator|ComparisonOperator,
     *         right: string,
     *         link: LinkOperator
     *       }>
     *     }>
     */
    private array $joins = [];

    private QueryParameterBag $parameterBag;

    public function __construct(QueryParameterBag $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function add(JoinType $type, string $table, ?string $alias = null): JoinConditionBuilder
    {
        $index = count($this->joins);
        $this->joins[$index] = [
            'type' => $type,
            'table' => $table,
            'alias' => $alias,
            'conditions' => [],
        ];

        return new JoinConditionBuilder($this, $index);
    }

    public function inner(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::INNER, $table, $alias);
    }

    public function left(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::LEFT, $table, $alias);
    }

    public function right(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::RIGHT, $table, $alias);
    }

    public function cross(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::CROSS, $table, $alias);
    }

    /**
     * @param ComparisonOperator $operator
     * @param string|mixed       $rightColumn
     */
    public function addCondition(
        int $index,
        string $leftColumn,
        ComparisonOperator|SqlOperator $operator,
        mixed $rightColumn,
        LinkOperator $link = LinkOperator::AND,
    ): void {
        if (!isset($this->joins[$index])) {
            throw new \RuntimeException('Invalid join index: '.$index);
        }

        // Ensure right column is a string
        $rightColumnStr = match (true) {
            is_string($rightColumn) => $rightColumn,
            is_null($rightColumn) => '',
            is_scalar($rightColumn) => (string) $rightColumn,
            default => throw new \InvalidArgumentException('Right column must be a string or scalar value'),
        };

        $this->joins[$index]['conditions'][] = [
            'left' => $leftColumn,
            'operator' => $operator,
            'right' => $rightColumnStr,
            'link' => $link,
        ];
    }

    public function toSql(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = [];

        foreach ($this->joins as $join) {
            $joinSql = $join['type']->value.' JOIN ';
            $joinSql .= $this->formatTable($join['table'], $join['alias']);

            if (!empty($join['conditions'])) {
                $joinSql .= ' ON '.$this->buildConditions($join['conditions']);
            }

            $sql[] = $joinSql;
        }

        return implode(' ', $sql);
    }

    /**
     * @param array<array{
     *     left: string,
     *     operator: SqlOperator|ComparisonOperator,
     *     right: string|mixed,
     *     link: LinkOperator}> $conditions
     */
    private function buildConditions(array $conditions): string
    {
        $sql = '';

        foreach ($conditions as $index => $condition) {
            if ($index > 0) {
                $sql .= ' '.$condition['link']->value.' ';
            }

            $left = $this->formatIdentifier($condition['left']);

            // Check if right side is a column reference or a value
            $right = (is_string($condition['right']) && $this->isColumnReference($condition['right']))
                ? $this->formatIdentifier($condition['right'])
                : $this->parameterBag->add($condition['right']);

            $sql .= $left.' '.$condition['operator']->value.' '.$right;
        }

        return $sql;
    }

    private function isColumnReference(string $value): bool
    {
        // Check if value looks like a column reference (table.column or just column name)
        return 1 === preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $value)
            && !is_numeric($value)
            && !in_array(strtoupper($value), ['TRUE', 'FALSE', 'NULL'], true);
    }

    public function isEmpty(): bool
    {
        return empty($this->joins);
    }

    public function count(): int
    {
        return count($this->joins);
    }

    public function clear(): self
    {
        $this->joins = [];

        return $this;
    }

    /**
     * @return array<int, array{type: JoinType, table: string, alias: string|null}>
     */
    public function getJoins(): array
    {
        return array_map(static function ($join) {
            return [
                'type' => $join['type'],
                'table' => $join['table'],
                'alias' => $join['alias'],
            ];
        }, $this->joins);
    }
}
