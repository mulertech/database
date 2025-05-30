<?php

namespace MulerTech\Database\Relational\Sql\Schema;

use MulerTech\Database\Relational\Sql\SqlQuery;

/**
 * Class SchemaBuilder
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SchemaBuilder
{
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
        return "DROP TABLE " . SqlQuery::escape($tableName);
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
        return "DROP INDEX " . SqlQuery::escape($indexName) . " ON " . SqlQuery::escape($tableName);
    }
}
