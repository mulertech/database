<?php

declare(strict_types=1);

namespace MulerTech\Database\Migration\Schema;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Relational\Sql\InformationSchema;
use ReflectionException;
use RuntimeException;

/**
 * Compare database schema with entity mappings
 *
 * @package MulerTech\Database\Migration\Schema
 * @author Sébastien Muler
 */
class SchemaComparer
{
    /**
     * @var array<int, array<string, mixed>> Database tables
     */
    private array $databaseTables = [];

    /**
     * @var array<int, array<string, mixed>> Database columns
     */
    private array $databaseColumns = [];

    /**
     * @var array<int, array<string, mixed>> Database foreign keys
     */
    private array $databaseForeignKeys = [];

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
        $this->loadDatabaseSchema();
    }

    /**
     * Load database schema information
     *
     * @return void
     */
    private function loadDatabaseSchema(): void
    {
        $this->databaseTables = $this->informationSchema->getTables($this->databaseName);
        $this->databaseColumns = $this->informationSchema->getColumns($this->databaseName);
        $this->databaseForeignKeys = $this->informationSchema->getForeignKeys($this->databaseName);
    }

    /**
     * Get database table info by table name
     *
     * @param string $tableName
     * @return array<string, mixed>|null
     */
    private function getTableInfo(string $tableName): ?array
    {
        return array_find($this->databaseTables, fn ($table) => $table['TABLE_NAME'] === $tableName);
    }

    /**
     * Get columns for a specific table
     *
     * @param string $tableName
     * @return array<string, array<string, mixed>>
     */
    private function getTableColumns(string $tableName): array
    {
        $columns = [];
        foreach ($this->databaseColumns as $column) {
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
     * @return array<string, array<string, mixed>>
     */
    private function getTableForeignKeys(string $tableName): array
    {
        $foreignKeys = [];

        foreach ($this->databaseForeignKeys as $foreignKey) {
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
        foreach ($this->databaseTables as $table) {
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
        $entityColumns = [];

        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $columnType = $this->dbMapping->getColumnTypeDefinition($entityClass, $property);
            $isNullable = $this->dbMapping->isNullable($entityClass, $property);
            $columnDefault = $this->dbMapping->getColumnDefault($entityClass, $property) ?? null;
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

        // Find columns to add (in entity mapping but not in database)
        foreach ($entityColumns as $columnName => $columnInfo) {
            if (!isset($databaseColumns[$columnName])) {
                $diff->addColumnToAdd($tableName, $columnName, $columnInfo);
            }
        }

        // Find columns to modify (in both but with different definitions)
        foreach ($entityColumns as $columnName => $columnInfo) {
            if (!isset($databaseColumns[$columnName])) {
                continue;
            }

            $dbColumnInfo = $databaseColumns[$columnName];

            // Compare column definitions
            $columnDifferences = [];

            if ($columnInfo['COLUMN_TYPE'] !== $dbColumnInfo['COLUMN_TYPE']) {
                $columnDifferences['COLUMN_TYPE'] = [
                    'from' => $dbColumnInfo['COLUMN_TYPE'],
                    'to' => $columnInfo['COLUMN_TYPE'],
                ];
            }

            if ($columnInfo['IS_NULLABLE'] !== $dbColumnInfo['IS_NULLABLE']) {
                $columnDifferences['IS_NULLABLE'] = [
                    'from' => $dbColumnInfo['IS_NULLABLE'],
                    'to' => $columnInfo['IS_NULLABLE'],
                ];
            }

            // Compare default values (need special handling for NULL)
            $dbDefault = $dbColumnInfo['COLUMN_DEFAULT'];
            $mappingDefault = $columnInfo['COLUMN_DEFAULT'];

            if (($dbDefault === null && $mappingDefault !== null) ||
                ($dbDefault !== null && $mappingDefault === null) ||
                ($dbDefault !== null && $mappingDefault !== null && $dbDefault !== $mappingDefault)) {
                $columnDifferences['COLUMN_DEFAULT'] = [
                    'from' => $dbDefault,
                    'to' => $mappingDefault,
                ];
            }

            if (!empty($columnDifferences)) {
                $diff->addColumnToModify($tableName, $columnName, $columnDifferences);
            }
        }

        // Find columns to drop (in database but not in entity mapping)
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
     */
    private function compareForeignKeys(string $tableName, string $entityClass, SchemaDifference $diff): void
    {
        $databaseForeignKeys = $this->getTableForeignKeys($tableName);
        $entityForeignKeys = [];

        foreach ($this->dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $foreignKey = $this->dbMapping->getForeignKey($entityClass, $property);

            if ($foreignKey !== null) {
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
