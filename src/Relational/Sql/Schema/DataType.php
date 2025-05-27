<?php

namespace MulerTech\Database\Relational\Sql\Schema;

enum DataType: string
{
    case INT = 'INT';
    case BIGINT = 'BIGINT';
    case VARCHAR = 'VARCHAR';
    case TEXT = 'TEXT';
    case DECIMAL = 'DECIMAL';
    case FLOAT = 'FLOAT';
    case DATETIME = 'DATETIME';
    case DATE = 'DATE';
    case BOOLEAN = 'BOOLEAN';
    case JSON = 'JSON';
}
