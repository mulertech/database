<?php

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity with getIdentifier method
 * @package MulerTech\Database
 */
#[MtEntity(tableName: "test_entity_identifier")]
class EntityWithGetIdentifier
{
    #[MtColumn(columnType: ColumnType::INT, isUnsigned: true, isNullable: false, columnKey: ColumnKey::PRIMARY_KEY)]
    private int $identifier = 123;

    public function getIdentifier(): int
    {
        return $this->identifier;
    }

    public function setIdentifier(int $identifier): void
    {
        $this->identifier = $identifier;
    }
}