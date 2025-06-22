<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql\Schema;

/**
 * Class TableDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TableDefinition
{
    /**
     * @var string
     */
    private string $tableName;

    /**
     * @var bool
     */
    private bool $isCreate;

    /**
     * @var array<string, ColumnDefinition|array{drop: bool}>
     */
    private array $columns = [];

    /**
     * @var array<string, array{type: string, columns: array<int, string>}>
     */
    private array $indexes = [];

    /**
     * @var array<string, ForeignKeyDefinition>
     */
    private array $foreignKeys = [];

    /**
     * @var array<string, string>
     */
    private array $options = [];

    /**
     * @param string $tableName
     * @param bool $isCreate
     */
    public function __construct(string $tableName, bool $isCreate = true)
    {
        $this->tableName = $tableName;
        $this->isCreate = $isCreate;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return bool
     */
    public function isCreate(): bool
    {
        return $this->isCreate;
    }

    /**
     * @return array<string, ColumnDefinition|array{drop: bool}>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<string, array{type: string, columns: array<int, string>}>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return array<string, ForeignKeyDefinition>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string $name
     * @return ColumnDefinition
     */
    public function column(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name);
        $this->columns[$name] = $column;
        return $column;
    }

    /**
     * Add a column after another column
     * @param string $name
     * @param string $afterColumn
     * @return ColumnDefinition
     */
    public function columnAfter(string $name, string $afterColumn): ColumnDefinition
    {
        $column = new ColumnDefinition($name);
        $column->after($afterColumn);
        $this->columns[$name] = $column;
        return $column;
    }

    /**
     * Add a column as the first column
     * @param string $name
     * @return ColumnDefinition
     */
    public function columnFirst(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name);
        $column->first();
        $this->columns[$name] = $column;
        return $column;
    }

    /**
     * @param string $columnName
     * @return self
     */
    public function dropColumn(string $columnName): self
    {
        $this->columns[$columnName] = ['drop' => true];
        return $this;
    }

    /**
     * @param string|array<int, string> $columns
     * @return self
     */
    public function primaryKey(string|array $columns): self
    {
        $this->indexes['PRIMARY'] = [
            'type' => 'PRIMARY KEY',
            'columns' => is_array($columns) ? $columns : [$columns],
        ];
        return $this;
    }

    /**
     * @param string $name
     * @return ForeignKeyDefinition
     */
    public function foreignKey(string $name): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition($name);
        $this->foreignKeys[$name] = $foreignKey;
        return $foreignKey;
    }

    /**
     * @param string $constraintName
     * @return self
     */
    public function dropForeignKey(string $constraintName): self
    {
        if (!isset($this->foreignKeys[$constraintName])) {
            $this->foreignKeys[$constraintName] = new ForeignKeyDefinition($constraintName);
        }
        $this->foreignKeys[$constraintName]->setDrop();
        return $this;
    }

    /**
     * @param string $engine
     * @return self
     */
    public function engine(string $engine): self
    {
        $this->options['ENGINE'] = $engine;
        return $this;
    }

    /**
     * @param string $charset
     * @return self
     */
    public function charset(string $charset): self
    {
        $this->options['CHARSET'] = $charset;
        return $this;
    }

    /**
     * @param string $collation
     * @return self
     */
    public function collation(string $collation): self
    {
        $this->options['COLLATE'] = $collation;
        return $this;
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        return new SchemaQueryGenerator()->generate($this);
    }
}
