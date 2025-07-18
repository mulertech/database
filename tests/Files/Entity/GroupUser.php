<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\Metadata\ColumnType;
use MulerTech\Database\Mapping\FkRule;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;

/**
 * Link entity for User-Group many-to-many relationship
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(tableName: "link_user_group_test")]
class GroupUser
{
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnName: "user_id", columnType: ColumnType::INT, unsigned: true, isNullable: false, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: User::class, referencedColumn: "id", deleteRule: FkRule::CASCADE, updateRule: FkRule::CASCADE)]
    #[MtManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[MtColumn(columnName: "group_id", columnType: ColumnType::INT, unsigned: true, isNullable: false, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Group::class, referencedColumn: "id", deleteRule: FkRule::CASCADE, updateRule: FkRule::CASCADE)]
    #[MtManyToOne(targetEntity: Group::class)]
    private ?Group $group = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;
        return $this;
    }
}
