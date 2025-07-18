<?php

declare(strict_types=1);

namespace MulerTech\Database\SQL\Operator;

/**
 * Enum ArithmeticOperator
 *
 * Arithmetic operators for SQL queries
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum ArithmeticOperator: string
{
    case ADD = '+';
    case SUBTRACT = '-';
    case MULTIPLY = '*';
    case DIVIDE = '/';
    case MODULO = '%';
}
