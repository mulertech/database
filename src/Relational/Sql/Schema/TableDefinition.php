<?php

namespace MulerTech\Database\Relational\Sql\Schema;

/**
 * Class TableDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TableDefinition
{
    private string $tableName;
    private bool $isCreate;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    private array $options = [];

    /**
     * @param string $tableName
     * @param bool $isCreate
     */
    public function __construct(string $tableName, bool $isCreate)
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
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return array
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return array
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
     * @param string $name
     * @return self
     */
    public function dropColumn(string $name): self
    {
        $this->columns[$name] = ['drop' => true];
        return $this;
    }

    /**
     * @param string|array $columns
     * @return self
     */
    public function primaryKey(string|array $columns): self
    {
        $this->indexes['PRIMARY'] = [
            'type' => 'PRIMARY KEY',
            'columns' => is_array($columns) ? $columns : [$columns]
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
        $schemaGenerator = new SchemaQueryGenerator();
        return $schemaGenerator->generate($this);
    }
}

