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
    #[MtManyToOne(entity: Group::class)]
    private ?Group $parent = null;

    #[MtOneToMany(entity: Group::class, mappedBy: "parent_id")]
    private ?Collection $children = null;

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

    public function setParent(Group $parent): self
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

        return $this;
    }
}