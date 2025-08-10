<?php

namespace MulerTech\Database\Tests\Files\EntityNotMapped;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity with missing setter method to test EntityHydrator error handling
 */
#[MtEntity(tableName: "test_missing_setter")]
class EntityWithMissingSetter
{
    #[MtColumn(columnType: ColumnType::INT, isNullable: false)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    // Missing setName() method intentionally - this should trigger the TODO case
}