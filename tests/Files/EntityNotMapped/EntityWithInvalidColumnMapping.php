<?php

namespace MulerTech\Database\Tests\Files\EntityNotMapped;

use MulerTech\Database\Mapping\Attributes\MtEntity;

/**
 * Test entity with no column mappings for testing EntityManager::isUnique error case
 * @package MulerTech\Database\Tests\Files\EntityNotMapped
 */
#[MtEntity(tableName: "entity_with_invalid_mapping")]
class EntityWithInvalidColumnMapping
{
    // No MtColumn attributes - this will cause getColumnName to return null
    private ?int $id = null;
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