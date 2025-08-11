<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(tableName: 'test_null_column')]
class TestEntityWithNullReferencedColumn
{
    #[MtFk(referencedTable: 'referenced_table')]
    #[MtColumn(columnType: ColumnType::INT)]
    public int $someId;
}