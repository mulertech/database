<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

/**
 * Class SchemaDifference
 *
 * Holds differences between database schema and entity mappings
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SchemaDifference
{
    /**
     * @var array<string, string> Tables to create [tableName => entityClass]
     */
    private array $tablesToCreate = [];

    /**
     * @var array<string> Tables to drop
     */
    private array $tablesToDrop = [];

    /**
     * @var array<string, array<string, array<string, mixed>>> Columns to add [tableName => [columnName => columnDefinition]]
     */
    private array $columnsToAdd = [];

    /**
     * @var array<string, array<string, array{
     *      COLUMN_TYPE: array{from: string, to: string},
     *      IS_NULLABLE: array{from: string, to: string},
     *      COLUMN_DEFAULT: array{from: string|null, to: string|null},
     *      }>> Columns to modify [tableName => [columnName => differences]]
     */
    private array $columnsToModify = [];

    /**
     * @var array<string, array<string>> Columns to drop [tableName => [columnNames]]
     */
    private array $columnsToDrop = [];

    /**
     * @var array<string, array<string, array<string, mixed>>> Foreign keys to add [tableName => [constraintName => definition]]
     */
    private array $foreignKeysToAdd = [];

    /**
     * @var array<string, array<string>> Foreign keys to drop [tableName => [constraintNames]]
     */
    private array $foreignKeysToDrop = [];

    /**
     * Add a table to be created
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @return $this
     */
    public function addTableToCreate(string $tableName, string $entityClass): self
    {
        $this->tablesToCreate[$tableName] = $entityClass;
        return $this;
    }

    /**
     * Add a table to be dropped
     *
     * @param string $tableName
     * @return $this
     */
    public function addTableToDrop(string $tableName): self
    {
        $this->tablesToDrop[] = $tableName;
        return $this;
    }

    /**
     * Add a column to be added
     *
     * @param string $tableName
     * @param string $columnName
     * @param array<string, mixed> $columnDefinition
     * @return $this
     */
    public function addColumnToAdd(string $tableName, string $columnName, array $columnDefinition): self
    {
        if (!isset($this->columnsToAdd[$tableName])) {
            $this->columnsToAdd[$tableName] = [];
        }

        $this->columnsToAdd[$tableName][$columnName] = $columnDefinition;
        return $this;
    }

    /**
     * Add a column to be modified
     *
     * @param string $tableName
     * @param string $columnName
     * @param array{
     *     COLUMN_TYPE: array{from: string, to: string},
     *     IS_NULLABLE: array{from: string, to: string},
     *     COLUMN_DEFAULT: array{from: string|null, to: string|null},
     *     } $differences
     * @return $this
     */
    public function addColumnToModify(string $tableName, string $columnName, array $differences): self
    {
        if (!isset($this->columnsToModify[$tableName])) {
            $this->columnsToModify[$tableName] = [];
        }

        $this->columnsToModify[$tableName][$columnName] = $differences;
        return $this;
    }

    /**
     * Add a column to be dropped
     *
     * @param string $tableName
     * @param string $columnName
     * @return $this
     */
    public function addColumnToDrop(string $tableName, string $columnName): self
    {
        if (!isset($this->columnsToDrop[$tableName])) {
            $this->columnsToDrop[$tableName] = [];
        }

        $this->columnsToDrop[$tableName][] = $columnName;
        return $this;
    }

    /**
     * Add a foreign key to be added
     *
     * @param string $tableName
     * @param string $constraintName
     * @param array<string, mixed> $foreignKeyDefinition
     * @return $this
     */
    public function addForeignKeyToAdd(string $tableName, string $constraintName, array $foreignKeyDefinition): self
    {
        if (!isset($this->foreignKeysToAdd[$tableName])) {
            $this->foreignKeysToAdd[$tableName] = [];
        }

        $this->foreignKeysToAdd[$tableName][$constraintName] = $foreignKeyDefinition;
        return $this;
    }

    /**
     * Add a foreign key to be dropped
     *
     * @param string $tableName
     * @param string $constraintName
     * @return $this
     */
    public function addForeignKeyToDrop(string $tableName, string $constraintName): self
    {
        if (!isset($this->foreignKeysToDrop[$tableName])) {
            $this->foreignKeysToDrop[$tableName] = [];
        }

        $this->foreignKeysToDrop[$tableName][] = $constraintName;
        return $this;
    }

    /**
     * Get tables to create
     *
     * @return array<string, string>
     */
    public function getTablesToCreate(): array
    {
        return $this->tablesToCreate;
    }

    /**
     * Get tables to drop
     *
     * @return array<string>
     */
    public function getTablesToDrop(): array
    {
        return $this->tablesToDrop;
    }

    /**
     * Get columns to add
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getColumnsToAdd(): array
    {
        return $this->columnsToAdd;
    }

    /**
     * Get columns to modify
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getColumnsToModify(): array
    {
        return $this->columnsToModify;
    }

    /**
     * Get columns to drop
     *
     * @return array<string, array<string>>
     */
    public function getColumnsToDrop(): array
    {
        return $this->columnsToDrop;
    }

    /**
     * Get foreign keys to add
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getForeignKeysToAdd(): array
    {
        return $this->foreignKeysToAdd;
    }

    /**
     * Get foreign keys to drop
     *
     * @return array<string, array<string>>
     */
    public function getForeignKeysToDrop(): array
    {
        return $this->foreignKeysToDrop;
    }

    /**
     * Check if there are any differences
     *
     * @return bool
     */
    public function hasDifferences(): bool
    {
        return !empty($this->tablesToCreate) ||
               !empty($this->tablesToDrop) ||
               !empty($this->columnsToAdd) ||
               !empty($this->columnsToModify) ||
               !empty($this->columnsToDrop) ||
               !empty($this->foreignKeysToAdd) ||
               !empty($this->foreignKeysToDrop);
    }
}
