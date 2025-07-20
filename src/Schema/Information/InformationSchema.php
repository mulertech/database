<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Information;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Builder\QueryBuilder;
use PDO;

/**
 * Class InformationSchema
 *
 * Provides access to database metadata through the information_schema.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class InformationSchema
{
    public const string INFORMATION_SCHEMA = 'information_schema';

    /**
     * @var array<int, array{TABLE_NAME: string, AUTO_INCREMENT: int|null}>
     */
    public array $tables;

    /**
     * @var array<int, array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     EXTRA: string,
     *     COLUMN_DEFAULT: string|null,
     *     COLUMN_KEY: string|null
     * }>
     */
    public array $columns;

    /**
     * @var array<int, array{
     *     TABLE_NAME: string,
     *     CONSTRAINT_NAME: string,
     *     COLUMN_NAME: string,
     *     REFERENCED_TABLE_SCHEMA: string|null,
     *     REFERENCED_TABLE_NAME: string|null,
     *     REFERENCED_COLUMN_NAME: string|null,
     *     DELETE_RULE: string|null,
     *     UPDATE_RULE: string|null
     * }>
     */
    public array $foreignKeys;

    /**
     * @param EmEngine $emEngine
     */
    public function __construct(private readonly EmEngine $emEngine)
    {
    }

    /**
     * @param string $database
     * @return array<int, array{TABLE_NAME: string, AUTO_INCREMENT: int|null}>
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
     * @return array<int, array{
     *      TABLE_NAME: string,
     *      COLUMN_NAME: string,
     *      COLUMN_TYPE: string,
     *      IS_NULLABLE: 'YES'|'NO',
     *      EXTRA: string,
     *      COLUMN_DEFAULT: string|null,
     *      COLUMN_KEY: string|null
     *  }>
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
     * @return array<int, array{
     *      TABLE_NAME: string,
     *      CONSTRAINT_NAME: string,
     *      COLUMN_NAME: string,
     *      REFERENCED_TABLE_SCHEMA: string|null,
     *      REFERENCED_TABLE_NAME: string|null,
     *      REFERENCED_COLUMN_NAME: string|null,
     *      DELETE_RULE: string|null,
     *      UPDATE_RULE: string|null
     *  }>
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
            ->where('TABLE_SCHEMA', $database);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        /**
         * @var array<int, array{TABLE_NAME: string, AUTO_INCREMENT: int|null}> $result
         */
        $result = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->tables = $result;
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
            ->where('TABLE_SCHEMA', $database);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        /**
         * @var array<int, array{
         *     TABLE_NAME: string,
         *     COLUMN_NAME: string,
         *     COLUMN_TYPE: string,
         *     IS_NULLABLE: 'YES'|'NO',
         *     EXTRA: string,
         *     COLUMN_DEFAULT: string|null,
         *     COLUMN_KEY: string|null
         * }> $result
         */
        $result = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->columns = $result;
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
                self::INFORMATION_SCHEMA . '.' . InformationSchemaTables::REFERENTIAL_CONSTRAINTS->value,
                'k.CONSTRAINT_NAME',
                'r.CONSTRAINT_NAME',
                'r'
            )
            ->where('k.CONSTRAINT_SCHEMA', $database)
            ->whereNotNull('k.REFERENCED_TABLE_SCHEMA')
            ->whereNotNull('k.REFERENCED_TABLE_NAME')
            ->whereNotNull('k.REFERENCED_COLUMN_NAME');

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        /**
         * @var array<int, array{
         *       TABLE_NAME: string,
         *       CONSTRAINT_NAME: string,
         *       COLUMN_NAME: string,
         *       REFERENCED_TABLE_SCHEMA: string|null,
         *       REFERENCED_TABLE_NAME: string|null,
         *       REFERENCED_COLUMN_NAME: string|null,
         *       DELETE_RULE: string|null,
         *       UPDATE_RULE: string|null
         *   }> $result
         */
        $result = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->foreignKeys = $result;
    }
}
