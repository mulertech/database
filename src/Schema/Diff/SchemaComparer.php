<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

use Exception;
use MulerTech\Database\Core\Cache\MetadataCache;
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
     * @var ColumnComparer $columnComparer
     */
    private readonly ColumnComparer $columnComparer;
    /**
     * @var ForeignKeyComparer $foreignKeyComparer
     */
    private readonly ForeignKeyComparer $foreignKeyComparer;

    /**
     * @param InformationSchema $informationSchema
     * @param MetadataCache $metadataCache
     * @param string $databaseName
     */
    public function __construct(
        private readonly InformationSchema $informationSchema,
        private readonly MetadataCache $metadataCache,
        private readonly string $databaseName
    ) {
        $this->columnComparer = new ColumnComparer();
        $this->foreignKeyComparer = new ForeignKeyComparer($metadataCache);
    }

    /**
     * Get cached properties columns for entity class
     * @param class-string $entityClass
     * @return array<string, string>
     * @throws ReflectionException|Exception
     */
    private function getCachedPropertiesColumns(string $entityClass): array
    {
        if (!isset($this->propertiesColumnsCache[$entityClass])) {
            $this->propertiesColumnsCache[$entityClass] = $this->metadataCache->getPropertiesColumns($entityClass);
        }
        return $this->propertiesColumnsCache[$entityClass];
    }

    /**
     * Check if a table should be ignored during schema comparison
     *
     * @param string $tableName
     * @return bool
     */
    private function shouldIgnoreTable(string $tableName): bool
    {
        return $tableName === 'migration_history';
    }

    /**
     * Get cached table name for entity class
     * @param class-string $entityClass
     * @return string
     * @throws ReflectionException|Exception
     */
    private function getCachedTableName(string $entityClass): string
    {
        if (!isset($this->tableNameCache[$entityClass])) {
            $this->tableNameCache[$entityClass] = $this->metadataCache->getTableName($entityClass);
        }
        return $this->tableNameCache[$entityClass];
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
     * @throws Exception
     */
    public function compare(): SchemaDifference
    {
        $diff = new SchemaDifference();

        // Get all entity tables
        $entityTables = [];
        foreach ($this->metadataCache->getLoadedEntities() as $entityClass) {
            $tableName = $this->getCachedTableName($entityClass);
            if ($tableName && !$this->shouldIgnoreTable($tableName)) {
                $entityTables[$tableName] = $entityClass;
            }
        }

        // Find tables to create (in entity mappings but not in database)
        foreach ($entityTables as $tableName => $entityClass) {
            if (!$this->getTableInfo($tableName)) {
                // Validate that the table has columns before adding it
                $columns = $this->metadataCache->getPropertiesColumns($entityClass);
                if (empty($columns)) {
                    throw new RuntimeException("Table '$tableName' has no columns defined.");
                }
                $diff->addTableToCreate($tableName, $entityClass);
            }
        }

        // Find tables to drop (in database but not in entity mappings)
        foreach ($this->informationSchema->getTables($this->databaseName) as $table) {
            $tableName = $table['TABLE_NAME'];

            // Skip ignored tables
            if ($this->shouldIgnoreTable($tableName)) {
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
                $databaseColumns = $this->getTableColumns($tableName);
                $databaseForeignKeys = $this->getTableForeignKeys($tableName);

                $this->columnComparer->compareColumns(
                    $tableName,
                    $entityClass,
                    $entityPropertiesColumns,
                    $databaseColumns,
                    $diff
                );
                $this->foreignKeyComparer->compareForeignKeys(
                    $tableName,
                    $entityClass,
                    $entityPropertiesColumns,
                    $databaseForeignKeys,
                    $diff
                );
            }
        }

        return $diff;
    }
}
