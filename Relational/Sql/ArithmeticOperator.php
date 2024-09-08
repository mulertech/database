<?php

namespace MulerTech\Database\Relational\Sql;

enum ArithmeticOperator: string
{
    case ADD = '+';
    case SUBTRACT = '-';
    case MULTIPLY = '*';
    case DIVIDE = '/';
    case MODULO = '%';
}