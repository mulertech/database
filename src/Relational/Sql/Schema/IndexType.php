<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql\Schema;

enum IndexType: string
{
    case INDEX = 'INDEX';
    case UNIQUE = 'UNIQUE';
    case FULLTEXT = 'FULLTEXT';
}
