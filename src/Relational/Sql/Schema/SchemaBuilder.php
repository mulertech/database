<?php

declare(strict_types=1);

namespace MulerTech\Database\Relational\Sql\Schema;

use MulerTech\Database\Core\Traits\SqlFormatterTrait;

/**
 * Class SchemaBuilder
 *
 * Builder for database schema operations including table creation, modification and deletion.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SchemaBuilder
{
    use SqlFormatterTrait;

    /**
     * @param string $tableName
     * @return TableDefinition
     */
    public function createTable(string $tableName): TableDefinition
    {
        return new TableDefinition($tableName, true);
    }

    /**
     * @param string $tableName
     * @return TableDefinition
     */
    public function alterTable(string $tableName): TableDefinition
    {
        return new TableDefinition($tableName, false);
    }

    /**
     * @param string $tableName
     * @return string
     */
    public function dropTable(string $tableName): string
    {
        return "DROP TABLE " . $this->escapeIdentifier($tableName);
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
