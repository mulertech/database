<?php

namespace MulerTech\Database\Relational\Sql;

enum SqlOperator: string
{
    case NOT = 'NOT';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case BETWEEN = 'BETWEEN';
    case NOT_BETWEEN = 'NOT BETWEEN';
    case EXISTS = 'EXISTS';
    case NOT_EXISTS = 'NOT EXISTS';
    case LIKE = 'LIKE';
    case NOT_LIKE = 'NOT LIKE';

    public function not(): SqlOperator
    {
        return match ($this) {
            self::IN => self::NOT_IN,
            self::BETWEEN => self::NOT_BETWEEN,
            self::EXISTS => self::NOT_EXISTS,
            self::LIKE => self::NOT_LIKE,
            default => self::NOT,
        };
    }
}