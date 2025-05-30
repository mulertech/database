<?php

namespace MulerTech\Database\Relational\Sql;

use MulerTech\Database\ORM\EmEngine;
use PDO;

/**
 * Class InformationSchema
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class InformationSchema
{
    public const string INFORMATION_SCHEMA = 'information_schema';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $tables;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $columns;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $foreignKeys;

    /**
     * @param EmEngine $emEngine
     */
    public function __construct(private EmEngine $emEngine)
    {
    }

    /**
     * @param string $database
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
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
        $queryBuilder = new QueryBuilder($this->emEngine)
            ->select('TABLE_NAME', 'AUTO_INCREMENT')
            ->from(self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::TABLES->value)
            ->where(SqlOperations::equal('TABLE_SCHEMA', "'$database'"));

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $this->tables = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $database
     * @return void
     */
    private function populateColumns(string $database): void
    {
        $queryBuilder = new QueryBuilder($this->emEngine)
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
            ->where(SqlOperations::equal('TABLE_SCHEMA', "'$database'"));

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $this->columns = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param string $database
     * @return void
     */
    private function populateForeignKeys(string $database): void
    {
        $queryBuilder = new QueryBuilder($this->emEngine)
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
                self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::KEY_COLUMN_USAGE->value . ' k',
                self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::REFERENTIAL_CONSTRAINTS->value . ' r',
                'k.CONSTRAINT_NAME=r.CONSTRAINT_NAME'
            )
            ->where(SqlOperations::equal('k.CONSTRAINT_SCHEMA', "'$database'"))
            ->andWhere('k.REFERENCED_TABLE_SCHEMA IS NOT NULL')
            ->andWhere('k.REFERENCED_TABLE_NAME IS NOT NULL')
            ->andWhere('k.REFERENCED_COLUMN_NAME IS NOT NULL');

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $this->foreignKeys = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
    }
}
