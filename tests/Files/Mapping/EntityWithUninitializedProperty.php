<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity with an uninitialized typed property (no default value).
 */
#[MtEntity(tableName: 'entity_uninitialized')]
class EntityWithUninitializedProperty
{
    #[MtColumn(columnType: ColumnType::INT)]
    private int $id = 1;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private string $items;

    public function getId(): int
    {
        return $this->id;
    }

    public function getItems(): string
    {
        return $this->items;
    }

    public function setItems(string $items): void
    {
        $this->items = $items;
    }
}
