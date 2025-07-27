<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use MulerTech\Database\Mapping\Types\FkRule;

/**
 * Foreign Key Definition - Fluent interface for foreign key operations
 */
class ForeignKeyDefinition
{
    private ?string $column = null;
    private ?string $referencedTable = null;
    private ?string $referencedColumn = null;
    private FkRule $onUpdate = FkRule::NO_ACTION;
    private FkRule $onDelete = FkRule::NO_ACTION;

    /**
     * Set the column for this foreign key
     */
    public function column(string $column): self
    {
        $this->column = $column;
        return $this;
    }

    /**
     * Set the referenced table and column
     */
    public function references(string $table, string $column): self
    {
        $this->referencedTable = $table;
        $this->referencedColumn = $column;
        return $this;
    }

    /**
     * @param FkRule $action
     * @return self
     */
    public function onUpdate(FkRule $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    /**
     * Mark this foreign key for dropping
     * @return self
     */
    public function onDelete(FkRule $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    /**
     * Generate SQL for this foreign key
     */
    public function toSql(): string
    {
        if (!$this->column || !$this->referencedTable || !$this->referencedColumn) {
            throw new \InvalidArgumentException("Foreign key definition is incomplete");
        }

        return "FOREIGN KEY (`{$this->column}`) REFERENCES `{$this->referencedTable}`(`{$this->referencedColumn}`) ON UPDATE {$this->onUpdate->value} ON DELETE {$this->onDelete->value}";
    }
}
