<?php

namespace MulerTech\Database\Tests\Files\Entity;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\ColumnKey;
use MulerTech\Database\Mapping\Metadata\ColumnType;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\Tests\Files\Repository\GroupRepository;

/**
 * Class Group
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
#[MtEntity(repository: GroupRepository::class, tableName: 'groups_test')]
class Group
{
    #[MtColumn(columnType: ColumnType::INT, unsigned: true, isNullable: false, extra: "auto_increment", columnKey: ColumnKey::PRIMARY_KEY)]
    private ?int $id = null;

    #[MtColumn(columnType: ColumnType::VARCHAR, length: 255, isNullable: false)]
    private ?string $name = null;

    #[MtColumn(columnName: "description", columnType: ColumnType::TEXT, isNullable: true)]
    private ?string $description = null;

    #[MtColumn(columnName: "created_at", columnType: ColumnType::TIMESTAMP, isNullable: true)]
    private ?string $createdAt = null;

    #[MtColumn(columnName: "member_count", columnType: ColumnType::MEDIUMINT, unsigned: true, isNullable: true, columnDefault: "0")]
    private ?int $memberCount = null;

    #[MtColumn(columnName: 'parent_id', columnType: ColumnType::INT, unsigned: true, isNullable: true, columnKey: ColumnKey::MULTIPLE_KEY)]
    #[MtFk(referencedTable: Group::class, referencedColumn: "id")]
    #[MtManyToOne(targetEntity: Group::class)]
    private ?Group $parent = null;

    #[MtOneToMany(targetEntity: Group::class, inverseJoinProperty: "parent")]
    private Collection $children;

    #[MtManyToMany(
        targetEntity: User::class,
        mappedBy: GroupUser::class,
        joinProperty: "group",
        inverseJoinProperty: "user"
    )]
    private Collection $users;

    public function __construct()
    {
        $this->children = new DatabaseCollection();
        $this->users = new DatabaseCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMemberCount(): ?int
    {
        return $this->memberCount;
    }

    public function setMemberCount(?int $memberCount): self
    {
        $this->memberCount = $memberCount;
        return $this;
    }

    public function getParent(): ?Group
    {
        return $this->parent;
    }

    public function setParent(?Group $parent): self
    {
        $this->parent = $parent;
        if ($parent !== null && !$parent->getChildren()->contains($this)) {
            $parent->addChild($this);
        } elseif ($parent === null && $this->getParent() !== null) {
            $this->getParent()->removeChild($this);
        }

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