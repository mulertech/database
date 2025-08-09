<?php

namespace MulerTech\Database\Tests\Files\EntityNotMapped;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity without repository specified for testing EntityManager::getRepository error case
 * @package MulerTech\Database\Tests\Files\EntityNotMapped
 */
#[MtEntity(tableName: "entity_without_repository")]
class EntityWithoutRepository
{
    #[MtColumn(columnType: ColumnType::INT, isNullable: false)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false)]
    private ?string $name = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}