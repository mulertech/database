<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\Types\FkRule;
use ReflectionException;
use RuntimeException;

readonly class ForeignKeyComparer
{
    public function __construct(
        private MetadataCache $metadataCache
    ) {
    }

    /**
     * Compare foreign keys between entity mapping and database
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, string> $entityPropertiesColumns
     * @param array<string, array{
     *       TABLE_NAME: string,
     *       CONSTRAINT_NAME: string,
     *       COLUMN_NAME: string,
     *       REFERENCED_TABLE_SCHEMA: string|null,
     *       REFERENCED_TABLE_NAME: string|null,
     *       REFERENCED_COLUMN_NAME: string|null,
     *       DELETE_RULE: string|null,
     *       UPDATE_RULE: string|null
     *   }> $databaseForeignKeys
     * @param SchemaDifference $diff
     * @return void
     * @throws ReflectionException
     */
    public function compareForeignKeys(
        string $tableName,
        string $entityClass,
        array $entityPropertiesColumns,
        array $databaseForeignKeys,
        SchemaDifference $diff
    ): void {
        $entityForeignKeys = $this->getEntityForeignKeys($entityClass, $entityPropertiesColumns);

        $this->findForeignKeysToAdd($tableName, $entityForeignKeys, $databaseForeignKeys, $diff);
        $this->findForeignKeysToDrop($tableName, $entityForeignKeys, $databaseForeignKeys, $diff);
    }

    /**
     * Get entity foreign keys configuration
     *
     * @param class-string $entityClass
     * @param array<string, string> $entityPropertiesColumns
     * @return array<string, array{
     *     COLUMN_NAME: string,
     *     REFERENCED_TABLE_NAME: string,
     *     REFERENCED_COLUMN_NAME: string,
     *     DELETE_RULE: FkRule,
     *     UPDATE_RULE: FkRule
     * }>
     * @throws ReflectionException
     */
    private function getEntityForeignKeys(string $entityClass, array $entityPropertiesColumns): array
    {
        $entityForeignKeys = [];

        foreach ($entityPropertiesColumns as $property => $columnName) {
            $foreignKeyInfo = $this->getForeignKeyInfo($entityClass, $property);

            if ($foreignKeyInfo === null) {
                continue;
            }

            $constraintName = $foreignKeyInfo['constraintName'];

            if ($constraintName === null
                || $foreignKeyInfo['referencedTable'] === null
                || $foreignKeyInfo['referencedColumn'] === null
            ) {
                throw new RuntimeException(
                    "Foreign key for $entityClass::$property is not fully defined in entity metadata"
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

        return $entityForeignKeys;
    }

    /**
     * Find foreign keys to add (in entity mapping but not in database)
     * @param string $tableName
     * @param array<string, array{
     *     COLUMN_NAME: string,
     *     REFERENCED_TABLE_NAME: string,
     *     REFERENCED_COLUMN_NAME: string,
     *     DELETE_RULE: FkRule,
     *     UPDATE_RULE: FkRule
     * }> $entityForeignKeys
     * @param array<string, array{
     *       TABLE_NAME: string,
     *       CONSTRAINT_NAME: string,
     *       COLUMN_NAME: string,
     *       REFERENCED_TABLE_SCHEMA: string|null,
     *       REFERENCED_TABLE_NAME: string|null,
     *       REFERENCED_COLUMN_NAME: string|null,
     *       DELETE_RULE: string|null,
     *       UPDATE_RULE: string|null
     *   }> $databaseForeignKeys
     * @param SchemaDifference $diff
     * @return void
     */
    private function findForeignKeysToAdd(
        string $tableName,
        array $entityForeignKeys,
        array $databaseForeignKeys,
        SchemaDifference $diff
    ): void {
        foreach ($entityForeignKeys as $constraintName => $foreignKeyInfo) {
            if (!isset($databaseForeignKeys[$constraintName])) {
                $diff->addForeignKeyToAdd($tableName, $constraintName, $foreignKeyInfo);
            }
        }
    }

    /**
     * Find foreign keys to drop (in database but not in entity mapping)
     * @param string $tableName
     * @param array<string, array{
     *     COLUMN_NAME: string,
     *     REFERENCED_TABLE_NAME: string,
     *     REFERENCED_COLUMN_NAME: string,
     *     DELETE_RULE: FkRule,
     *     UPDATE_RULE: FkRule
     * }> $entityForeignKeys
     * @param array<string, array{
     *       TABLE_NAME: string,
     *       CONSTRAINT_NAME: string,
     *       COLUMN_NAME: string,
     *       REFERENCED_TABLE_SCHEMA: string|null,
     *       REFERENCED_TABLE_NAME: string|null,
     *       REFERENCED_COLUMN_NAME: string|null,
     *       DELETE_RULE: string|null,
     *       UPDATE_RULE: string|null
     *   }> $databaseForeignKeys
     * @param SchemaDifference $diff
     * @return void
     */
    private function findForeignKeysToDrop(
        string $tableName,
        array $entityForeignKeys,
        array $databaseForeignKeys,
        SchemaDifference $diff
    ): void {
        foreach ($databaseForeignKeys as $constraintName => $_) {
            if (!isset($entityForeignKeys[$constraintName])) {
                $diff->addForeignKeyToDrop($tableName, $constraintName);
            }
        }
    }

    /**
     * Get foreign key info for entity property
     *
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
    private function getForeignKeyInfo(string $entityClass, string $property): ?array
    {
        $metadata = $this->metadataCache->getEntityMetadata($entityClass);
        $foreignKey = $metadata->getForeignKey($property);

        if ($foreignKey === null) {
            return null;
        }

        // Validate that foreignKey is an array
        if (!is_array($foreignKey)) {
            return null;
        }

        // Generate constraint name if not provided
        $constraintName = is_string($foreignKey['constraintName'] ?? null) ? $foreignKey['constraintName'] : null;
        $referencedTable = is_string($foreignKey['referencedTable'] ?? null) ? $foreignKey['referencedTable'] : null;
        if ($constraintName === null && !empty($referencedTable)) {
            $tableName = $this->metadataCache->getTableName($entityClass);
            $columnName = $metadata->getColumnName($property);
            $constraintName = sprintf(
                "fk_%s_%s_%s",
                strtolower($tableName ?: ''),
                strtolower($columnName ?: ''),
                strtolower($referencedTable)
            );
        }

        $referencedColumn = is_string($foreignKey['referencedColumn'] ?? null) ? $foreignKey['referencedColumn'] : null;

        $deleteRule = null;
        if (isset($foreignKey['deleteRule']) && $foreignKey['deleteRule'] instanceof FkRule) {
            $deleteRule = $foreignKey['deleteRule'];
        }

        $updateRule = null;
        if (isset($foreignKey['updateRule']) && $foreignKey['updateRule'] instanceof FkRule) {
            $updateRule = $foreignKey['updateRule'];
        }

        return [
            'foreignKey' => $foreignKey,
            'constraintName' => $constraintName,
            'referencedTable' => $referencedTable,
            'referencedColumn' => $referencedColumn,
            'deleteRule' => $deleteRule,
            'updateRule' => $updateRule,
        ];
    }
}
