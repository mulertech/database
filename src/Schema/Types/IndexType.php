<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Types;

enum IndexType: string
{
    case INDEX = 'INDEX';
    case UNIQUE = 'UNIQUE';
    case FULLTEXT = 'FULLTEXT';
}
