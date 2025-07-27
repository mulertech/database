<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Types\ColumnKey;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Tests\Files\Repository\UnitRepository;

/**
 * Class User
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(repository: UnitRepository::class, tableName: "units_test", autoIncrement: 100)]
class Unit
{
    #[MtColumn(columnType: ColumnType::INT, isUnsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false)]
    private ?string $name = null;

    #[MtColumn(columnName: "unit_code", columnType: ColumnType::CHAR, length: 10, isNullable: true)]
    private ?string $unitCode = null;

    #[MtColumn(columnName: "priority", columnType: ColumnType::TINYINT, isUnsigned: true, isNullable: true, columnDefault: 1)]
    private ?int $priority = null;

    #[MtColumn(columnName: "is_enabled", columnType: ColumnType::TINYINT, length: 1, isNullable: false, columnDefault: 1)]
    private ?bool $isEnabled = null;

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

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUnitCode(): ?string
    {
        return $this->unitCode;
    }

    public function setUnitCode(?string $unitCode): self
    {
        $this->unitCode = $unitCode;
        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getIsEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }
}