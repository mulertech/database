<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use InvalidArgumentException;
use MulerTech\Database\Mapping\Attributes\MtEntity;

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
    private MtEntity $mtEntity;

    public function __construct(
        private readonly string $tableName,
        private readonly string $action,
    ) {
        $this->mtEntity = new MtEntity(
            repository: null,
            tableName: $this->tableName,
            autoIncrement: null,
            engine: null,
            charset: null,
            collation: null
        );
    }

    /**
     * Add a column definition
     * @param string $name Column name
     * @return ColumnDefinition
     */
    public function column(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name);
        $this->columns[$name] = $column;
        return $column;
    }

    /**
     * Set primary key
     * @param string ...$columns Column names to set as primary key
     * @return self
     */
    public function primaryKey(string ...$columns): self
    {
        $this->primaryKeys = array_merge($this->primaryKeys, $columns);
        return $this;
    }

    /**
     * Add foreign key constraint
     * @param string $constraintName Name of the foreign key constraint
     * @return ForeignKeyDefinition
     */
    public function foreignKey(string $constraintName): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition($constraintName);
        $this->foreignKeys[$constraintName] = $foreignKey;
        return $foreignKey;
    }

    /**
     * Drop a column
     * @param string $columnName Name of the column to drop
     * @return self
     */
    public function dropColumn(string $columnName): self
    {
        $this->dropColumns[] = $columnName;
        return $this;
    }

    /**
     * Drop a foreign key
     * @param string $constraintName Name of the foreign key constraint to drop
     * @return self
     */
    public function dropForeignKey(string $constraintName): self
    {
        $this->dropForeignKeys[] = $constraintName;
        return $this;
    }

    /**
     * Modify an existing column
     * @param ColumnDefinition $column Column definition to modify
     * @return self
     */
    public function modifyColumn(ColumnDefinition $column): self
    {
        $this->modifyColumns[$column->getName()] = $column;
        return $this;
    }

    /**
     * Set table engine
     * @param string $engine Engine type (e.g., InnoDB, MyISAM)
     * @return self
     */
    public function engine(string $engine): self
    {
        $this->mtEntity->engine = $engine;
        return $this;
    }

    /**
     * Set table charset
     * @param string $charset Character set (e.g., utf8mb4)
     * @return self
     */
    public function charset(string $charset): self
    {
        $this->mtEntity->charset = $charset;
        return $this;
    }

    /**
     * Set table collation
     * @param string $collation Collation (e.g., utf8mb4_unicode_ci)
     * @return self
     */
    public function collation(string $collation): self
    {
        $this->mtEntity->collation = $collation;
        return $this;
    }

    /**
     * Generate SQL for this table definition
     * @return string SQL statement
     */
    public function toSql(): string
    {
        return match ($this->action) {
            self::ACTION_CREATE => $this->generateCreateTableSql(),
            self::ACTION_ALTER => $this->generateAlterTableSql(),
            default => throw new InvalidArgumentException("Unknown action: $this->action")
        };
    }

    /**
     * @return string
     */
    private function generateCreateTableSql(): string
    {
        $sql = "CREATE TABLE `$this->tableName` (\n";

        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = "    " . $column->toSql();
        }

        if (!empty($this->primaryKeys)) {
            $primaryKeyColumns = implode('`, `', $this->primaryKeys);
            $columnDefinitions[] = "    PRIMARY KEY (`$primaryKeyColumns`)";
        }

        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n)";

        if ($this->mtEntity->engine) {
            $sql .= " ENGINE={$this->mtEntity->engine}";
        }
        if ($this->mtEntity->charset) {
            $sql .= " CHARACTER SET {$this->mtEntity->charset}";
        }
        if ($this->mtEntity->collation) {
            $sql .= " COLLATE {$this->mtEntity->collation}";
        }

        return $sql;
    }

    /**
     * @return string
     */
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
            $alterations[] = "DROP COLUMN `$columnName`";
        }

        // Add foreign keys
        foreach ($this->foreignKeys as $constraintName => $foreignKey) {
            $alterations[] = "ADD CONSTRAINT `$constraintName` " . $foreignKey->toSql();
        }

        // Drop foreign keys
        foreach ($this->dropForeignKeys as $constraintName) {
            $alterations[] = "DROP FOREIGN KEY `$constraintName`";
        }

        if (empty($alterations)) {
            return "-- No alterations defined for table $this->tableName";
        }

        return "ALTER TABLE `$this->tableName` " . implode(", ", $alterations);
    }
}
