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
#[MtEntity(
    tableName: 'test_entities',
    repository: 'TestRepository',
    autoIncrement: 1000
)]
class TestEntityForProcessor
{
    #[MtColumn(columnType: ColumnType::INT)]
    public int $id;

    #[MtColumn(columnName: 'entity_name', columnType: ColumnType::VARCHAR)]
    public string $name;

    #[MtColumn(columnType: ColumnType::VARCHAR)]
    public string $email;

    public string $noColumnAttribute;
}

