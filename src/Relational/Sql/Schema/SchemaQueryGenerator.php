<?php

namespace MulerTech\Database\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\SqlQuery;

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
        $indexes = $tableDefinition->getIndexes();
        $foreignKeys = $tableDefinition->getForeignKeys();
        $options = $tableDefinition->getOptions();

        $escapedTableName = SqlQuery::escape($tableName);

        if ($isCreate) {
            return $this->generateCreateTable(
                $escapedTableName,
                $columns,
                $indexes,
                $foreignKeys,
                $options
            );
        } else {
            return $this->generateAlterTable(
                $escapedTableName,
                $columns,
                $indexes,
                $foreignKeys
            );
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
            $parts[] = "  " . $this->generateColumnDefinition($column);
        }

        foreach ($indexes as $name => $index) {
            if ($name === 'PRIMARY') {
                $columns = array_map([SqlQuery::class, 'escape'], $index['columns']);
                $parts[] = "  PRIMARY KEY (" . implode(', ', $columns) . ")";
            } else {
                $type = $index['type'] ?? 'INDEX';
                $name = SqlQuery::escape($name);
                $columns = array_map([SqlQuery::class, 'escape'], $index['columns']);
                $parts[] = "  $type $name (" . implode(', ', $columns) . ")";
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $parts[] = "  " . $this->generateForeignKey($foreignKey);
        }

        $sql = "CREATE TABLE $tableName (" . PHP_EOL . implode("," . PHP_EOL, $parts) . PHP_EOL . ")";

        if (!empty($options)) {
            $optionParts = [];
            foreach ($options as $key => $value) {
                $optionParts[] = "$key=$value";
            }
            $sql .= " " . implode(" ", $optionParts);
        }

        return $sql . ";";
    }

    /**
     * @param string $tableName The escaped table name
     * @param array<string, object|array{drop: bool}> $columns Column definitions or drop specifications
     * @param array<string, array{type?: string, columns: array<int, string>, drop?: bool}> $indexes Index definitions
     * @param array<string, ForeignKeyDefinition> $foreignKeys Foreign key definitions
     * @return string The generated ALTER TABLE SQL statement, empty string if no alterations
     */
    private function generateAlterTable(
        string $tableName,
        array $columns,
        array $indexes,
        array $foreignKeys
    ): string {
        $alterParts = [];

        // Columns - add modify or drop
        foreach ($columns as $name => $column) {
            if (is_array($column)) {
                $alterParts[] = "DROP COLUMN " . SqlQuery::escape($name);
            } else {
                // Utiliser une méthode isModify si disponible
                $isModify = method_exists($column, 'isModify') && $column->isModify();

                $action = $isModify ? "MODIFY COLUMN" : "ADD COLUMN";
                $alterParts[] = "$action " . $this->generateColumnDefinition($column);
            }
        }

        // Index - add or drop
        foreach ($indexes as $name => $index) {
            if (isset($index['drop']) && $index['drop']) {
                if ($name === 'PRIMARY') {
                    $alterParts[] = "DROP PRIMARY KEY";
                } else {
                    $alterParts[] = "DROP INDEX " . SqlQuery::escape($name);
                }
            } else {
                $name = SqlQuery::escape($name);
                $type = $index['type'] ?? 'INDEX';
                $columns = array_map([SqlQuery::class, 'escape'], $index['columns']);

                if ($name === 'PRIMARY') {
                    $alterParts[] = "ADD PRIMARY KEY (" . implode(', ', $columns) . ")";
                } else {
                    $alterParts[] = "ADD $type $name (" . implode(', ', $columns) . ")";
                }
            }
        }

        // Foreign keys - add or drop
        foreach ($foreignKeys as $name => $foreignKey) {
            if ($foreignKey->isDrop()) {
                $alterParts[] = "DROP FOREIGN KEY " . SqlQuery::escape($name);
            } else {
                $alterParts[] = "ADD " . $this->generateForeignKey($foreignKey);
            }
        }

        if (empty($alterParts)) {
            return "";
        }

        return "ALTER TABLE $tableName " . PHP_EOL . "  " . implode("," . PHP_EOL . "  ", $alterParts) . ";";
    }

    /**
     * @param mixed $column
     * @return string
     */
    private function generateColumnDefinition(mixed $column): string
    {
        if (is_array($column)) {
            return ""; // If it's an array, we assume it's a drop column
        }

        $name = $column->getName();
        $type = $column->getType();
        $length = $column->getLength();
        $precision = $column->getPrecision();
        $scale = $column->getScale();
        $nullable = $column->isNullable();
        $default = $column->getDefault();
        $autoIncrement = $column->isAutoIncrement();
        $unsigned = $column->isUnsigned();
        $comment = $column->getComment();
        $after = $column->getAfter();

        $parts = [SqlQuery::escape($name)];

        // Type and length/precision
        $typeDef = $type->value;
        if ($length !== null) {
            $typeDef .= "($length)";
        } elseif ($precision !== null && $scale !== null) {
            $typeDef .= "($precision,$scale)";
        }
        $parts[] = $typeDef;

        // Unsigned
        if ($unsigned) {
            $parts[] = "UNSIGNED";
        }

        // Nullable
        $parts[] = $nullable ? "NULL" : "NOT NULL";

        // Default
        if ($default !== null) {
            if ($default === 'CURRENT_TIMESTAMP') {
                $parts[] = "DEFAULT CURRENT_TIMESTAMP";
            } else {
                $parts[] = "DEFAULT " . $this->quoteValue($default);
            }
        }

        // Auto increment
        if ($autoIncrement) {
            $parts[] = "AUTO_INCREMENT";
        }

        // Comment
        if ($comment !== null) {
            $parts[] = "COMMENT " . $this->quoteValue($comment);
        }

        // After
        if ($after !== null) {
            $parts[] = "AFTER " . SqlQuery::escape($after);
        }

        return implode(" ", $parts);
    }

    /**
     * @param ForeignKeyDefinition $foreignKey The foreign key definition object
     * @return string The SQL fragment for the foreign key constraint
     */
    private function generateForeignKey(ForeignKeyDefinition $foreignKey): string
    {
        $name = $foreignKey->getName();
        $columns = $foreignKey->getColumns();
        $referencedTable = SqlQuery::escape($foreignKey->getReferencedTable());
        $referencedColumns = $foreignKey->getReferencedColumns();
        $onUpdate = $foreignKey->getOnUpdate()->value;
        $onDelete = $foreignKey->getOnDelete()->value;

        $columnsList = implode(', ', array_map([SqlQuery::class, 'escape'], $columns));
        $refColumnsList = implode(', ', array_map([SqlQuery::class, 'escape'], $referencedColumns));

        $constraint = "CONSTRAINT " . SqlQuery::escape($name) . " ";
        $fkDef = "FOREIGN KEY ($columnsList) REFERENCES $referencedTable ($refColumnsList)";
        $actions = " ON DELETE $onDelete ON UPDATE $onUpdate";

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
