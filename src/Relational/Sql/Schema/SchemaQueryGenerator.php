<?php

namespace MulerTech\Database\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\SqlQuery;
use MulerTech\Database\Mapping\ColumnType;

/**
 * Class SchemaQueryGenerator
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SchemaQueryGenerator
{
    /**
     * @param TableDefinition $tableDefinition
     * @return string
     */
    public function generate(TableDefinition $tableDefinition): string
    {
        $tableName = $tableDefinition->getTableName();
        $isCreate = $tableDefinition->isCreate();
        $columns = $tableDefinition->getColumns();
        $options = $tableDefinition->getOptions();
        $foreignKeys = $tableDefinition->getForeignKeys();
        $indexes = $tableDefinition->getIndexes();

        if ($isCreate) {
            return $this->generateCreateTable($tableName, $columns, $indexes, $foreignKeys, $options);
        } else {
            return $this->generateAlterTable($tableName, $columns, $options, $foreignKeys);
        }
    }

    /**
     * @param string $tableName The escaped table name
     * @param array<string, ColumnDefinition|array{drop: bool}> $columns Column definitions
     * @param array<string, array{type?: string, columns: array<int, string>}> $indexes Index definitions
     * @param array<string, ForeignKeyDefinition> $foreignKeys Foreign key definitions
     * @param array<string, string> $options Table options
     * @return string The generated CREATE TABLE SQL statement
     */
    private function generateCreateTable(
        string $tableName,
        array $columns,
        array $indexes,
        array $foreignKeys,
        array $options
    ): string {
        $parts = [];

        foreach ($columns as $column) {
            if (!is_array($column)) { // Skip dropped columns in CREATE
                $parts[] = "    " . $this->generateColumnDefinition($column);
            }
        }

        foreach ($indexes as $name => $index) {
            if ($name === 'PRIMARY') {
                $columnsList = array_map([SqlQuery::class, 'escape'], $index['columns']);
                $parts[] = "    PRIMARY KEY (" . implode(', ', $columnsList) . ")";
            } else {
                $type = $index['type'] ?? 'INDEX';
                $escapedName = SqlQuery::escape($name);
                $columnsList = array_map([SqlQuery::class, 'escape'], $index['columns']);
                $parts[] = "    $type $escapedName (" . implode(', ', $columnsList) . ")";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            if (!$foreignKey->isDrop()) { // Only add non-dropped foreign keys
                $parts[] = "    " . $this->generateForeignKey($foreignKey);
            }
        }

        $sql = "CREATE TABLE `$tableName` (" . PHP_EOL . implode("," . PHP_EOL, $parts) . PHP_EOL . ")";

        if (!empty($options)) {
            $optionParts = [];
            foreach ($options as $key => $value) {
                if ($key === 'CHARSET') {
                    $optionParts[] = "DEFAULT CHARSET=$value";
                } else {
                    $optionParts[] = "$key=$value";
                }
            }
            $sql .= " " . implode(" ", $optionParts);
        }

        return $sql . ";";
    }

    /**
     * @param string $tableName
     * @param array<string, ColumnDefinition|array{drop: bool}> $columns
     * @param array<string, mixed> $options
     * @param array<string, ForeignKeyDefinition> $foreignKeys
     * @return string
     */
    private function generateAlterTable(string $tableName, array $columns, array $options, array $foreignKeys): string
    {
        $alterations = [];

        // Add or modify columns
        foreach ($columns as $name => $column) {
            if (is_array($column)) {
                // Handle drop column
                $alterations[] = "DROP COLUMN `$name`";
            } else {
                // Handle add/modify column
                $alterations[] = "ADD COLUMN " . $this->generateColumnDefinition($column);
            }
        }

        // Add foreign keys
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->isDrop()) {
                $alterations[] = "DROP FOREIGN KEY `" . $foreignKey->getName() . "`";
            } else {
                $alterations[] = "ADD " . $this->generateForeignKey($foreignKey);
            }
        }

        if (empty($alterations)) {
            return "";
        }

        return "ALTER TABLE `$tableName`" . PHP_EOL . "    " . implode("," . PHP_EOL . "    ", $alterations) . ";";
    }

    /**
     * @param ColumnDefinition $column
     * @return string
     */
    private function generateColumnDefinition(ColumnDefinition $column): string
    {
        $sql = '`' . $column->getName() . '` ';

        // Add type
        $type = $column->getType();
        $sql .= strtoupper($type->value);

        // Add length/precision/set values
        if ($column->getPrecision() !== null && $column->getScale() !== null) {
            // For DECIMAL, NUMERIC, FLOAT types that use precision and scale
            $sql .= '(' . $column->getPrecision() . ',' . $column->getScale() . ')';
        } elseif ($column->getType()->isTypeWithLength() && $column->getLength() !== null) {
            // For VARCHAR, CHAR and other types that use length
            $sql .= '(' . $column->getLength() . ')';
        } elseif ($type === ColumnType::SET || $type === ColumnType::ENUM) {
            // For SET and ENUM type, we need to handle the set values
            $sql .= "('" . implode("', '", $column->getChoiceValues()) . "')";
        }

        // Add unsigned
        if ($column->isUnsigned()) {
            $sql .= ' UNSIGNED';
        }

        // Add nullable
        if (!$column->isNullable()) {
            $sql .= ' NOT NULL';
        }

        // Add default
        if ($column->getDefault() !== null) {
            if ($column->getDefault() === 'CURRENT_TIMESTAMP') {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $sql .= ' DEFAULT ' . $this->quoteValue($column->getDefault());
            }
        }

        // Add auto increment
        if ($column->isAutoIncrement()) {
            $sql .= ' AUTO_INCREMENT';
        }

        // Add comment
        if ($column->getComment() !== null) {
            $sql .= ' COMMENT ' . $this->quoteValue($column->getComment());
        }

        // Add position (AFTER clause)
        if ($column->getAfter() !== null) {
            $sql .= ' AFTER `' . $column->getAfter() . '`';
        } elseif ($column->isFirst()) {
            $sql .= ' FIRST';
        }

        return $sql;
    }

    /**
     * @param ForeignKeyDefinition $foreignKey
     * @return string
     */
    private function generateForeignKey(ForeignKeyDefinition $foreignKey): string
    {
        $name = $foreignKey->getName();
        $columns = $foreignKey->getColumns();
        $referencedTable = $foreignKey->getReferencedTable();
        $referencedColumns = $foreignKey->getReferencedColumns();
        $onUpdate = $foreignKey->getOnUpdate();
        $onDelete = $foreignKey->getOnDelete();

        $columnsList = implode(', ', array_map([SqlQuery::class, 'escape'], $columns));
        $refColumnsList = implode(', ', array_map([SqlQuery::class, 'escape'], $referencedColumns));

        $constraint = "CONSTRAINT `$name` ";
        $fkDef = "FOREIGN KEY ($columnsList) REFERENCES `$referencedTable` ($refColumnsList)";

        $actions = "";
        if ($onDelete !== null) {
            $actions .= " ON DELETE " . $onDelete->value;
        }
        if ($onUpdate !== null) {
            $actions .= " ON UPDATE " . $onUpdate->value;
        }

        return $constraint . $fkDef . $actions;
    }

    /**
     * @param mixed $value
     * @return string|float|int
     */
    private function quoteValue(mixed $value): string|float|int
    {
        if (is_numeric($value)) {
            return $value;
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_null($value)) {
            return 'NULL';
        } else {
            return "'" . str_replace("'", "''", $value) . "'";
        }
    }
}
