<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use InvalidArgumentException;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Types\FkRule;

/**
 * Foreign Key Definition - Fluent interface for foreign key operations
 */
class ForeignKeyDefinition
{
    private MtFk $mtFk;

    public function __construct(
        ?string $constraintName = null
    ) {
        $this->mtFk = new MtFk(
            constraintName: $constraintName,
            referencedTable: null,
            referencedColumn: null,
            deleteRule: FkRule::NO_ACTION,
            updateRule: FkRule::NO_ACTION
        );
    }

    /**
     * Set the column for this foreign key
     */
    public function column(string $column): self
    {
        $this->mtFk->column = $column;
        return $this;
    }

    /**
     * Set the referenced table and column
     * @param string $table
     * @param string $column
     * @return self
     */
    public function references(string $table, string $column): self
    {
        $this->mtFk->referencedTable = $table;
        $this->mtFk->referencedColumn = $column;
        return $this;
    }

    /**
     * @param FkRule $action
     * @return self
     */
    public function onUpdate(FkRule $action): self
    {
        $this->mtFk->updateRule = $action;
        return $this;
    }

    /**
     * Mark this foreign key for dropping
     * @param FkRule $action
     * @return self
     */
    public function onDelete(FkRule $action): self
    {
        $this->mtFk->deleteRule = $action;
        return $this;
    }

    /**
     * Generate SQL for this foreign key
     */
    public function toSql(): string
    {
        if (!$this->mtFk->column || !$this->mtFk->referencedTable || !$this->mtFk->referencedColumn) {
            throw new InvalidArgumentException("Foreign key definition is incomplete");
        }

        $sql = '';

        if (!$this->mtFk->constraintName) {
            $sql .= "CONSTRAINT `{$this->mtFk->constraintName}` ";
        }

        $updateRule = $this->mtFk->updateRule->value ?? 'NO ACTION';
        $deleteRule = $this->mtFk->deleteRule->value ?? 'NO ACTION';

        $sql .= sprintf(
            "FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`) ON UPDATE %s ON DELETE %s",
            $this->mtFk->column,
            $this->mtFk->referencedTable,
            $this->mtFk->referencedColumn,
            $updateRule,
            $deleteRule
        );

        return $sql;
    }
}
