<?php


namespace MulerTech\Database\Relational\Sql;

use ScopeComparison;

/**
 * Class SqlOperations is one or multiple operations, for example :
 * age > 25 AND size > 180cm
 * @package MulerTech\Database\SQL
 * @author Sébastien Muler
 */
class SqlOperations
{

    /**
     * Operation index for operations variable.
     */
    public const OPERATION_INDEX = 'operation';

    /**
     * Link index for operations variable.
     */
    public const LINK_INDEX = 'link';

    /**
     * @var string $operation
     */
    private $operation = '';

    /**
     * @var array $operations
     */
    private $operations = [];

    /**
     * SqlOperations constructor.
     * @param string|self|null $operation
     */
    public function __construct(SqlOperations|string $operation = null)
    {
        if (!is_null($operation)) {
            $this->addOperation($operation);
        }
    }

    /**
     * @param string $operation
     */
    public function manualOperation(string $operation): void
    {
        $this->operation = $operation;
    }

    /**
     * @param string|self $operation
     * @param LinkOperator $link
     * @return SqlOperations
     */
    public function addOperation(SqlOperations|string $operation, LinkOperator $link = LinkOperator::AND): SqlOperations
    {
        $this->operations[] = [self::OPERATION_INDEX => $operation, self::LINK_INDEX => $link];

        return $this;
    }

    /**
     * @param string|self ...$operations
     * @return SqlOperations
     */
    public function and(string|SqlOperations ...$operations): SqlOperations
    {
        foreach ($operations as $operation) {
            $this->addOperation($operation);
        }

        return $this;
    }

    /**
     * @param string|self ...$operations
     * @return SqlOperations
     */
    public function andNot(string|SqlOperations ...$operations): SqlOperations
    {
        foreach ($operations as $operation) {
            $this->addOperation($operation, LinkOperator::AND_NOT);
        }
        return $this;
    }

    /**
     * @param string|self ...$operations
     * @return SqlOperations
     */
    public function or(string|SqlOperations ...$operations): SqlOperations
    {
        foreach ($operations as $operation) {
            $this->addOperation($operation, LinkOperator::OR);
        }
        return $this;
    }

    /**
     * @param string|self ...$operations
     * @return SqlOperations
     */
    public function orNot(string|SqlOperations ...$operations): SqlOperations
    {
        foreach ($operations as $operation) {
            $this->addOperation($operation, LinkOperator::OR_NOT);
        }
        return $this;
    }

    /**
     * @param string $column
     * @param array|string|QueryBuilder $list
     * @param LinkOperator $link
     * @return SqlOperations
     */
    public function in(
        string $column,
        array|string|QueryBuilder $list,
        LinkOperator $link = LinkOperator::AND
    ): SqlOperations {
        if (is_array($list)) {
            $stringList = implode('\', \'', $list);
            $stringList = '\'' . $stringList . '\'';
        }

        $this->addOperation(
            $column .
            ' ' .
            SqlOperator::IN->value .
            ' (' . ((!empty($stringList)) ? $stringList : $list) .
            ')',
            $link
        );

        return $this;
    }

    /**
     * @param string $column
     * @param array|string|QueryBuilder $list
     * @param LinkOperator $link
     * @return SqlOperations
     */
    public function notIn(
        string $column,
        array|string|QueryBuilder $list,
        LinkOperator $link = LinkOperator::AND
    ): SqlOperations {
        if (is_array($list)) {
            $stringList = implode('\', \'', $list);
            $stringList = '\'' . $stringList . '\'';
        }

        $this->addOperation(
            $column .
            ' ' .
            SqlOperator::IN->not()->value .
            ' (' . ((!empty($stringList)) ? $stringList : $list) .
            ')',
            $link
        );

        return $this;
    }

    /**
     * @return string
     */
    public function generateOperation(): string
    {
        if (!empty($this->operation)) {
            return ' ' . $this->operation;
        }

        $operations = $this->operations;
        if (empty($operations)) {
            return '';
        }

        $firstOperation = array_shift($operations);
        if ($firstOperation[self::OPERATION_INDEX] instanceof $this) {
            $firstOperation[self::OPERATION_INDEX] = '(' . $firstOperation[self::OPERATION_INDEX]->generateOperation(
                ) . ')';
        }

        //For the first operation just leave the NOT operator (not AND NOT).
        $firstLink = $firstOperation[self::LINK_INDEX]->value;
        if (stripos($firstLink, 'not') !== false) {
            $firstLink = ' ' . SqlOperator::NOT->value . ' ';
        } else {
            $firstLink = ' ';
        }

        $this->operation .= $firstLink . $firstOperation[self::OPERATION_INDEX];
        if (!empty($operations)) {
            foreach ($operations as $operation) {
                if ($operation[self::OPERATION_INDEX] instanceof $this) {
                    $operation[self::OPERATION_INDEX] = '(' . $operation[self::OPERATION_INDEX] . ')';
                }
                $this->operation .= ' ' . $operation[self::LINK_INDEX]->value . ' ' . $operation[self::OPERATION_INDEX];
            }
        }

        return $this->operation;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function equal(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::EQUAL,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    public static function notEqual(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::NOT_EQUAL,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function greater(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::GREATER_THAN,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function notGreater(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::NOT_GREATER_THAN,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function less(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::LESS_THAN,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function notLess(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::NOT_LESS_THAN,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function greaterEqual(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::GREATER_THAN_OR_EQUAL,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function lessEqual(SqlOperations|string $first, SqlOperations|string $second, ScopeComparison $allAnySome = null): string
    {
        return self::generateComparisonOperation(
            $first,
            $second,
            ComparisonOperator::LESS_THAN_OR_EQUAL,
            $allAnySome
        );
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function add($first, $second): string
    {
        return $first . ArithmeticOperator::ADD->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function subtract($first, $second): string
    {
        return $first . ArithmeticOperator::SUBTRACT->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function multiply($first, $second): string
    {
        return $first . ArithmeticOperator::MULTIPLY->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function divide($first, $second): string
    {
        return $first . ArithmeticOperator::DIVIDE->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function modulo($first, $second): string
    {
        return $first . ArithmeticOperator::MODULO->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitAnd($first, $second): string
    {
        return $first . BitwiseOperator::BITWISE_AND->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitOr($first, $second): string
    {
        return $first . BitwiseOperator::BITWISE_OR->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitExclusiveOr($first, $second): string
    {
        return $first . BitwiseOperator::BITWISE_XOR->value . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitNot($first, $second): string
    {
        return $first . BitwiseOperator::BITWISE_NOT->value . $second;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        if (!empty($this->operation)) {
            return $this->operation[0] === ' ' ? $this->operation : ' ' . $this->operation;
        }

        return $this->generateOperation();
    }

    /**
     * @param string $first
     * @param string $second
     * @param ComparisonOperator $sqlComparisonOperator
     * @param ScopeComparison|null $allAnySome
     * @return string
     */
    private static function generateComparisonOperation(
        string $first,
        string $second,
        ComparisonOperator $sqlComparisonOperator,
        ScopeComparison $allAnySome = null
    ): string {
        $allAnySomeString = ($allAnySome !== null) ? ' ' . $allAnySome->value . ' ' : '';

        return $first . $sqlComparisonOperator->value . $allAnySomeString . $second;
    }
}