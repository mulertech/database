<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema;

/**
 * Class ForeignKeyDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ForeignKeyDefinition
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var array<int, string>
     */
    private array $columns = [];

    /**
     * @var string|null
     */
    private ?string $referencedTable = null;

    /**
     * @var array<int, string>
     */
    private array $referencedColumns = [];

    /**
     * @var ReferentialAction|null
     */
    private ?ReferentialAction $onDelete = null;

    /**
     * @var ReferentialAction|null
     */
    private ?ReferentialAction $onUpdate = null;

    /**
     * @var bool
     */
    private bool $drop = false;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return string|null
     */
    public function getReferencedTable(): ?string
    {
        return $this->referencedTable;
    }

    /**
     * @return array<int, string>
     */
    public function getReferencedColumns(): array
    {
        return $this->referencedColumns;
    }

    /**
     * @return ReferentialAction|null
     */
    public function getOnDelete(): ?ReferentialAction
    {
        return $this->onDelete;
    }

    /**
     * @return ReferentialAction|null
     */
    public function getOnUpdate(): ?ReferentialAction
    {
        return $this->onUpdate;
    }

    /**
     * @return bool
     */
    public function isDrop(): bool
    {
        return $this->drop;
    }

    /**
     * @param string|array<int, string> $columns
     * @return self
     */
    public function columns(string|array $columns): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * @param string $table
     * @param string|array<int, string> $columns
     * @return self
     */
    public function references(string $table, string|array $columns): self
    {
        $this->referencedTable = $table;
        $this->referencedColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * @param ReferentialAction $action
     * @return self
     */
    public function onDelete(ReferentialAction $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    /**
     * @param ReferentialAction $action
     * @return self
     */
    public function onUpdate(ReferentialAction $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    /**
     * Mark this foreign key for dropping
     * @return self
     */
    public function setDrop(): self
    {
        $this->drop = true;
        return $this;
    }
}
