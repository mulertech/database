<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtManyToOne;
use MulerTech\Database\Mapping\MtOneToMany;
use MulerTech\Database\Tests\Files\Repository\GroupRepository;

/**
 * Class Group
 * @package MulerTech\Database\Tests\Files
 * @author Sébastien Muler
 */
#[MtEntity(repository: GroupRepository::class, tableName: 'groups_test')]
class Group
{
    #[MtColumn(columnType: "int unsigned", isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: "varchar(255)", isNullable: false)]
    private ?string $name = null;

    #[MtColumn(columnName: 'parent_id', columnType: "int unsigned", isNullable: false, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Group::class, referencedColumn: "id")]
    #[MtManyToOne(targetEntity: Group::class)]
    private ?Group $parent = null;

    #[MtOneToMany(targetEntity: Group::class, mappedBy: "parent")]
    private Collection $children;

    #[MtManyToMany(
        targetEntity: User::class,
        mappedBy: GroupUser::class,
        joinColumn: "group_id",
        inverseJoinColumn: "user_id"
    )]
    private Collection $users;

    public function __construct()
    {
        $this->children = new Collection();
        $this->users = new Collection();
    }

     /**
     * @return Collection
     */

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

    public function getParent(): ?Group
    {
        return $this->parent;
    }

    public function setParent(?Group $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getChildren(): ?Collection
    {
        return $this->children;
    }

    public function setChildren(Collection $children): self
    {
        $this->children = $children;
        foreach ($children as $child) {
            $child->setParent($this);
        }

        return $this;
    }

    public function addChild(Group $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->push($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(Group $child): self
    {
        $this->children->removeItem($child);
        $child->setParent(null);

        return $this;
    }

    public function getUsers(): ?Collection
    {
        return $this->users;
    }

    public function setUsers(Collection $users): self
    {
        $this->users = $users;

        return $this;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->push($user);
        }

        return $this;
    }
}