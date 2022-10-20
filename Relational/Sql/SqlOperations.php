<?php


namespace mtphp\Database\Relational\Sql;

/**
 * Class SqlOperations is one or multiple operations, for example :
 * age > 25 AND size > 180cm
 * @package mtphp\Database\SQL
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
     * @param string|self $operation
     */
    public function __construct($operation = null)
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
     * @param string $link
     * @return SqlOperations
     */
    public function addOperation($operation, string $link = SqlOperators::AND_OPERATOR): SqlOperations
    {
        $this->operations[] = [self::OPERATION_INDEX => $operation, self::LINK_INDEX => $link];
        return $this;
    }

    /**
     * @param string|self ...$operation
     * @return SqlOperations
     */
    public function and(...$operation): SqlOperations
    {
        foreach ($operation as $op) {
            $this->addOperation($op);
        }
        return $this;
    }

    /**
     * @param string|self ...$operation
     * @return SqlOperations
     */
    public function andNot(...$operation): SqlOperations
    {
        foreach ($operation as $op) {
            $this->addOperation($op, SqlOperators::AND_OPERATOR . ' ' . SqlOperators::NOT_OPERATOR);
        }
        return $this;
    }

    /**
     * @param string|self ...$operation
     * @return SqlOperations
     */
    public function or(...$operation): SqlOperations
    {
        foreach ($operation as $op) {
            $this->addOperation($op, SqlOperators::OR_OPERATOR);
        }
        return $this;
    }

    /**
     * @param string|self ...$operation
     * @return SqlOperations
     */
    public function orNot(...$operation): SqlOperations
    {
        foreach ($operation as $op) {
            $this->addOperation($op, SqlOperators::OR_OPERATOR . ' ' . SqlOperators::NOT_OPERATOR);
        }
        return $this;
    }

    /**
     * @param string $column
     * @param array|QueryBuilder|string $list
     * @param string $link
     * @return SqlOperations
     */
    public function in(string $column, $list, string $link = SqlOperators::AND_OPERATOR): SqlOperations
    {
        if (is_array($list)) {
            $stringList = implode('\', \'', $list);
            $stringList = '\'' . $stringList . '\'';
        }
        $this->addOperation(
            $column . ' ' . SqlOperators::IN_OPERATOR . ' (' . ((!empty($stringList)) ? $stringList : $list) . ')',
            $link
        );
        return $this;
    }

    /**
     * @param string $column
     * @param array|QueryBuilder|string $list
     * @param string $link
     * @return SqlOperations
     */
    public function notIn(string $column, $list, string $link = SqlOperators::AND_OPERATOR): SqlOperations
    {
        if (is_array($list)) {
            $stringList = implode('\', \'', $list);
            $stringList = '\'' . $stringList . '\'';
        }
        $this->addOperation(
            $column . ' ' . SqlOperators::NOT_OPERATOR . ' ' . SqlOperators::IN_OPERATOR . ' (' . ((!empty($stringList)) ? $stringList : $list) . ')',
            $link
        );
        return $this;
    }

    /**
     * @return string
     */
    public function generateOperation(): string
    {
        if (!empty($operations = $this->operations)) {
            $firstOperation = array_shift($operations);
            if ($firstOperation[self::OPERATION_INDEX] instanceof $this) {
                $firstOperation[self::OPERATION_INDEX] = '(' . $firstOperation[self::OPERATION_INDEX]->generateOperation(
                    ) . ')';
            }
            //For the first operation just leave the NOT operator (not AND NOT).
            if (empty($this->operation)) {
                if (stripos($firstOperation[self::LINK_INDEX], 'not') !== false) {
                    $firstOperation[self::LINK_INDEX] = SqlOperators::NOT_OPERATOR;
                } else {
                    $firstOperation[self::LINK_INDEX] = '';
                }
            }
            $this->operation .= $firstOperation[self::LINK_INDEX] . ' ' . $firstOperation[self::OPERATION_INDEX];
            if (!empty($operations)) {
                foreach ($operations as $operation) {
                    if ($operation[self::OPERATION_INDEX] instanceof $this) {
                        $operation[self::OPERATION_INDEX] = '(' . $operation[self::OPERATION_INDEX] . ')';
                    }
                    $this->operation .= ' ' . $operation[self::LINK_INDEX] . ' ' . $operation[self::OPERATION_INDEX];
                }
            }
        }

        if ($this->operation[0] !== ' ') {
            $this->operation = ' ' . $this->operation;
        }
        return $this->operation;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function equal($first, $second, bool $any = false): string
    {
        $anyString = ($any) ? ' ' . SqlOperators::ANY_OPERATOR . ' ' : '';
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $anyString . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function notEqual($first, $second, bool $any = false): string
    {
        $anyString = ($any) ? ' ' . SqlOperators::ANY_OPERATOR . ' ' : '';
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__][0] . $anyString . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function greater($first, $second, bool $any = false): string
    {
        $anyString = ($any) ? ' ' . SqlOperators::ANY_OPERATOR . ' ' : '';
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $anyString . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function notGreater($first, $second): string
    {
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function less($first, $second, bool $any = false): string
    {
        $anyString = ($any) ? ' ' . SqlOperators::ANY_OPERATOR . ' ' : '';
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $anyString . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function notLess($first, $second): string
    {
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function greaterEqual($first, $second, bool $any = false): string
    {
        $anyString = ($any) ? ' ' . SqlOperators::ANY_OPERATOR . ' ' : '';
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $anyString . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @param bool $any
     * @return string
     */
    public static function lessEqual($first, $second, bool $any = false): string
    {
        $anyString = ($any) ? ' ' . SqlOperators::ANY_OPERATOR . ' ' : '';
        return $first . SqlOperators::COMPARISON_OPERATORS[__FUNCTION__] . $anyString . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function add($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function addAssignment($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function subtract($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function subtractAssignment($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function multiply($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function multiplyAssignment($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function divide($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function divideAssignment($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function modulo($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function moduloAssignment($first, $second): string
    {
        return $first . SqlOperators::ARITHMETIC_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitAnd($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitAndAssignment($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitOr($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitOrAssignment($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitExclusiveOr($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitExclusiveOrAssignment($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @param string|self $first
     * @param string|self $second
     * @return string
     */
    public static function bitNot($first, $second): string
    {
        return $first . SqlOperators::BITWISE_OPERATORS[__FUNCTION__] . $second;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->generateOperation();
    }
}