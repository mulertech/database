<?php

namespace MulerTech\Database\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\SqlQuery;
use RuntimeException;

/**
 * Class ForeignKeyDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ForeignKeyDefinition
{
    /**
     * @var array<int, string>
     */
    private array $columns = [];

    /**
     * @var string
     */
    private string $referencedTable;

    /**
     * @var array<int, string>
     */
    private array $referencedColumns = [];

    /**
     * @var ReferentialAction
     */
    private ReferentialAction $onUpdate = ReferentialAction::RESTRICT;

    /**
     * @var ReferentialAction
     */
    private ReferentialAction $onDelete = ReferentialAction::RESTRICT;

    /**
     * @var bool
     */
    private bool $isDrop = false;

    /**
     * @param string $name
     */
    public function __construct(private string $name)
    {}

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
     * @return string
     */
    public function getReferencedTable(): string
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
     * @return ReferentialAction
     */
    public function getOnUpdate(): ReferentialAction
    {
        return $this->onUpdate;
    }

    /**
     * @return ReferentialAction
     */
    public function getOnDelete(): ReferentialAction
    {
        return $this->onDelete;
    }

    /**
     * @return bool
     */
    public function isDrop(): bool
    {
        return $this->isDrop;
    }

    /**
     * @param bool $isDrop
     * @return self
     */
    public function setDrop(bool $isDrop = true): self
    {
        $this->isDrop = $isDrop;
        return $this;
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
}

