<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

/**
 * Enum ColumnKey
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum ColumnKey: string
{
    case PRIMARY_KEY = 'PRI';
    case UNIQUE_KEY = 'UNI';
    case MULTIPLE_KEY = 'MUL';
}
