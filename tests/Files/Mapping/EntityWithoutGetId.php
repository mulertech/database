<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity without getId method - used for testing DeletionProcessor exception handling
 * Note: This is in EntityNotMapped namespace to avoid being processed by schema comparers
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(tableName: 'entity_without_get_id')]
class EntityWithoutGetId
{
    #[MtColumn(columnType: ColumnType::INT)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255)]
    private ?string $name = null;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}