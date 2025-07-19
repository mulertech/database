<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Clause;

use MulerTech\Database\Core\Parameters\QueryParameterBag;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Query\Types\ComparisonOperator;
use MulerTech\Database\Query\Types\JoinType;
use MulerTech\Database\Query\Types\LinkOperator;
use MulerTech\Database\Query\Types\SqlOperator;
use RuntimeException;

/**
 * Class JoinClauseBuilder
 *
 * Centralized JOIN clause building functionality
 *
 * @package MulerTech\Database
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

    /**
     * @var QueryParameterBag
     */
    private QueryParameterBag $parameterBag;

    /**
     * @param QueryParameterBag $parameterBag
     */
    public function __construct(QueryParameterBag $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * @param JoinType $type
     * @param string $table
     * @param string|null $alias
     * @return JoinConditionBuilder
     */
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

    /**
     * @param string $table
     * @param string|null $alias
     * @return JoinConditionBuilder
     */
    public function inner(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::INNER, $table, $alias);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return JoinConditionBuilder
     */
    public function left(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::LEFT, $table, $alias);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return JoinConditionBuilder
     */
    public function right(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::RIGHT, $table, $alias);
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return JoinConditionBuilder
     */
    public function cross(string $table, ?string $alias = null): JoinConditionBuilder
    {
        return $this->add(JoinType::CROSS, $table, $alias);
    }

    /**
     * @param int $index
     * @param string $leftColumn
     * @param ComparisonOperator $operator
     * @param string|mixed $rightColumn
     * @param LinkOperator $link
     * @return void
     */
    public function addCondition(
        int $index,
        string $leftColumn,
        ComparisonOperator|SqlOperator $operator,
        mixed $rightColumn,
        LinkOperator $link = LinkOperator::AND
    ): void {
        if (!isset($this->joins[$index])) {
            throw new RuntimeException('Invalid join index: ' . $index);
        }

        // Ensure right column is a string
        $rightColumnStr = is_string($rightColumn) ? $rightColumn : (string)$rightColumn;

        $this->joins[$index]['conditions'][] = [
            'left' => $leftColumn,
            'operator' => $operator,
            'right' => $rightColumnStr,
            'link' => $link,
        ];
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        $sql = [];

        foreach ($this->joins as $join) {
            $joinSql = $join['type']->value . ' JOIN ';
            $joinSql .= $this->formatTable($join['table'], $join['alias']);

            if (!empty($join['conditions'])) {
                $joinSql .= ' ON ' . $this->buildConditions($join['conditions']);
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
     * @return string
     */
    private function buildConditions(array $conditions): string
    {
        $sql = '';

        foreach ($conditions as $index => $condition) {
            if ($index > 0) {
                $sql .= ' ' . $condition['link']->value . ' ';
            }

            $left = $this->formatIdentifier($condition['left']);

            // Check if right side is a column reference or a value
            if (is_string($condition['right']) && $this->isColumnReference($condition['right'])) {
                $right = $this->formatIdentifier($condition['right']);
            } else {
                $right = $this->parameterBag->add($condition['right']);
            }

            $sql .= $left . ' ' . $condition['operator']->value . ' ' . $right;
        }

        return $sql;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function isColumnReference(string $value): bool
    {
        // Check if value looks like a column reference (table.column or just column name)
        return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $value) === 1
            && !is_numeric($value)
            && !in_array(strtoupper($value), ['TRUE', 'FALSE', 'NULL'], true);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->joins);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->joins);
    }

    /**
     * @return self
     */
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
