<?php

namespace MulerTech\Database\Relational\Sql\Schema;

/**
 * Class TableDefinition
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TableDefinition
{
    /**
     * @param string $tableName The name of the table
     * @param bool $isCreate Whether this is a create operation
     * @param array<string, ColumnDefinition|array{drop: bool}> $columns Table columns
     * @param array<string, array{type: string, columns: array<int, string>}> $indexes Table indexes
     * @param array<string, ForeignKeyDefinition> $foreignKeys Table foreign keys
     * @param array<string, string> $options Table options
     */
    public function __construct(
        private string $tableName,
        private bool $isCreate,
        private array $columns = [],
        private array $indexes = [],
        private array $foreignKeys = [],
        private array $options = []
    ) {
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
     * @param string $name
     * @return self
     */
    public function dropColumn(string $name): self
    {
        $this->columns[$name] = ['drop' => true];
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
     * @param string $name
     * @return self
     */
    public function dropForeignKey(string $name): self
    {
        if (!isset($this->foreignKeys[$name])) {
            $this->foreignKeys[$name] = new ForeignKeyDefinition($name);
        }

        $this->foreignKeys[$name]->setDrop();
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
        $schemaGenerator = new SchemaQueryGenerator();
        return $schemaGenerator->generate($this);
    }
}
