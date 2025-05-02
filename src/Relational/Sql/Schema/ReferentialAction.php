<?php

namespace MulerTech\Database\Relational\Sql\Schema;

enum ReferentialAction: string
{
    case RESTRICT = 'RESTRICT';
    case CASCADE = 'CASCADE';
    case SET_NULL = 'SET NULL';
    case NO_ACTION = 'NO ACTION';
}