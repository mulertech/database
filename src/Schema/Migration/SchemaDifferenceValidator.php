<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Schema\Diff\SchemaDifference;
use RuntimeException;

/**
 * Class SchemaDifferenceValidator
 *
 * Validates schema differences to ensure all prerequisites are met
 *
 * @package MulerTech\Database\Schema\Migration
 */
class SchemaDifferenceValidator
{
    /**
     * Validate schema differences to ensure all prerequisites are met
     *
     * @param SchemaDifference $diff
     * @return void
     * @throws RuntimeException If validation fails
     */
    public function validate(SchemaDifference $diff): void
    {
        $tablesToCreate = array_keys($diff->getTablesToCreate());
        $columnsToCreate = $this->collectColumnsToCreate($diff);

        $this->validateForeignKeyReferences($diff, $tablesToCreate, $columnsToCreate);
        $this->validateTableColumnConsistency($diff);
    }

    /**
     * Collect columns that will be created during migration
     *
     * @param SchemaDifference $diff
     * @return array<string, string[]> Array of table names to column names
     */
    private function collectColumnsToCreate(SchemaDifference $diff): array
    {
        $columnsToCreate = [];

        foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
            if (!isset($columnsToCreate[$tableName])) {
                $columnsToCreate[$tableName] = [];
            }
            foreach ($columns as $columnName => $definition) {
                $columnsToCreate[$tableName][] = $columnName;
            }
        }

        return $columnsToCreate;
    }

    /**
     * Validate foreign key references to ensure referenced tables and columns exist
     *
     * @param SchemaDifference $diff
     * @param string[] $tablesToCreate Array of table names that will be created
     * @param array<string, string[]> $columnsToCreate Array of table names to column names that will be created
     * @return void
     */
    private function validateForeignKeyReferences(SchemaDifference $diff, array $tablesToCreate, array $columnsToCreate): void
    {
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            // Skip if the table itself is being created
            if (in_array($tableName, $tablesToCreate, true)) {
                continue;
            }

            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $this->validateSingleForeignKey($constraintName, $foreignKeyInfo);
            }
        }
    }

    /**
     * Validate a single foreign key constraint
     *
     * @param string $constraintName
     * @param array<string, mixed> $foreignKeyInfo Foreign key information array
     * @return void
     * @throws RuntimeException If foreign key definition is incomplete
     */
    private function validateSingleForeignKey(string $constraintName, array $foreignKeyInfo): void
    {
        $columnName = $foreignKeyInfo['COLUMN_NAME'] ?? null;
        $referencedTable = $foreignKeyInfo['REFERENCED_TABLE_NAME'] ?? null;
        $referencedColumn = $foreignKeyInfo['REFERENCED_COLUMN_NAME'] ?? null;

        if (!$columnName || !$referencedTable || !$referencedColumn) {
            throw new RuntimeException("Foreign key '$constraintName' has incomplete definition.");
        }
    }

    private function validateTableColumnConsistency(SchemaDifference $diff): void
    {
        $columnsToAdd = $diff->getColumnsToAdd();

        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            if (!isset($columnsToAdd[$tableName])) {
                throw new RuntimeException("Table '$tableName' has no columns defined.");
            }
        }
    }
}
