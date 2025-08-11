<?php

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity with missing getter method to test EntityHydrator error handling
 */
#[MtEntity(tableName: "test_missing_getter")]
class EntityWithMissingGetter
{
    #[MtColumn(columnType: ColumnType::INT, isNullable: false)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // Missing getDescription() method intentionally - this should trigger the TODO case
}