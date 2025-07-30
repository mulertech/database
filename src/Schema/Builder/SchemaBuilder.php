<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Builder;

use MulerTech\Database\Core\Traits\SqlFormatterTrait;

/**
 * Schema Builder - Provides fluent interface for database schema operations
 */
class SchemaBuilder
{
    use SqlFormatterTrait;

    /**
     * Create a new table definition
     */
    public function createTable(string $tableName): TableDefinition
    {
        return new TableDefinition($tableName, TableDefinition::ACTION_CREATE);
    }

    /**
     * Alter an existing table
     */
    public function alterTable(string $tableName): TableDefinition
    {
        return new TableDefinition($tableName, TableDefinition::ACTION_ALTER);
    }

    /**
     * Drop a table
     */
    public function dropTable(string $tableName): string
    {
        return "DROP TABLE IF EXISTS {$this->escapeIdentifier($tableName)}";
    }

    /**
     * @param string $indexName
     * @param string $tableName
     * @return IndexDefinition
     */
    public function createIndex(string $indexName, string $tableName): IndexDefinition
    {
        return new IndexDefinition($indexName, $tableName);
    }

    /**
     * @param string $indexName
     * @param string $tableName
     * @return string
     */
    public function dropIndex(string $indexName, string $tableName): string
    {
        return "DROP INDEX " . $this->escapeIdentifier($indexName) . " ON " . $this->escapeIdentifier($tableName);
    }
}
