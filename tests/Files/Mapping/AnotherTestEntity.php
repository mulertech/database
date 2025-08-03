<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(tableName: 'others')]
class AnotherTestEntity
{
    #[MtColumn(columnType: ColumnType::INT)]
    public int $id;

    #[MtColumn(columnType: ColumnType::VARCHAR)]
    public string $title;
}

