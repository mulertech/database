<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\Database\Schema\Information\InformationSchema;
use ReflectionException;
use RuntimeException;

/**
 * Compare database schema with entity mappings
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SchemaComparer
{
    /**
     * Cache for entity properties columns by entity class
     * @var array<class-string, array<string, string>>
     */
    private array $propertiesColumnsCache = [];

    /**
     * Cache for entity table names by entity class
     * @var array<class-string, string|null>
     */
    private array $tableNameCache = [];

    /**
     * Cache for column info by entity class and property
     * @var array<string, array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: 'YES'|'NO',
     *     COLUMN_DEFAULT: string|null,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * }>
     */
    private array $columnInfoCache = [];

    /**
     * Cache for foreign key info by entity class and property
     * @var array<string, array{
     *     foreignKey: mixed,
     *     constraintName: string|null,
     *     referencedTable: string|null,
     *     referencedColumn: string|null,
     *     deleteRule: FkRule|null,
     *     updateRule: FkRule|null
     * }|null>
     */
    private array $foreignKeyInfoCache = [];

    /**
     * @param InformationSchema $informationSchema
     * @param DbMappingInterface $dbMapping
     * @param string $databaseName
     */
    public function __construct(
        private readonly InformationSchema $informationSchema,
        private readonly DbMappingInterface $dbMapping,
        private readonly string $databaseName
    ) {
    }

    /**
     * Get cached properties columns for entity class
     * @param class-string $entityClass
     * @return array<string, string>
     * @throws ReflectionException
     */
    private function getCachedPropertiesColumns(string $entityClass): array
    {
        if (!isset($this->propertiesColumnsCache[$entityClass])) {
            $this->propertiesColumnsCache[$entityClass] = $this->dbMapping->getPropertiesColumns($entityClass);
        }
        return $this->propertiesColumnsCache[$entityClass];
    }

    /**
     * Get cached table name for entity class
     * @param class-string $entityClass
     * @return string|null
     * @throws ReflectionException
     */
    private function getCachedTableName(string $entityClass): ?string
    {
        if (!isset($this->tableNameCache[$entityClass])) {
            $this->tableNameCache[$entityClass] = $this->dbMapping->getTableName($entityClass);
        }
        return $this->tableNameCache[$entityClass];
    }

    /**
     * Get cached column info for entity property
     * @param class-string $entityClass
     * @param string $property
     * @return array{
     *     COLUMN_TYPE: string|null,
     *     IS_NULLABLE: 'YES'|'NO',
     *     COLUMN_DEFAULT: string|null,
     *     EXTRA: string|null,
     *     COLUMN_KEY: string|null
     * }
     * @throws ReflectionException
     */
    private function getCachedColumnInfo(string $entityClass, string $property): array
    {
        $cacheKey = $entityClass . '::' . $property;
        if (!isset($this->columnInfoCache[$cacheKey])) {
            $this->columnInfoCache[$cacheKey] = [
                'COLUMN_TYPE' => $this->dbMapping->getColumnTypeDefinition($entityClass, $property),
                'IS_NULLABLE' => $this->dbMapping->isNullable($entityClass, $property) === false ? 'NO' : 'YES',
                'COLUMN_DEFAULT' => $this->dbMapping->getColumnDefault($entityClass, $property),
                'EXTRA' => $this->dbMapping->getExtra($entityClass, $property),
                'COLUMN_KEY' => $this->dbMapping->getColumnKey($entityClass, $property),
            ];
        }
        return $this->columnInfoCache[$cacheKey];
    }

    /**
     * Get cached foreign key info for entity property
     * @param class-string $entityClass
     * @param string $property
     * @return array{
     *     foreignKey: mixed,
     *     constraintName: string|null,
     *     referencedTable: string|null,
     *     referencedColumn: string|null,
     *     deleteRule: FkRule|null,
     *     updateRule: FkRule|null
     * }|null
     * @throws ReflectionException
     */
    private function getCachedForeignKeyInfo(string $entityClass, string $property): ?array
    {
        $cacheKey = $entityClass . '::' . $property;
        if (!isset($this->foreignKeyInfoCache[$cacheKey])) {
            $foreignKey = $this->dbMapping->getForeignKey($entityClass, $property);
            if ($foreignKey === null) {
                $this->foreignKeyInfoCache[$cacheKey] = null;
            } else {
                $this->foreignKeyInfoCache[$cacheKey] = [
                    'foreignKey' => $foreignKey,
                    'constraintName' => $this->dbMapping->getConstraintName($entityClass, $property),
                    'referencedTable' => $this->dbMapping->getReferencedTable($entityClass, $property),
                    'referencedColumn' => $this->dbMapping->getReferencedColumn($entityClass, $property),
                    'deleteRule' => $this->dbMapping->getDeleteRule($entityClass, $property),
                    'updateRule' => $this->dbMapping->getUpdateRule($entityClass, $property),
                ];
            }
        }
        return $this->foreignKeyInfoCache[$cacheKey];
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
            $tableName = $this->getCachedTableName($entityClass);
            if ($tableName) {
                $entityTables[$tableName] = $entityClass;
            }
        }

        // Find tables to create (in entity mappings but not in database)
        foreach ($entityTables as $tableName => $entityClass) {
            if (!$this->getTableInfo($tableName)) {
                // Validate that the table has columns before adding it
                $columns = $this->dbMapping->getColumns($entityClass);
                if (empty($columns)) {
                    throw new RuntimeException("Table '$tableName' has no columns defined.");
                }
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
                // Use cached properties columns
                $entityPropertiesColumns = $this->getCachedPropertiesColumns($entityClass);

                $this->compareColumns($tableName, $entityClass, $entityPropertiesColumns, $diff);
                $this->compareForeignKeys($tableName, $entityClass, $entityPropertiesColumns, $diff);
            }
        }

        return $diff;
    }

    /**
     * Compare columns between entity mapping and database
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, string> $entityPropertiesColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function compareColumns(
        string $tableName,
        string $entityClass,
        array $entityPropertiesColumns,
        SchemaDifference $diff
    ): void {
        $databaseColumns = $this->getTableColumns($tableName);

        $this->findColumnsToAdd($tableName, $entityClass, $databaseColumns, $entityPropertiesColumns, $diff);
        $this->findColumnsToModify($tableName, $entityClass, $databaseColumns, $entityPropertiesColumns, $diff);
        $this->findColumnsToDrop($tableName, $databaseColumns, $entityPropertiesColumns, $diff);
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
     * @param array<string, string> $entityPropertiesColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function findColumnsToAdd(
        string $tableName,
        string $entityClass,
        array $databaseColumns,
        array $entityPropertiesColumns,
        SchemaDifference $diff
    ): void {
        // Use cached entity columns instead of calling dbMapping again
        foreach ($entityPropertiesColumns as $property => $columnName) {
            if (isset($databaseColumns[$columnName])) {
                continue;
            }

            $columnInfo = $this->getCachedColumnInfo($entityClass, $property);

            if ($columnInfo['COLUMN_TYPE'] === null) {
                throw new RuntimeException(
                    "Column type for $entityClass::$property is not defined in DbMapping"
                );
            }

            $diff->addColumnToAdd($tableName, $columnName, $columnInfo);
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
     * @param array<string, string> $entityPropertiesColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function findColumnsToModify(
        string $tableName,
        string $entityClass,
        array $databaseColumns,
        array $entityPropertiesColumns,
        SchemaDifference $diff
    ): void {
        foreach ($entityPropertiesColumns as $property => $columnName) {
            if (!isset($databaseColumns[$columnName])) {
                continue;
            }

            $dbColumnInfo = $databaseColumns[$columnName];

            // Get entity column info from cache
            $entityColumnInfo = $this->getCachedColumnInfo($entityClass, $property);

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
     * @param array<string, array{
     *     TABLE_NAME: string,
     *     COLUMN_NAME: string,
     *     COLUMN_TYPE: string,
     *     IS_NULLABLE: 'YES'|'NO',
     *     EXTRA: string,
     *     COLUMN_DEFAULT: string|null,
     *     COLUMN_KEY: string|null
     * }> $databaseColumns
     * @param array<string, string> $entityPropertiesColumns
     * @param SchemaDifference $diff
     * @return void
     */
    private function findColumnsToDrop(
        string $tableName,
        array $databaseColumns,
        array $entityPropertiesColumns,
        SchemaDifference $diff
    ): void {
        foreach ($databaseColumns as $columnName => $columnInfo) {
            if (!in_array($columnName, $entityPropertiesColumns, true)) {
                $diff->addColumnToDrop($tableName, $columnName);
            }
        }
    }

    /**
     * Compare foreign keys between entity mapping and database
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, string> $entityPropertiesColumns
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    private function compareForeignKeys(
        string $tableName,
        string $entityClass,
        array $entityPropertiesColumns,
        SchemaDifference $diff
    ): void {
        $databaseForeignKeys = $this->getTableForeignKeys($tableName);
        $entityForeignKeys = [];

        foreach ($entityPropertiesColumns as $property => $columnName) {
            $foreignKeyInfo = $this->getCachedForeignKeyInfo($entityClass, $property);

            if ($foreignKeyInfo === null) {
                continue;
            }

            $constraintName = $foreignKeyInfo['constraintName'];

            if ($constraintName === null
                || $foreignKeyInfo['referencedTable'] === null
                || $foreignKeyInfo['referencedColumn'] === null
            ) {
                throw new RuntimeException(
                    "Foreign key for $entityClass::$property is not fully defined in DbMapping"
                );
            }

            $entityForeignKeys[$constraintName] = [
                'COLUMN_NAME' => $columnName,
                'REFERENCED_TABLE_NAME' => $foreignKeyInfo['referencedTable'],
                'REFERENCED_COLUMN_NAME' => $foreignKeyInfo['referencedColumn'],
                'DELETE_RULE' => $foreignKeyInfo['deleteRule'] ?? FkRule::NO_ACTION,
                'UPDATE_RULE' => $foreignKeyInfo['updateRule'] ?? FkRule::NO_ACTION,
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

        $this->compareColumnType($entityColumnInfo, $dbColumnInfo, $differences);
        $this->compareNullable($entityColumnInfo, $dbColumnInfo, $differences);
        $this->compareDefaultValue($entityColumnInfo, $dbColumnInfo, $differences);
        $this->compareExtra($entityColumnInfo, $dbColumnInfo, $differences);

        return $differences;
    }

    /**
     * Compare column type between entity and database
     *
     * @param array{COLUMN_TYPE: string} $entityColumnInfo
     * @param array{COLUMN_TYPE: string} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * } $differences
     * @return void
     */
    private function compareColumnType(array $entityColumnInfo, array $dbColumnInfo, array &$differences): void
    {
        if (strtolower($entityColumnInfo['COLUMN_TYPE']) !== strtolower($dbColumnInfo['COLUMN_TYPE'])) {
            $differences['COLUMN_TYPE'] = [
                'from' => $dbColumnInfo['COLUMN_TYPE'],
                'to' => $entityColumnInfo['COLUMN_TYPE'],
            ];
        }
    }

    /**
     * Compare nullable constraint between entity and database
     *
     * @param array{IS_NULLABLE: 'YES'|'NO'} $entityColumnInfo
     * @param array{IS_NULLABLE: 'YES'|'NO'} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * } $differences
     * @return void
     */
    private function compareNullable(array $entityColumnInfo, array $dbColumnInfo, array &$differences): void
    {
        if ($entityColumnInfo['IS_NULLABLE'] !== $dbColumnInfo['IS_NULLABLE']) {
            $differences['IS_NULLABLE'] = [
                'from' => $dbColumnInfo['IS_NULLABLE'],
                'to' => $entityColumnInfo['IS_NULLABLE'],
            ];
        }
    }

    /**
     * Compare default value between entity and database
     *
     * @param array{COLUMN_DEFAULT: string|null} $entityColumnInfo
     * @param array{COLUMN_DEFAULT: string|null} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * } $differences
     * @return void
     */
    private function compareDefaultValue(array $entityColumnInfo, array $dbColumnInfo, array &$differences): void
    {
        $entityDefault = $this->normalizeDefaultValue($entityColumnInfo['COLUMN_DEFAULT']);
        $dbDefault = $this->normalizeDefaultValue($dbColumnInfo['COLUMN_DEFAULT']);

        if ($entityDefault !== $dbDefault) {
            $differences['COLUMN_DEFAULT'] = [
                'from' => $dbColumnInfo['COLUMN_DEFAULT'],
                'to' => $entityColumnInfo['COLUMN_DEFAULT'],
            ];
        }
    }

    /**
     * Compare extra attributes between entity and database
     *
     * @param array{EXTRA: string|null} $entityColumnInfo
     * @param array{EXTRA: string|null} $dbColumnInfo
     * @param array{
     *     COLUMN_TYPE?: array{from: string, to: string},
     *     IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *     EXTRA?: array{from: string|null, to: string|null}
     * } $differences
     * @return void
     */
    private function compareExtra(array $entityColumnInfo, array $dbColumnInfo, array &$differences): void
    {
        $entityExtra = $this->normalizeExtraValue($entityColumnInfo['EXTRA']);
        $dbExtra = $this->normalizeExtraValue($dbColumnInfo['EXTRA']);

        if ($entityExtra !== $dbExtra) {
            $differences['EXTRA'] = [
                'from' => $dbColumnInfo['EXTRA'] === '' ? null : $dbColumnInfo['EXTRA'],
                'to' => $entityColumnInfo['EXTRA'],
            ];
        }
    }

    /**
     * Normalize default value (convert empty string to null)
     *
     * @param string|null $value
     * @return string|null
     */
    private function normalizeDefaultValue(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * Normalize extra value (convert empty string to null)
     *
     * @param string|null $value
     * @return string|null
     */
    private function normalizeExtraValue(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

}
