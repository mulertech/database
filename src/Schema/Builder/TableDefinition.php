<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

/**
 * Table Definition - Fluent interface for table operations
 */
class TableDefinition
{
    public const string ACTION_CREATE = 'CREATE';
    public const string ACTION_ALTER = 'ALTER';

    /** @var array<string, ColumnDefinition> */
    private array $columns = [];
    /** @var array<string> */
    private array $primaryKeys = [];
    /** @var array<string, ForeignKeyDefinition> */
    private array $foreignKeys = [];
    /** @var array<string> */
    private array $dropColumns = [];
    /** @var array<string> */
    private array $dropForeignKeys = [];
    /** @var array<string, ColumnDefinition> */
    private array $modifyColumns = [];
    private ?string $engine = null;
    private ?string $charset = null;
    private ?string $collation = null;

    public function __construct(
        private readonly string $tableName,
        private readonly string $action
    ) {
    }

    /**
     * Add a column definition
     */
    public function column(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name);
        $this->columns[$name] = $column;
        return $column;
    }

    /**
     * Set primary key
     */
    public function primaryKey(string ...$columns): self
    {
        $this->primaryKeys = array_merge($this->primaryKeys, $columns);
        return $this;
    }

    /**
     * Add foreign key constraint
     */
    public function foreignKey(string $constraintName): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition();
        $this->foreignKeys[$constraintName] = $foreignKey;
        return $foreignKey;
    }

    /**
     * Drop a column
     */
    public function dropColumn(string $columnName): self
    {
        $this->dropColumns[] = $columnName;
        return $this;
    }

    /**
     * Drop a foreign key
     */
    public function dropForeignKey(string $constraintName): self
    {
        $this->dropForeignKeys[] = $constraintName;
        return $this;
    }

    /**
     * Modify an existing column
     */
    public function modifyColumn(ColumnDefinition $column): self
    {
        $this->modifyColumns[$column->getName()] = $column;
        return $this;
    }

    /**
     * Set table engine
     */
    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    /**
     * Set table charset
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Set table collation
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;
        return $this;
    }

    /**
     * Generate SQL for this table definition
     */
    public function toSql(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE => $this->generateCreateTableSql(),
            self::ACTION_ALTER => $this->generateAlterTableSql(),
            default => throw new \InvalidArgumentException("Unknown action: {$this->action}")
        };
    }

    private function generateCreateTableSql(): string
    {
        $sql = "CREATE TABLE `{$this->tableName}` (\n";

        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = "    " . $column->toSql();
        }

        if (!empty($this->primaryKeys)) {
            $primaryKeyColumns = implode('`, `', $this->primaryKeys);
            $columnDefinitions[] = "    PRIMARY KEY (`{$primaryKeyColumns}`)";
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        if ($this->engine) {
            $sql .= " ENGINE={$this->engine}";
        }
        if ($this->charset) {
            $sql .= " CHARACTER SET {$this->charset}";
        }
        if ($this->collation) {
            $sql .= " COLLATE {$this->collation}";
        }

        return $sql;
    }

    private function generateAlterTableSql(): string
    {
        $alterations = [];

        // Add columns
        foreach ($this->columns as $column) {
            $alterations[] = "ADD COLUMN " . $column->toSql();
        }

        // Modify columns
        foreach ($this->modifyColumns as $column) {
            $alterations[] = "MODIFY COLUMN " . $column->toSql();
        }

        // Drop columns
        foreach ($this->dropColumns as $columnName) {
            $alterations[] = "DROP COLUMN `{$columnName}`";
        }

        // Add foreign keys
        foreach ($this->foreignKeys as $constraintName => $foreignKey) {
            $alterations[] = "ADD CONSTRAINT `{$constraintName}` " . $foreignKey->toSql();
        }

        // Drop foreign keys
        foreach ($this->dropForeignKeys as $constraintName) {
            $alterations[] = "DROP FOREIGN KEY `{$constraintName}`";
        }

        if (empty($alterations)) {
            return "-- No alterations defined for table {$this->tableName}";
        }

        return "ALTER TABLE `{$this->tableName}` " . implode(", ", $alterations);
    }
}
