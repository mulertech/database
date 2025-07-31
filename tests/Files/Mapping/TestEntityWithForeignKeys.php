<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\FkRule;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(tableName: 'posts')]
class TestEntityWithForeignKeys
{
    #[MtColumn(columnType: ColumnType::INT)]
    public int $id;

    #[MtFk(
        column: 'user_id',
        referencedTable: 'users',
        referencedColumn: 'id',
        deleteRule: FkRule::CASCADE,
        updateRule: FkRule::RESTRICT
    )]
    #[MtColumn(columnType: ColumnType::INT)]
    public int $userId;

    #[MtFk(
        column: 'category_id',
        referencedTable: 'categories',
        referencedColumn: 'id',
        deleteRule: FkRule::SET_NULL,
        updateRule: FkRule::CASCADE
    )]
    #[MtColumn(columnType: ColumnType::INT)]
    public ?int $categoryId;

    #[MtColumn(columnType: ColumnType::VARCHAR)]
    public string $title;
}

