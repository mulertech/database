<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use MulerTech\Entity\Entity;

/**
 * Class User
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity(repository: UserRepository::class, tableName: "users_test", autoIncrement: 100)]
class User extends Entity
{
    #[MtColumn(columnType: "int unsigned", isNullable: false, extra: "auto_increment", columnKey: MtColumn::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: "varchar(255)", isNullable: false, columnDefault: "John")]
    private ?string $username = null;

    /**
     * @var int $unit
     */
    #[MtColumn(columnName: "unit_id", columnType: "int unsigned", isNullable: false, columnKey: MtColumn::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Unit::class, referencedColumn: "id", deleteRule: MtFk::RESTRICT, updateRule: MtFk::CASCADE)]
    private ?int $unit = null;

    /**
     * Test of a variable which is not a column in the database
     * @var int $group
     */
//    private int $group;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getUnit(): ?int
    {
        return $this->unit;
    }

    public function setUnit(int $unit): void
    {
        $this->unit = $unit;
    }


}