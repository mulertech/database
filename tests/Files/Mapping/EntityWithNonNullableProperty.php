<?php

namespace MulerTech\Database\Tests\Files\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnType;

/**
 * Test entity with non-nullable property for hydration testing
 * @package MulerTech\Database
 */
#[MtEntity(tableName: "test_entity_non_nullable")]
class EntityWithNonNullableProperty
{
    #[MtColumn(columnName: "required_field", columnType: ColumnType::VARCHAR, length: 255, isNullable: false)]
    private ?string $requiredField = null;

    #[MtColumn(columnName: "optional_field", columnType: ColumnType::VARCHAR, length: 255, isNullable: true)]
    private ?string $optionalField = null;

    public function getRequiredField(): ?string
    {
        return $this->requiredField;
    }

    public function setRequiredField(?string $requiredField): void
    {
        $this->requiredField = $requiredField;
    }

    public function getOptionalField(): ?string
    {
        return $this->optionalField;
    }

    public function setOptionalField(?string $optionalField): void
    {
        $this->optionalField = $optionalField;
    }
}