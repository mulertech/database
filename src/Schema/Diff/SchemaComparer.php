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
        $entityColumns = $this->buildEntityColumnsArray($entityClass);

        $this->findColumnsToAdd($tableName, $entityColumns, $databaseColumns, $diff);
        $this->findColumnsToModify($tableName, $entityColumns, $databaseColumns, $diff);
        $this->findColumnsToDrop($tableName, $entityColumns, $databaseColumns, $diff);
    }

    /**
     * Build array of entity columns from mapping
     *
     * @param class-string $entityClass
     * @return array<string, array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: string,
     *     COLUMN_DEFAULT: mixed,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * }>
     * @throws ReflectionException
     */
    private function buildEntityColumnsArray(string $entityClass): array
    {
        $entityColumns = [];

        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $columnType = $this->dbMapping->getColumnTypeDefinition($entityClass, $property);
            $isNullable = $this->dbMapping->isNullable($entityClass, $property);
            $columnDefault = $this->dbMapping->getColumnDefault($entityClass, $property);
            $columnExtra = $this->dbMapping->getExtra($entityClass, $property);
            $columnKey = $this->dbMapping->getColumnKey($entityClass, $property);

            $entityColumns[$columnName] = [
                'COLUMN_TYPE' => $columnType,
                'IS_NULLABLE' => $isNullable === false ? 'NO' : 'YES',
                'COLUMN_DEFAULT' => $columnDefault,
                'EXTRA' => $columnExtra,
                'COLUMN_KEY' => $columnKey,
            ];
        }

        return $entityColumns;
    }

    /**
     * Find columns to add (in entity mapping but not in database)
     *
     * @param string $tableName
     * @param array<string, array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: string,
     *     COLUMN_DEFAULT: mixed,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * }> $entityColumns
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
     */
    private function findColumnsToAdd(
        string $tableName,
        array $entityColumns,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
        foreach ($entityColumns as $columnName => $columnInfo) {
            if (!isset($databaseColumns[$columnName])) {
                $diff->addColumnToAdd($tableName, $columnName, $columnInfo);
            }
        }
    }

    /**
     * Find columns to modify (in both but with different definitions)
     *
     * @param string $tableName
     * @param array<string, array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: string,
     *     COLUMN_DEFAULT: mixed,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * }> $entityColumns
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
     */
    private function findColumnsToModify(
        string $tableName,
        array $entityColumns,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
        foreach ($entityColumns as $columnName => $columnInfo) {
            if (!isset($databaseColumns[$columnName])) {
                continue;
            }

            $dbColumnInfo = $databaseColumns[$columnName];
            $columnDifferences = $this->compareColumnDefinitions($columnInfo, $dbColumnInfo);

            if (!empty($columnDifferences)) {
                $diff->addColumnToModify($tableName, $columnName, $columnDifferences);
            }
        }
    }

    /**
     * Compare column definitions and return differences
     *
     * @param array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: string,
     *     COLUMN_DEFAULT: mixed,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * } $entityColumnInfo
     * @param array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     EXTRA: string,
     *     COLUMN_DEFAULT: string|null,
     *     COLUMN_KEY: string|null
     * } $dbColumnInfo
     * @return array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null}
     *     }
     */
    private function compareColumnDefinitions(array $entityColumnInfo, array $dbColumnInfo): array
    {
        $columnDifferences = [];

        $this->compareColumnType($entityColumnInfo, $dbColumnInfo, $columnDifferences);
        $this->compareNullability($entityColumnInfo, $dbColumnInfo, $columnDifferences);
        $this->compareDefaultValues($entityColumnInfo, $dbColumnInfo, $columnDifferences);

        return $columnDifferences;
    }

    /**
     * Compare column type
     *
     * @param array{COLUMN_TYPE: string|null} $entityColumnInfo
     * @param array{COLUMN_TYPE: string} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null}
     *     } $columnDifferences
     * @return void
     */
    private function compareColumnType(array $entityColumnInfo, array $dbColumnInfo, array &$columnDifferences): void
    {
        if ($entityColumnInfo['COLUMN_TYPE'] !== null
            && $entityColumnInfo['COLUMN_TYPE'] !== $dbColumnInfo['COLUMN_TYPE']
        ) {
            $columnDifferences['COLUMN_TYPE'] = [
                'from' => $dbColumnInfo['COLUMN_TYPE'],
                'to' => $entityColumnInfo['COLUMN_TYPE'],
            ];
        }
    }

    /**
     * Compare column nullability
     *
     * @param array{IS_NULLABLE: string} $entityColumnInfo
     * @param array{IS_NULLABLE: 'YES'|'NO'} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null}
     *     } $columnDifferences
     * @return void
     */
    private function compareNullability(array $entityColumnInfo, array $dbColumnInfo, array &$columnDifferences): void
    {
        if ($entityColumnInfo['IS_NULLABLE'] !== $dbColumnInfo['IS_NULLABLE']) {
            $columnDifferences['IS_NULLABLE'] = [
                'from' => $dbColumnInfo['IS_NULLABLE'],
                'to' => $entityColumnInfo['IS_NULLABLE'] === 'YES' ? 'YES' : 'NO',
            ];
        }
    }

    /**
     * Compare default values (with special handling for NULL)
     *
     * @param array{COLUMN_DEFAULT: mixed} $entityColumnInfo
     * @param array{COLUMN_DEFAULT: string|null} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null}
     *     } $columnDifferences
     * @return void
     */
    private function compareDefaultValues(array $entityColumnInfo, array $dbColumnInfo, array &$columnDifferences): void
    {
        $dbDefault = $dbColumnInfo['COLUMN_DEFAULT'];
        $mappingDefault = $entityColumnInfo['COLUMN_DEFAULT'];

        if ($this->defaultValuesAreDifferent($dbDefault, $mappingDefault)) {
            // Safely convert the mapping default to string|null for consistency
            $convertedMappingDefault = $this->convertDefaultValueToString($mappingDefault);

            $columnDifferences['COLUMN_DEFAULT'] = [
                'from' => $dbDefault,
                'to' => $convertedMappingDefault,
            ];
        }
    }

    /**
     * Safely convert a default value to string|null
     *
     * @param mixed $value
     * @return string|null
     */
    private function convertDefaultValueToString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        // For non-scalar values, return null as a safe fallback
        return null;
    }

    /**
     * Check if default values are different
     *
     * @param mixed $dbDefault
     * @param mixed $mappingDefault
     * @return bool
     */
    private function defaultValuesAreDifferent(mixed $dbDefault, mixed $mappingDefault): bool
    {
        return ($dbDefault === null && $mappingDefault !== null) ||
               ($dbDefault !== null && $mappingDefault === null) ||
               ($dbDefault !== null && $mappingDefault !== null && $dbDefault !== $mappingDefault);
    }

    /**
     * Find columns to drop (in database but not in entity mapping)
     *
     * @param string $tableName
     * @param array<string, array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: string,
     *     COLUMN_DEFAULT: mixed,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * }> $entityColumns
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
     */
    private function findColumnsToDrop(
        string $tableName,
        array $entityColumns,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
        foreach ($databaseColumns as $columnName => $columnInfo) {
            if (!isset($entityColumns[$columnName])) {
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
}
