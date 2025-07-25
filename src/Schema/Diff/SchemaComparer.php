<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Schema\Information\InformationSchema;
use ReflectionException;
use RuntimeException;

/**
 * Compare database schema with entity mappings
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class SchemaComparer
{
    /**
     * @param InformationSchema $informationSchema
     * @param DbMappingInterface $dbMapping
     * @param string $databaseName
     */
    public function __construct(
        private InformationSchema $informationSchema,
        private DbMappingInterface $dbMapping,
        private string $databaseName
    ) {
    }

    /**
     * Get database table info by table name
     *
     * @param string $tableName
     * @return array{TABLE_NAME: string, AUTO_INCREMENT: int|null}|null
     */
    private function getTableInfo(string $tableName): ?array
    {
        return array_find(
            $this->informationSchema->getTables($this->databaseName),
            static fn ($table) => $table['TABLE_NAME'] === $tableName
        );
    }

    /**
     * Get columns for a specific table
     *
     * @param string $tableName
     * @return array<string, array{
     *       TABLE_NAME: string,
     *       COLUMN_NAME: string,
     *       COLUMN_TYPE: string,
     *       IS_NULLABLE: 'YES'|'NO',
     *       EXTRA: string,
     *       COLUMN_DEFAULT: string|null,
     *       COLUMN_KEY: string|null
     *   }>
     */
    private function getTableColumns(string $tableName): array
    {
        $columns = [];
        foreach ($this->informationSchema->getColumns($this->databaseName) as $column) {
            if ($column['TABLE_NAME'] === $tableName) {
                $columns[$column['COLUMN_NAME']] = $column;
            }
        }

        return $columns;
    }

    /**
     * Get foreign keys for a specific table
     *
     * @param string $tableName
     * @return array<string, array{
     *       TABLE_NAME: string,
     *       CONSTRAINT_NAME: string,
     *       COLUMN_NAME: string,
     *       REFERENCED_TABLE_SCHEMA: string|null,
     *       REFERENCED_TABLE_NAME: string|null,
     *       REFERENCED_COLUMN_NAME: string|null,
     *       DELETE_RULE: string|null,
     *       UPDATE_RULE: string|null
     *   }>
     */
    private function getTableForeignKeys(string $tableName): array
    {
        $foreignKeys = [];

        foreach ($this->informationSchema->getForeignKeys($this->databaseName) as $foreignKey) {
            if ($foreignKey['TABLE_NAME'] === $tableName) {
                $foreignKeys[$foreignKey['CONSTRAINT_NAME']] = $foreignKey;
            }
        }

        return $foreignKeys;
    }

    /**
     * Compare database schema with entity mappings
     *
     * @return SchemaDifference
     * @throws ReflectionException
     */
    public function compare(): SchemaDifference
    {
        $diff = new SchemaDifference();

        // Get all entity tables
        $entityTables = [];
        foreach ($this->dbMapping->getEntities() as $entityClass) {
            $tableName = $this->dbMapping->getTableName($entityClass);
            if ($tableName) {
                $entityTables[$tableName] = $entityClass;
            }
        }

        // Find tables to create (in entity mappings but not in database)
        foreach ($entityTables as $tableName => $entityClass) {
            if (!$this->getTableInfo($tableName)) {
                $diff->addTableToCreate($tableName, $entityClass);
            }
        }

        // Find tables to drop (in database but not in entity mappings)
        foreach ($this->informationSchema->getTables($this->databaseName) as $table) {
            $tableName = $table['TABLE_NAME'];

            // Skip migration_history table
            if ($tableName === 'migration_history') {
                continue;
            }

            if (!isset($entityTables[$tableName])) {
                $diff->addTableToDrop($tableName);
            }
        }

        // Compare columns for existing tables
        foreach ($entityTables as $tableName => $entityClass) {
            // Skip tables that need to be created
            if (!in_array($tableName, $diff->getTablesToCreate(), true)) {
                $this->compareColumns($tableName, $entityClass, $diff);
                $this->compareForeignKeys($tableName, $entityClass, $diff);
            }
        }

        return $diff;
    }

    /**
     * Compare columns between entity mapping and database
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function compareColumns(string $tableName, string $entityClass, SchemaDifference $diff): void
    {
        $databaseColumns = $this->getTableColumns($tableName);

        // Use DbMapping directly instead of building entity columns array
        $this->findColumnsToAdd($tableName, $entityClass, $databaseColumns, $diff);
        $this->findColumnsToModify($tableName, $entityClass, $databaseColumns, $diff);
        $this->findColumnsToDrop($tableName, $entityClass, $databaseColumns, $diff);
    }

    /**
     * Find columns to add (in entity mapping but not in database)
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     EXTRA: string,
     *     COLUMN_DEFAULT: string|null,
     *     COLUMN_KEY: string|null
     * }> $databaseColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function findColumnsToAdd(
        string $tableName,
        string $entityClass,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
        // Get entity columns directly from DbMapping
        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            if (!isset($databaseColumns[$columnName])) {
                // Build column info directly from DbMapping
                $columnInfo = [
                    'COLUMN_TYPE' => $this->dbMapping->getColumnTypeDefinition($entityClass, $property),
                    'IS_NULLABLE' => $this->dbMapping->isNullable($entityClass, $property) === false ? 'NO' : 'YES',
                    'COLUMN_DEFAULT' => $this->dbMapping->getColumnDefault($entityClass, $property),
                    'EXTRA' => $this->dbMapping->getExtra($entityClass, $property),
                    'COLUMN_KEY' => $this->dbMapping->getColumnKey($entityClass, $property),
                ];

                if ($columnInfo['COLUMN_TYPE'] === null) {
                    throw new RuntimeException(
                        "Column type for $entityClass::$property is not defined in DbMapping"
                    );
                }

                $diff->addColumnToAdd($tableName, $columnName, $columnInfo);
            }
        }
    }

    /**
     * Find columns to modify (in both but with different definitions)
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     EXTRA: string,
     *     COLUMN_DEFAULT: string|null,
     *     COLUMN_KEY: string|null
     * }> $databaseColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function findColumnsToModify(
        string $tableName,
        string $entityClass,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            if (!isset($databaseColumns[$columnName])) {
                continue;
            }

            $dbColumnInfo = $databaseColumns[$columnName];

            // Get entity column info directly from DbMapping
            $entityColumnInfo = [
                'COLUMN_TYPE' => $this->dbMapping->getColumnTypeDefinition($entityClass, $property),
                'IS_NULLABLE' => $this->dbMapping->isNullable($entityClass, $property) === false ? 'NO' : 'YES',
                'COLUMN_DEFAULT' => $this->dbMapping->getColumnDefault($entityClass, $property),
                'EXTRA' => $this->dbMapping->getExtra($entityClass, $property),
                'COLUMN_KEY' => $this->dbMapping->getColumnKey($entityClass, $property),
            ];

            if ($entityColumnInfo['COLUMN_TYPE'] === null) {
                throw new RuntimeException(
                    "Column type for $entityClass::$property is not defined in DbMapping"
                );
            }

            $columnDifferences = $this->compareColumnDefinitions($entityColumnInfo, $dbColumnInfo);

            if (!empty($columnDifferences)) {
                $diff->addColumnToModify($tableName, $columnName, $columnDifferences);
            }
        }
    }

    /**
     * Find columns to drop (in database but not in entity mapping)
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     EXTRA: string,
     *     COLUMN_DEFAULT: string|null,
     *     COLUMN_KEY: string|null
     * }> $databaseColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function findColumnsToDrop(
        string $tableName,
        string $entityClass,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
        $entityColumns = $this->dbMapping->getPropertiesColumns($entityClass);

        foreach ($databaseColumns as $columnName => $columnInfo) {
            if (!in_array($columnName, $entityColumns, true)) {
                $diff->addColumnToDrop($tableName, $columnName);
            }
        }
    }

    /**
     * Compare foreign keys between entity mapping and database
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function compareForeignKeys(string $tableName, string $entityClass, SchemaDifference $diff): void
    {
        $databaseForeignKeys = $this->getTableForeignKeys($tableName);
        $entityForeignKeys = [];

        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $foreignKey = $this->dbMapping->getForeignKey($entityClass, $property);

            if ($foreignKey === null) {
                continue;
            }

            $constraintName = $this->dbMapping->getConstraintName($entityClass, $property);

            if ($constraintName === null) {
                throw new RuntimeException(
                    "Missing constraint name for foreign key on $entityClass::$property"
                );
            }

            $entityForeignKeys[$constraintName] = [
                'COLUMN_NAME' => $columnName,
                'REFERENCED_TABLE_NAME' => $this->dbMapping->getReferencedTable($entityClass, $property),
                'REFERENCED_COLUMN_NAME' => $this->dbMapping->getReferencedColumn($entityClass, $property),
                'DELETE_RULE' => $this->dbMapping->getDeleteRule($entityClass, $property),
                'UPDATE_RULE' => $this->dbMapping->getUpdateRule($entityClass, $property),
            ];
        }

        // Find foreign keys to add
        foreach ($entityForeignKeys as $constraintName => $foreignKeyInfo) {
            if (!isset($databaseForeignKeys[$constraintName])) {
                $diff->addForeignKeyToAdd($tableName, $constraintName, $foreignKeyInfo);
            }
        }

        // Find foreign keys to drop
        foreach ($databaseForeignKeys as $constraintName => $foreignKeyInfo) {
            if (!isset($entityForeignKeys[$constraintName])) {
                $diff->addForeignKeyToDrop($tableName, $constraintName);
            }
        }
    }

    /**
     * Compare column definitions and return differences
     *
     * @param array{
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     COLUMN_DEFAULT: string|null,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * } $entityColumnInfo
     * @param array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     COLUMN_DEFAULT: string|null,
     *     EXTRA: string,
     *     COLUMN_KEY: string|null
     * } $dbColumnInfo
     * @return array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * }
     */
    private function compareColumnDefinitions(array $entityColumnInfo, array $dbColumnInfo): array
    {
        $differences = [];

        // Compare column type
        if ($entityColumnInfo['COLUMN_TYPE'] !== $dbColumnInfo['COLUMN_TYPE']) {
            $differences['COLUMN_TYPE'] = [
                'from' => $dbColumnInfo['COLUMN_TYPE'],
                'to' => $entityColumnInfo['COLUMN_TYPE'],
            ];
        }

        // Compare nullable
        if ($entityColumnInfo['IS_NULLABLE'] !== $dbColumnInfo['IS_NULLABLE']) {
            $differences['IS_NULLABLE'] = [
                'from' => $dbColumnInfo['IS_NULLABLE'],
                'to' => $entityColumnInfo['IS_NULLABLE'],
            ];
        }

        // Compare default value - normalize empty string and null
        $entityDefault = $entityColumnInfo['COLUMN_DEFAULT'] === '' ? null : $entityColumnInfo['COLUMN_DEFAULT'];
        $dbDefault = $dbColumnInfo['COLUMN_DEFAULT'] === '' ? null : $dbColumnInfo['COLUMN_DEFAULT'];

        if ($entityDefault !== $dbDefault) {
            $differences['COLUMN_DEFAULT'] = [
                'from' => $dbColumnInfo['COLUMN_DEFAULT'],
                'to' => $entityColumnInfo['COLUMN_DEFAULT'],
            ];
        }

        // Compare extra (like auto_increment) - normalize empty string and null
        $entityExtra = $entityColumnInfo['EXTRA'] === '' ? null : $entityColumnInfo['EXTRA'];
        $dbExtra = $dbColumnInfo['EXTRA'] === '' ? null : $dbColumnInfo['EXTRA'];

        if ($entityExtra !== $dbExtra) {
            $differences['EXTRA'] = [
                'from' => $dbColumnInfo['EXTRA'] === '' ? null : $dbColumnInfo['EXTRA'],
                'to' => $entityColumnInfo['EXTRA'],
            ];
        }

        return $differences;
    }
}
