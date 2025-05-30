<?php

namespace MulerTech\Database\Relational\Sql;

enum ComparisonOperator: string
{
    case EQUAL = '=';
    case NOT_EQUAL = '<>';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUAL = '>=';
    case NOT_GREATER_THAN = '!>';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUAL = '<=';
    case NOT_LESS_THAN = '!<';

    public function reverse(): ComparisonOperator
    {
        return match ($this) {
            self::EQUAL => self::NOT_EQUAL,
            self::NOT_EQUAL => self::EQUAL,
            self::GREATER_THAN => self::LESS_THAN_OR_EQUAL,
            self::GREATER_THAN_OR_EQUAL => self::LESS_THAN,
            self::NOT_GREATER_THAN => self::LESS_THAN_OR_EQUAL,
            self::LESS_THAN => self::GREATER_THAN_OR_EQUAL,
            self::LESS_THAN_OR_EQUAL => self::GREATER_THAN,
            self::NOT_LESS_THAN => self::GREATER_THAN_OR_EQUAL,
        };
    }
}
