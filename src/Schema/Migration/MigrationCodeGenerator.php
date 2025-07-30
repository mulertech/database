<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use ReflectionException;

/**
 * Class MigrationCodeGenerator
 *
 * Generates migration code for up() and down() methods
 *
 * @package MulerTech\Database\Schema\Migration
 */
readonly class MigrationCodeGenerator
{
    /**
     * @var SqlTypeConverter $typeConverter
     */
    private SqlTypeConverter $typeConverter;
    /**
     * @var MigrationStatementGenerator $statementGenerator
     */
    private MigrationStatementGenerator $statementGenerator;

    public function __construct(private DbMappingInterface $dbMapping)
    {
        $this->typeConverter = new SqlTypeConverter();
        $this->statementGenerator = new MigrationStatementGenerator();
    }

    /**
     * Generate code for the up() method
     *
     * @param SchemaDifference $diff
     * @return string
     * @throws ReflectionException
     */
    public function generateUpCode(SchemaDifference $diff): string
    {
        $code = [];

        $this->addDropForeignKeysCode($diff, $code);
        $this->addDropColumnsCode($diff, $code);
        $this->addCreateTablesCode($diff, $code);
        $this->addNewColumnsCode($diff, $code);
        $this->addModifyColumnsCode($diff, $code);
        $this->addForeignKeysCode($diff, $code);
        $this->addDropTablesCode($diff, $code);

        return implode("\n\n", array_map(static fn ($line) => "        $line", $code));
    }

    /**
     * Generate code for the down() method
     *
     * @param SchemaDifference $diff
     * @return string
     */
    public function generateDownCode(SchemaDifference $diff): string
    {
        $code = [];

        $this->addDropAddedForeignKeysCode($diff, $code);
        $this->addDropCreatedTablesCode($diff, $code);
        $this->addDropAddedColumnsCode($diff, $code);
        $this->addRestoreModifiedColumnsCode($diff, $code);

        return empty($code)
            ? '        // No rollback operations defined'
            : implode("\n\n", array_map(static fn ($line) => "        $line", $code));
    }

    /**
     * @param array<string> &$code
     */
    private function addDropForeignKeysCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getForeignKeysToDrop() as $tableName => $constraintNames) {
            foreach ($constraintNames as $constraintName) {
                $code[] = $this->statementGenerator->generateDropForeignKeyStatement($tableName, $constraintName);
            }
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addDropColumnsCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getColumnsToDrop() as $tableName => $columnNames) {
            foreach ($columnNames as $columnName) {
                $code[] = $this->statementGenerator->generateDropColumnStatement($tableName, $columnName);
            }
        }
    }

    /**
     * @param array<string> &$code
     * @throws ReflectionException
     */
    private function addCreateTablesCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = $this->statementGenerator->generateCreateTableStatement($tableName, $this->dbMapping);
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addNewColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $columnsToAdd = $diff->getColumnsToAdd();
        $tablesToCreate = array_keys($diff->getTablesToCreate());

        foreach ($columnsToAdd as $tableName => $columns) {
            if (in_array($tableName, $tablesToCreate, true)) {
                continue;
            }

            foreach ($columns as $columnName => $columnDefinition) {
                $columnType = $columnDefinition['COLUMN_TYPE'];
                $columnExtra = is_string($columnDefinition['EXTRA'] ?? null)
                    ? $columnDefinition['EXTRA']
                    : null;

                $code[] = $this->statementGenerator->generateAlterTableStatement(
                    $tableName,
                    $columnType,
                    $columnName,
                    $columnDefinition['IS_NULLABLE'] === 'YES',
                    $columnDefinition['COLUMN_DEFAULT'] ?? null,
                    $columnExtra,
                    $this->typeConverter
                );
            }
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addModifyColumnsCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getColumnsToModify() as $tableName => $columns) {
            foreach ($columns as $columnName => $differences) {
                $code[] = $this->statementGenerator->generateModifyColumnStatement($tableName, $columnName, $differences);
            }
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addForeignKeysCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $code[] = $this->statementGenerator->generateAddForeignKeyStatement($tableName, $constraintName, $foreignKeyInfo);
            }
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addDropTablesCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getTablesToDrop() as $tableName) {
            $code[] = '$schema = new SchemaBuilder();';
            $code[] = '        $sql = $schema->dropTable("' . $tableName . '");';
            $code[] = '        $this->entityManager->getPdm()->exec($sql);';
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addDropAddedForeignKeysCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getForeignKeysToAdd() as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $constraintName => $foreignKeyInfo) {
                $code[] = $this->statementGenerator->generateDropForeignKeyStatement($tableName, $constraintName);
            }
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addDropCreatedTablesCode(SchemaDifference $diff, array &$code): void
    {
        $columnsToDrop = $diff->getColumnsToAdd();
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = '$schema = new SchemaBuilder();';
            $code[] = '        $sql = $schema->dropTable("' . $tableName . '");';
            $code[] = '        $this->entityManager->getPdm()->exec($sql);';
            unset($columnsToDrop[$tableName]);
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addDropAddedColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $columnsToAdd = $diff->getColumnsToAdd();
        $tablesToCreate = array_keys($diff->getTablesToCreate());

        foreach ($columnsToAdd as $tableName => $columns) {
            if (in_array($tableName, $tablesToCreate, true)) {
                continue;
            }

            foreach ($columns as $columnName => $columnDefinition) {
                $code[] = $this->statementGenerator->generateDropColumnStatement($tableName, $columnName);
            }
        }
    }

    /**
     * @param array<string> &$code
     */
    private function addRestoreModifiedColumnsCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getColumnsToModify() as $tableName => $columns) {
            foreach ($columns as $columnName => $differences) {
                $hasColumnTypeFrom = isset($differences['COLUMN_TYPE']['from']);
                $hasNullableFrom = isset($differences['IS_NULLABLE']['from']);
                $hasDefaultFrom = isset($differences['COLUMN_DEFAULT']['from']);

                if ($hasColumnTypeFrom || $hasNullableFrom || $hasDefaultFrom) {
                    $code[] = $this->statementGenerator->generateRestoreColumnStatement($tableName, $columnName, $differences);
                }
            }
        }
    }
}
