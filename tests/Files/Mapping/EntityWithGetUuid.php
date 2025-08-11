<?php

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity with getUuid method
 * @package MulerTech\Database
 */
#[MtEntity(tableName: "test_entity_uuid")]
class EntityWithGetUuid
{
    #[MtColumn(columnType: ColumnType::VARCHAR, length: 36, isNullable: false, columnKey: ColumnKey::PRIMARY_KEY)]
    private string $uuid = 'uuid-123';

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }
}