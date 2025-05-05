<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\ColumnType;
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
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false, columnDefault: "John")]
    private ?string $username = null;

    #[MtColumn(columnName: "size", columnType: ColumnType::INT, isNullable: true)]
    private ?int $size = null;

    /**
     * @var null|Unit $unit
     */
    #[MtColumn(columnName: "unit_id", columnType: ColumnType::INT, unsigned: true, isNullable: false, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Unit::class, referencedColumn: "id", deleteRule: FkRule::RESTRICT, updateRule: FkRule::CASCADE)]
    #[MtOneToOne(targetEntity: Unit::class)]
    private ?Unit $unit = null;

    #[MtManyToMany(
        targetEntity: Group::class,
        mappedBy: GroupUser::class,
        joinProperty: "user",
        inverseJoinProperty: "group"
    )]
    private Collection $groups;

    /**
     * Test of a variable which is not a column in the database
     * @var int $notColumn
     */
    private int $notColumn;

    public function __construct()
    {
        $this->groups = new Collection();
    }

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

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

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

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(User $manager): self
    {
        $this->manager = $manager;

        return $this;
    }

    public function getGroups(): ?Collection
    {
        return $this->groups;
    }

    public function setGroups(Collection $groups): self
    {
        $this->groups = $groups;

        return $this;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups->push($group);
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        $this->groups->removeItem($group);

        return $this;
    }
}