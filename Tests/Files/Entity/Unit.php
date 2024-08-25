<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Tests\Files\UserRepository;
use MulerTech\Entity\Entity;

/**
 * Class User
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity(repository: UserRepository::class, tableName: "units_test", autoIncrement: 100)]
class Unit extends Entity
{
    #[MtColumn(columnType: "int unsigned", isNullable: false, extra: "auto_increment", columnKey: MtColumn::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: "varchar(255)", isNullable: false)]
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