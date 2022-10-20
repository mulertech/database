<?php

namespace mtphp\Database\Relational\Sql;

use mtphp\Database\ORM\EmEngine;

class InformationSchema extends QueryBuilder
{

    /**
     * It shows the role authorizations that the current user may use.
     */
    public const APPLICABLE_ROLES = 'APPLICABLE_ROLES';
    /**
     * It contains a list of supported character sets.
     */
    public const CHARACTER_SETS = 'CHARACTER_SETS';
    /**
     * It stores metadata about the constraints defined for tables in all databases.
     */
    public const CHECK_CONSTRAINTS = 'CHECK_CONSTRAINTS';
    /**
     * It shows which character sets are associated with which collations.
     */
    public const COLLATION_CHARACTER_SET_APPLICABILITY = 'COLLATION_CHARACTER_SET_APPLICABILITY';
    /**
     * It contains a list of supported collations.
     */
    public const COLLATIONS = 'COLLATIONS';
    /**
     * It contains column privilege information derived from the mysql.columns_priv grant table.
     */
    public const COLUMN_PRIVILEGES = 'COLUMN_PRIVILEGES';
    /**
     * It provides information about columns in each table on the server.
     * @todo To implement.
     */
    public const COLUMNS = 'COLUMNS';
    /**
     * It shows the enabled roles for the current session.
     */
    public const ENABLED_ROLES = 'ENABLED_ROLES';
    /**
     * It displays status information about the server's storage engines.
     */
    public const ENGINES = 'ENGINES';
    /**
     * It stores information about events on the server.
     */
    public const EVENTS = 'EVENTS';
    /**
     * It provides information about the files in wich MYSQL tablespace data is stored.
     */
    public const FILES = 'FILES';
    /**
     * It describes which key columns have constraints.
     * @todo To implement.
     */
    public const KEY_COLUMN_USAGE = 'KEY_COLUMN_USAGE';
    /**
     * It lists the words considered keywords by Sql and for each one, indicates whether it is reserved.
     */
    public const KEYWORDS = 'KEYWORDS';
    /**
     * It provides information produced by the optimizer tracing capability for traced statements.
     */
    public const OPTIMIZER_TRACE = 'OPTIMIZER_TRACE';
    /**
     * It provides information about parameters for stored routines (stored procedures and stored functions),
     * and about return values for stored functions.
     */
    public const PARAMETERS = 'PARAMETERS';
    /**
     * It provides information about table partitions. Each row in this table corresponds to an individual partition
     * or subpartition of a partitioned table.
     */
    public const PARTITIONS = 'PARTITIONS';
    /**
     * It provides information about server plugins.
     */
    public const PLUGINS = 'PLUGINS';
    /**
     * It indicates the operations currently being performed by the set of threads executing within the server.
     */
    public const PROCESSLIST = 'PROCESSLIST';
    /**
     * It provides statement profiling information.
     */
    public const PROFILING = 'PROFILING';
    /**
     * It provides information about foreign keys.
     * @todo To implement.
     */
    public const REFERENTIAL_CONSTRAINTS = 'REFERENTIAL_CONSTRAINTS';
    /**
     * It provides information about stored routines (stored procedures and stored functions).
     */
    public const ROUTINES = 'ROUTINES';
    /**
     * It provides information about schema (database) privileges. It takes its values from the mysql.db system table.
     */
    public const SCHEMA_PRIVILEGES = 'SCHEMA_PRIVILEGES';
    /**
     * It provides information about databases.
     */
    public const SCHEMATA = 'SCHEMATA';
    /**
     * It provides information about table indexes.
     * @todo To implement if it is necessary.
     */
    public const STATISTICS = 'STATISTICS';
    /**
     * It describes which tables have constraints.
     * @todo To implement if it is necessary.
     */
    public const TABLE_CONSTRAINTS = 'TABLE_CONSTRAINTS';
    /**
     * It provides information about table privileges.
     */
    public const TABLE_PRIVILEGES = 'TABLE_PRIVILEGES';
    /**
     * It provides information about tables in databases.
     * @todo To implement.
     */
    public const TABLES = 'TABLES';
    /**
     * It provides information about triggers.
     */
    public const TRIGGERS = 'TRIGGERS';
    /**
     * It provides information about global privileges. It takes its values from the mysql.user system table.
     */
    public const USER_PRIVILEGES = 'USER_PRIVILEGES';
    /**
     * It provides information about views in databases.
     */
    public const VIEWS = 'VIEWS';

    /**
     * @var array $tables
     */
    public $tables;
    /**
     * @var array $columns
     */
    public $columns;
    /**
     * @var array $foreignKeys
     */
    public $foreignKeys;

    /**
     * @param string $database
     * @return array
     */
    public function getTables(string $database): array
    {
        if (empty($this->tables)) {
            $this->populateTables($database);
        }
        return $this->tables;
    }

    /**
     * @param string $database
     * @return array
     */
    public function getColumns(string $database): array
    {
        if (empty($this->columns)) {
            $this->populateColumns($database);
        }
        return $this->columns;
    }

    /**
     * @param string $database
     * @return array
     */
    public function getForeignKeys(string $database): array
    {
        if (empty($this->foreignKeys)) {
            $this->populateForeignKeys($database);
        }
        return $this->foreignKeys;
    }

    private function populateTables(string $database)
    {
        //TABLES req
//        $tablesreq = "SELECT TABLE_NAME, AUTO_INCREMENT FROM `information_schema`.`TABLES` WHERE TABLE_SCHEMA = '" . $dbName . "'";
//        $reqtables = $this->em->getPdm()->query($tablesreq);
//        $tables_structure = $reqtables->fetchAll(PDO::FETCH_ASSOC);
//        $reqtables->closeCursor();

        $queryBuilder = $this->select('TABLE_NAME', 'AUTO_INCREMENT')->from('information_schema.TABLES')->where(SqlOperations::equal('TABLE_SCHEMA', $database));
        $this->tables = $queryBuilder->getResult(self::RESULT_TYPE_ARRAY);
    }

    private function populateColumns(string $database)
    {
        //COLUMNS req
//        $columnsreq = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA, COLUMN_DEFAULT, COLUMN_KEY FROM `information_schema`.`COLUMNS` WHERE TABLE_SCHEMA = '" . $dbName . "'";
//        $reqcolumns = $this->em->getPdm()->query($columnsreq);
//        $column_structure = $reqcolumns->fetchAll(PDO::FETCH_ASSOC);
//        $reqcolumns->closeCursor();

        $queryBuilder = $this->select('TABLE_NAME', 'COLUMN_NAME', 'COLUMN_TYPE', 'IS_NULLABLE', 'EXTRA', 'COLUMN_DEFAULT', 'COLUMN_KEY')->from('information_schema.COLUMNS')->where(SqlOperations::equal('TABLE_SCHEMA', $database));
        $this->columns = $queryBuilder->getResult(self::RESULT_TYPE_ARRAY);
    }

    private function populateForeignKeys(string $database)
    {
        //KEY_COLUMN_USAGE AND REFERENTIAL_CONSTRAINTS
//        $constraintreq = "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
//            FROM `information_schema`.`KEY_COLUMN_USAGE` AS k LEFT JOIN `information_schema`.`REFERENTIAL_CONSTRAINTS` AS r
//            ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
//            WHERE k.CONSTRAINT_SCHEMA = '" . $dbName . "'
//            AND k.REFERENCED_TABLE_SCHEMA IS NOT NULL
//            AND k.REFERENCED_TABLE_NAME IS NOT NULL
//            AND k.REFERENCED_COLUMN_NAME IS NOT NULL";
//        $reqconstraints = $this->em->getPdm()->query($constraintreq);
//        $constraints_structure = $reqconstraints->fetchAll(PDO::FETCH_ASSOC);
//        $reqconstraints->closeCursor();

        $queryBuilder = $this
            ->select('k.TABLE_NAME', 'k.CONSTRAINT_NAME', 'k.COLUMN_NAME', 'k.REFERENCED_TABLE_SCHEMA', 'k.REFERENCED_TABLE_NAME', 'k.REFERENCED_COLUMN_NAME', 'r.DELETE_RULE', 'r.UPDATE_RULE')
            ->from('information_schema.KEY_COLUMN_USAGE', 'k')
            ->leftJoin('information_schema.KEY_COLUMN_USAGE', 'information_schema.REFERENTIAL_CONSTRAINTS as r', 'k.CONSTRAINT_NAME = r.CONSTRAINT_NAME')
            ->where(SqlOperations::equal('k.CONSTRAINT_SCHEMA', $database))
            ->andWhere('k.REFERENCED_TABLE_SCHEMA IS NOT NULL')
            ->andWhere('k.REFERENCED_TABLE_NAME IS NOT NULL')
            ->andWhere('k.REFERENCED_COLUMN_NAME IS NOT NULL');
        $this->foreignKeys = $queryBuilder->getResult(self::RESULT_TYPE_ARRAY);
    }

}