<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql;

enum ScopeComparison: string
{
    case ALL = 'all';
    case ANY = 'any';
    case SOME = 'some';
}
