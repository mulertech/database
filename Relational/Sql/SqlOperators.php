<?php


namespace mtphp\Database\Relational\Sql;

/**
 * Class SqlOperators for SQL database.
 * @package mtphp\Database\SQL
 * @author Sébastien Muler
 */
final class SqlOperators
{

    /**
     * SqlOperators constructor, do not instantiate this class.
     */
    private function __construct()
    {
    }

    /**
     * AND operator.
     */
    public const AND_OPERATOR = 'AND';

    /**
     * OR operator.
     */
    public const OR_OPERATOR = 'OR';

    /**
     * NOT in the beginning of a comparison/logical operation.
     */
    public const NOT_OPERATOR = 'NOT';

    /**
     * IN or NOT IN : this operator need a column or a variable and list or child request.
     * For example : SELECT * FROM Customers WHERE City IN (SELECT City FROM Customers WHERE Country='UK');
     * For example : SELECT * FROM Customers WHERE City IN ('Paris','London'); (Case sensitive)
     */
    public const IN_OPERATOR = 'IN';

    /**
     * Any or some operator, they are the same operator.
     * This operator need a column, a comparison operator and a child request.
     * For example : SELECT * FROM Products WHERE Price < ANY (SELECT Price FROM Products WHERE Price > 50);
     */
    public const ANY_OPERATOR = 'ANY';
    public const SOME_OPERATOR = 'SOME';

    /**
     * BETWEEN or NOT BETWEEN : this operator need a column and two variable to compare.
     * For example : SELECT * FROM Products WHERE Price BETWEEN 50 AND 60;
     */
    private const BETWEEN_OPERATOR = 'BETWEEN';

    /**
     * This operator need only child request.
     * For example : SELECT * FROM products WHERE EXISTS (SELECT Price FROM Products WHERE Price > 50);
     */
    private const EXISTS_OPERATOR = 'EXISTS';

    /**
     * These operators need a column and a string.
     * For example : SELECT * FROM Customers WHERE City LIKE 'r%'; (Case insensitive)
     */
    private const LIKE_OPERATOR = ['LIKE', 'NOT LIKE'];

    /**
     * Comparison operators, need 2 expressions.
     */
    public const COMPARISON_OPERATORS = [
        'equal' => '=',
        'notEqual' => ['!=', '<>'],
        'greater' => '>',
        'notGreater' => '!>',
        'less' => '<',
        'notLess' => '!<',
        'greaterEqual' => '>=',
        'lessEqual' => '<='
    ];

    /**
     * Arithmetic operators, need 2 expressions.
     */
    public const ARITHMETIC_OPERATORS = [
        'add' => '+',
        'addAssignment' => '+',
        'subtract' => '-',
        'subtractAssignment' => '-',
        'multiply' => '*',
        'multiplyAssignment' => '*',
        'divide' => '/',
        'divideAssignment' => '/',
        'modulo' => '%',
        'moduloAssignment' => '%'
    ];

    /**
     * Bitwise operators, need 2 expressions except not which requires only one.
     */
    public const BITWISE_OPERATORS = [
        'bitAnd' => '&',
        'bitAndAssignment' => '&=',
        'bitOr' => '|',
        'bitOrAssignment' => '|=',
        'bitExclusiveOr' => '^',
        'bitExclusiveOrAssignment' => '^=',
        'bitNot' => '~'
    ];

    /**
     * List the reverse operator in database, for example :
     * ['Lisa' => 14, 'John' => 18, 'Elena' => 25, 'Nicolas' => 35]
     * Age = 14 : Lisa. The reverse : the others.
     * Age > 18 : Elena, Nicolas. The reverse (<=) : the others.
     */
    private const REVERSE_COMPARISON_OPERATOR = [
        '=' => '<>',
        '<>' => '=',
        '!=' => '=',
        '>' => '<=',
        '!>' => '>',
        '<' => '>=',
        '!<' => '<',
        '>=' => '<',
        '<=' => '>'
    ];

    /**
     * @param string $operator
     * @return string
     */
    public static function reverseOperator(string $operator): string
    {
        if (!array_keys(self::REVERSE_COMPARISON_OPERATOR, $operator)) {
            throw new \RuntimeException(
                sprintf('Class : SqlOperators, function : reverseOperator. The operator "%s" is unknown.', $operator)
            );
        }
        return self::REVERSE_COMPARISON_OPERATOR[$operator];
    }
}