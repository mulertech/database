<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql;

/**
 * Enum JoinType
 *
 * SQL join types enumeration
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum JoinType: string
{
    case INNER = 'INNER';
    case LEFT = 'LEFT';
    case RIGHT = 'RIGHT';
    case CROSS = 'CROSS';
    case FULL_OUTER = 'FULL OUTER';
    case LEFT_OUTER = 'LEFT OUTER';
    case RIGHT_OUTER = 'RIGHT OUTER';
}
