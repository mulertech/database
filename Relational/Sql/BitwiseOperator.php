<?php

namespace MulerTech\Database\Relational\Sql;

enum BitwiseOperator: string
{
    case BITWISE_AND = '&';
    case BITWISE_OR = '|';
    case BITWISE_XOR = '^';
    case BITWISE_NOT = '~';
}