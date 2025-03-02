<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\FkRule;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\Tests\Files\Repository\UserRepository;

/**
 * Class User
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity(repository: UserRepository::class, tableName: "users_test", autoIncrement: 100)]
class User
{
    #[MtColumn(columnType: "int unsigned", isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: "varchar(255)", isNullable: false, columnDefault: "John")]
    private ?string $username = null;

    /**
     * @var null|Unit $unit
     */
    #[MtColumn(columnName: "unit_id", columnType: "int unsigned", isNullable: false, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Unit::class, referencedColumn: "id", deleteRule: FkRule::RESTRICT, updateRule: FkRule::CASCADE)]
    #[MtOneToOne(entity: Unit::class)]
    private ?Unit $unit = null;

    #[MtManyToMany(entity: Group::class, joinTable: "user_group_test", joinColumn: "user_id", inverseJoinColumn: "group_id")]
    private ?Collection $groups = null;

    /**
     * Test of a variable which is not a column in the database
     * @var int $notColumn
     */
    private int $notColumn;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): self
    {
        $this->unit = $unit;

        return $this;
    }


}