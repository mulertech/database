<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql;

enum LinkOperator: string
{
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';
    case AND_NOT = 'AND NOT';
    case OR_NOT = 'OR NOT';

    public function not(): LinkOperator
    {
        return match ($this) {
            self::AND => self::AND_NOT,
            self::OR => self::OR_NOT,
            default => self::NOT,
        };
    }
}
