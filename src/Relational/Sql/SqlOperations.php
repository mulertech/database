<?php

namespace MulerTech\Database\Relational\Sql;

/**
 * Class SqlOperations
 *
 * Represents one or more SQL operations, for example:
 * age > 25 AND size > 180cm
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SqlOperations
{
    public const string OPERATION_INDEX = 'operation';
    public const string LINK_INDEX = 'link';

    private string $operation = '';

    /** @var array<int, array{operation: SqlOperations|string, link: LinkOperator}> */
    private array $operations = [];

    /**
     * @param SqlOperations|string|null $operation
     * @return void
     */
    public function __construct(SqlOperations|string|null $operation = null)
    {
        if ($operation !== null) {
            $this->addOperation($operation);
        }
    }

    /**
     * @param string $operation
     * @return void
     */
    public function manualOperation(string $operation): void
    {
        $this->operation = $operation;
    }

    /**
     * @param SqlOperations|string $operation
     * @param LinkOperator $link
     * @return self
     */
    public function addOperation(SqlOperations|string $operation, LinkOperator $link = LinkOperator::AND): self
    {
        $this->operations[] = [self::OPERATION_INDEX => $operation, self::LINK_INDEX => $link];
        return $this;
    }

    /**
     * @param string|SqlOperations ...$operations
     * @return self
     */
    public function and(string|SqlOperations ...$operations): self
    {
        return $this->addMultipleOperations(LinkOperator::AND, ...$operations);
    }

    /**
     * @param string|SqlOperations ...$operations
     * @return self
     */
    public function andNot(string|SqlOperations ...$operations): self
    {
        return $this->addMultipleOperations(LinkOperator::AND_NOT, ...$operations);
    }

    /**
     * @param string|SqlOperations ...$operations
     * @return self
     */
    public function or(string|SqlOperations ...$operations): self
    {
        return $this->addMultipleOperations(LinkOperator::OR, ...$operations);
    }

    /**
     * @param string|SqlOperations ...$operations
     * @return self
     */
    public function orNot(string|SqlOperations ...$operations): self
    {
        return $this->addMultipleOperations(LinkOperator::OR_NOT, ...$operations);
    }

    /**
     * @param LinkOperator $link
     * @param string|SqlOperations ...$operations
     * @return self
     */
    private function addMultipleOperations(LinkOperator $link, string|SqlOperations ...$operations): self
    {
        foreach ($operations as $operation) {
            $this->addOperation($operation, $link);
        }
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, string>|string|QueryBuilder $list
     * @param LinkOperator $link
     * @return self
     */
    public function in(string $column, array|string|QueryBuilder $list, LinkOperator $link = LinkOperator::AND): self
    {
        $this->addOperation($this->formatInOperation($column, $list, SqlOperator::IN), $link);
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, string>|string|QueryBuilder $list
     * @param LinkOperator $link
     * @return self
     */
    public function notIn(string $column, array|string|QueryBuilder $list, LinkOperator $link = LinkOperator::AND): self
    {
        $this->addOperation($this->formatInOperation($column, $list, SqlOperator::IN->not()), $link);
        return $this;
    }

    /**
     * @param string $column
     * @param array<int, string>|string|QueryBuilder $list
     * @param SqlOperator $operator
     * @return string
     */
    private function formatInOperation(string $column, array|string|QueryBuilder $list, SqlOperator $operator): string
    {
        if ($list instanceof QueryBuilder) {
            return "$column $operator->value ($list)";
        }

        $formattedList = is_array($list) ? '\'' . implode('\', \'', $list) . '\'' : $list;
        return "$column $operator->value ($formattedList)";
    }

    /**
     * @return string
     */
    public function generateOperation(): string
    {
        if (!empty($this->operation)) {
            return ' ' . $this->operation;
        }

        if (empty($this->operations)) {
            return '';
        }

        $firstOperation = array_shift($this->operations);
        $this->operation = $this->formatOperation($firstOperation, true);

        foreach ($this->operations as $operation) {
            $this->operation .= $this->formatOperation($operation);
        }

        return $this->operation;
    }

    /**
     * @param array{operation: SqlOperations|string, link: LinkOperator} $operation
     * @param bool $isFirst
     * @return string
     */
    private function formatOperation(array $operation, bool $isFirst = false): string
    {
        $link = $isFirst && stripos($operation[self::LINK_INDEX]->value, 'not') === false
            ? ' '
            : ' ' . $operation[self::LINK_INDEX]->value . ' ';

        $op = $operation[self::OPERATION_INDEX];
        if ($op instanceof self) {
            $opStr = trim($op->generateOperation());
            $op = '(' . $opStr . ')';
        }

        return $link . $op;
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function equal(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation($first, $second, ComparisonOperator::EQUAL, $allAnySome);
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function notEqual(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation($first, $second, ComparisonOperator::NOT_EQUAL, $allAnySome);
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function greater(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::GREATER_THAN,
            $allAnySome
        );
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function notGreater(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::NOT_GREATER_THAN,
            $allAnySome
        );
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function less(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation($first, $second, ComparisonOperator::LESS_THAN, $allAnySome);
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function notLess(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::NOT_LESS_THAN,
            $allAnySome
        );
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function greaterEqual(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::GREATER_THAN_OR_EQUAL,
            $allAnySome
        );
    }

    /**
     * @param SqlOperations|string $first
     * @param SqlOperations|string $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function lessEqual(
        SqlOperations|string $first,
        SqlOperations|string $second,
        ?ScopeComparison $allAnySome = null
    ): string {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::LESS_THAN_OR_EQUAL,
            $allAnySome
        );
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function add(string $first, string $second): string
    {
        return $first . ArithmeticOperator::ADD->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function subtract(string $first, string $second): string
    {
        return $first . ArithmeticOperator::SUBTRACT->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function multiply(string $first, string $second): string
    {
        return $first . ArithmeticOperator::MULTIPLY->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function divide(string $first, string $second): string
    {
        return $first . ArithmeticOperator::DIVIDE->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function modulo(string $first, string $second): string
    {
        return $first . ArithmeticOperator::MODULO->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function bitAnd(string $first, string $second): string
    {
        return $first . BitwiseOperator::BITWISE_AND->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function bitOr(string $first, string $second): string
    {
        return $first . BitwiseOperator::BITWISE_OR->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function bitExclusiveOr(string $first, string $second): string
    {
        return $first . BitwiseOperator::BITWISE_XOR->value . $second;
    }

    /**
     * @param string $first
     * @param string $second
     * @return string
     */
    public static function bitNot(string $first, string $second): string
    {
        return $first . BitwiseOperator::BITWISE_NOT->value . $second;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if (!empty($this->operation) && empty($this->operations)) {
            return str_starts_with($this->operation, ' ') ? $this->operation : ' ' . $this->operation;
        }
        return $this->generateOperation();
    }

    /**
     * @param string $first
     * @param string $second
     * @param ComparisonOperator $operator
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    private static function generateComparisonOperation(
        string $first,
        string $second,
        ComparisonOperator $operator,
        ?ScopeComparison $allAnySome = null
    ): string {
        $allAnySomeString = $allAnySome ? ' ' . $allAnySome->value . ' ' : '';
        return $first . $operator->value . $allAnySomeString . $second;
    }
}
