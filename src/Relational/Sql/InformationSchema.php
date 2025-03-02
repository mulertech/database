<?php

namespace MulerTech\Database\Relational\Sql;

use PDO;

class InformationSchema extends QueryBuilder
{
    public const string INFORMATION_SCHEMA = 'information_schema';

    /**
     * @var array $tables
     */
    public array $tables;
    /**
     * @var array $columns
     */
    public array $columns;
    /**
     * @var array $foreignKeys
     */
    public array $foreignKeys;

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

    /**
     * @param string $database
     * @return void
     */
    private function populateTables(string $database): void
    {
        $queryBuilder = $this
            ->select('TABLE_NAME', 'AUTO_INCREMENT')
            ->from(self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::TABLES->value)
            ->where(SqlOperations::equal('TABLE_SCHEMA', $database));

        $this->tables = $queryBuilder->getResult()->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $database
     * @return void
     */
    private function populateColumns(string $database): void
    {
        $queryBuilder = $this
            ->select(
                'TABLE_NAME',
                'COLUMN_NAME',
                'COLUMN_TYPE',
                'IS_NULLABLE',
                'EXTRA',
                'COLUMN_DEFAULT',
                'COLUMN_KEY'
            )
            ->from(self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::COLUMNS->value)
            ->where(SqlOperations::equal('TABLE_SCHEMA', $database));

        $this->columns = $queryBuilder->getResult()->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $database
     * @return void
     */
    private function populateForeignKeys(string $database): void
    {
        $queryBuilder = $this
            ->select(
                'k.TABLE_NAME',
                'k.CONSTRAINT_NAME',
                'k.COLUMN_NAME',
                'k.REFERENCED_TABLE_SCHEMA',
                'k.REFERENCED_TABLE_NAME',
                'k.REFERENCED_COLUMN_NAME',
                'r.DELETE_RULE',
                'r.UPDATE_RULE'
            )
            ->from(self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::KEY_COLUMN_USAGE->value, 'k')
            ->leftJoin(
                self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::KEY_COLUMN_USAGE->value,
                self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::REFERENTIAL_CONSTRAINTS->value . ' as r',
                'k.CONSTRAINT_NAME = r.CONSTRAINT_NAME'
            )
            ->where(SqlOperations::equal('k.CONSTRAINT_SCHEMA', $database))
            ->andWhere('k.REFERENCED_TABLE_SCHEMA IS NOT NULL')
            ->andWhere('k.REFERENCED_TABLE_NAME IS NOT NULL')
            ->andWhere('k.REFERENCED_COLUMN_NAME IS NOT NULL');

        $this->foreignKeys = $queryBuilder->getResult()->fetchAll(PDO::FETCH_ASSOC);
    }

}