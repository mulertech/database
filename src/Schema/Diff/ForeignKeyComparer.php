<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

use Exception;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Types\FkRule;
use ReflectionException;
use RuntimeException;

/**
 * Class ForeignKeyComparer
 * Compares foreign keys between entity mapping and database schema
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
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
                // Todo: il serait bien de ne pas avoir de propriété nulle
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
        foreach ($databaseForeignKeys as $constraintName => $value) {
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

        if (!$this->isValidForeignKey($foreignKey)) {
            return null;
        }

        // At this point, we know $foreignKey is an MtFk object
        assert($foreignKey instanceof MtFk);

        $referencedTable = $foreignKey->referencedTable;
        $constraintName = $this->resolveConstraintName($foreignKey, $entityClass, $property, $referencedTable);

        return [
            'foreignKey' => $foreignKey,
            'constraintName' => $constraintName,
            'referencedTable' => $referencedTable,
            'referencedColumn' => $foreignKey->referencedColumn,
            'deleteRule' => $this->extractFkRule($foreignKey, 'deleteRule'),
            'updateRule' => $this->extractFkRule($foreignKey, 'updateRule'),
        ];
    }

    /**
     * @param mixed $foreignKey
     * @return bool
     */
    private function isValidForeignKey(mixed $foreignKey): bool
    {
        return $foreignKey instanceof MtFk;
    }

    /**
     * @param MtFk $foreignKey
     * @param string $key
     * @return string|null
     */
    private function extractStringValue(MtFk $foreignKey, string $key): ?string
    {
        return match($key) {
            'constraintName' => $foreignKey->constraintName,
            'referencedTable' => $foreignKey->referencedTable,
            'referencedColumn' => $foreignKey->referencedColumn,
            default => null
        };
    }

    /**
     * @param MtFk $foreignKey
     * @param string $key
     * @return FkRule|null
     */
    private function extractFkRule(MtFk $foreignKey, string $key): ?FkRule
    {
        return match($key) {
            'deleteRule' => $foreignKey->deleteRule,
            'updateRule' => $foreignKey->updateRule,
            default => null
        };
    }

    /**
     * Resolve constraint name with generation if needed
     *
     * @param MtFk $foreignKey
     * @param class-string $entityClass
     * @param string $property
     * @param string|null $referencedTable
     * @return string|null
     * @throws ReflectionException
     */
    private function resolveConstraintName(MtFk $foreignKey, string $entityClass, string $property, ?string $referencedTable): ?string
    {
        $constraintName = $this->extractStringValue($foreignKey, 'constraintName');

        return $constraintName ?? $this->generateConstraintName($entityClass, $property, $referencedTable);
    }

    /**
     * Generate constraint name from entity, property and referenced table
     *
     * @param class-string $entityClass
     * @param string $property
     * @param string|null $referencedTable
     * @return string|null
     * @throws ReflectionException
     * @throws Exception
     */
    private function generateConstraintName(string $entityClass, string $property, ?string $referencedTable): ?string
    {
        if (empty($referencedTable)) {
            return null;
        }

        $tableName = $this->metadataCache->getTableName($entityClass);
        $metadata = $this->metadataCache->getEntityMetadata($entityClass);
        $columnName = $metadata->getColumnName($property);

        return sprintf(
            "fk_%s_%s_%s",
            strtolower($tableName ?: ''),
            strtolower($columnName ?: ''),
            strtolower($referencedTable)
        );
    }
}
