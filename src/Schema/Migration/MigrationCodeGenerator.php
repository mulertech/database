<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\Types\FkRule;
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
     * @param array<string> $code
     */
    private function addDropForeignKeysCode(SchemaDifference $diff, array &$code): void
    {
        $this->processSimpleNestedItems(
            $diff->getForeignKeysToDrop(),
            fn ($tableName, $constraintName) => $this->statementGenerator->generateDropForeignKeyStatement(
                $tableName,
                $constraintName
            ),
            $code
        );
    }

    /**
     * @param array<string> $code
     */
    private function addDropColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $this->processSimpleNestedItems(
            $diff->getColumnsToDrop(),
            fn ($tableName, $columnName) => $this->statementGenerator->generateDropColumnStatement(
                $tableName,
                $columnName
            ),
            $code
        );
    }

    /**
     * @param array<string> $code
     * @throws ReflectionException
     */
    private function addCreateTablesCode(SchemaDifference $diff, array &$code): void
    {
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            $code[] = $this->statementGenerator->generateCreateTableStatement($tableName, $this->dbMapping);
        }
    }

    /**
     * @param array<string> $code
     */
    private function addNewColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $columnsToAdd = $diff->getColumnsToAdd();
        $tablesToCreate = array_keys($diff->getTablesToCreate());

        $this->processFilteredColumns(
            $columnsToAdd,
            $tablesToCreate,
            $code,
            fn (
                $tableName,
                $columnName,
                $columnDefinition
            ) => is_array($columnDefinition)
                ? $this->generateAddColumnStatement($tableName, $columnName, $columnDefinition)
                : null
        );
    }

    /**
     * @param array<string> $code
     */
    private function addModifyColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $this->processNestedItems(
            $diff->getColumnsToModify(),
            function ($tableName, $columnName, $differences) {
                if (!is_array($differences)) {
                    return null;
                }
                /** @phpstan-var array{
                 *     COLUMN_TYPE?: array{from: string, to: string},
                 *     IS_NULLABLE?: array{from: 'NO'|'YES', to: 'NO'|'YES'},
                 *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
                 *     EXTRA?: array{from: string|null, to: string|null}
                 *     } $differences
                 */
                return $this->statementGenerator->generateModifyColumnStatement($tableName, $columnName, $differences);
            },
            $code
        );
    }

    /**
     * @param array<string> $code
     */
    private function addForeignKeysCode(SchemaDifference $diff, array &$code): void
    {
        $this->processNestedItems(
            $diff->getForeignKeysToAdd(),
            function ($tableName, $constraintName, $foreignKeyInfo) {
                if (!is_array($foreignKeyInfo)) {
                    return null;
                }
                /** @phpstan-var array{
                 *     COLUMN_NAME: string,
                 *     REFERENCED_TABLE_NAME: string|null,
                 *     REFERENCED_COLUMN_NAME: string|null,
                 *     DELETE_RULE: FkRule|null,
                 *     UPDATE_RULE: FkRule|null
                 * } $foreignKeyInfo
                 */
                return $this->statementGenerator->generateAddForeignKeyStatement(
                    $tableName,
                    $constraintName,
                    $foreignKeyInfo
                );
            },
            $code
        );
    }

    /**
     * @param array<string> $code
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
     * @param array<string> $code
     */
    private function addDropAddedForeignKeysCode(SchemaDifference $diff, array &$code): void
    {
        $this->processNestedItems(
            $diff->getForeignKeysToAdd(),
            fn ($tableName, $constraintName) => $this->statementGenerator->generateDropForeignKeyStatement(
                $tableName,
                $constraintName
            ),
            $code
        );
    }

    /**
     * @param array<string> $code
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
     * @param array<string> $code
     */
    private function addDropAddedColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $columnsToAdd = $diff->getColumnsToAdd();
        $tablesToCreate = array_keys($diff->getTablesToCreate());

        $this->processFilteredColumns(
            $columnsToAdd,
            $tablesToCreate,
            $code,
            fn ($tableName, $columnName, $definition) => $this->statementGenerator->generateDropColumnStatement(
                $tableName,
                $columnName
            )
        );
    }

    /**
     * @param array<string> $code
     */
    private function addRestoreModifiedColumnsCode(SchemaDifference $diff, array &$code): void
    {
        $this->processNestedItems(
            $diff->getColumnsToModify(),
            function ($tableName, $columnName, $differences) {
                if (!is_array($differences) || !$this->shouldRestoreColumn($differences)) {
                    return null;
                }
                /** @phpstan-var array{
                 *     COLUMN_TYPE?: array{from: string, to: string},
                 *     IS_NULLABLE?: array{from: 'NO'|'YES', to: 'NO'|'YES'},
                 *     COLUMN_DEFAULT?: array{from: string|null, to: string|null},
                 *     EXTRA?: array{from: string|null, to: string|null}
                 *     } $differences
                 */
                return $this->statementGenerator->generateRestoreColumnStatement($tableName, $columnName, $differences);
            },
            $code
        );
    }

    /**
     * Process nested array items with a callback
     * @param array<mixed, mixed> $nestedArray
     * @param callable(string, string, mixed): ?string $callback
     * @param array<string> $code
     */
    private function processNestedItems(array $nestedArray, callable $callback, array &$code): void
    {
        foreach ($nestedArray as $tableName => $items) {
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $itemName => $itemData) {
                $statement = $callback($tableName, $itemName, $itemData);
                if ($statement !== null) {
                    $code[] = $statement;
                }
            }
        }
    }

    /**
     * Check if column should be restored
     * @param array<mixed, mixed> $differences
     */
    private function shouldRestoreColumn(array $differences): bool
    {
        return (is_array($differences['COLUMN_TYPE'] ?? null) && isset($differences['COLUMN_TYPE']['from']))
            || (is_array($differences['IS_NULLABLE'] ?? null) && isset($differences['IS_NULLABLE']['from']))
            || (is_array($differences['COLUMN_DEFAULT'] ?? null) && isset($differences['COLUMN_DEFAULT']['from']));
    }

    /**
     * Process columns with table filtering
     * @param array<mixed, mixed> $columns
     * @param array<int, string> $tablesToSkip
     * @param array<string> $code
     * @param callable(string, string, mixed): ?string $callback
     */
    private function processFilteredColumns(array $columns, array $tablesToSkip, array &$code, callable $callback): void
    {
        foreach ($columns as $tableName => $columnData) {
            if (!is_array($columnData) || in_array($tableName, $tablesToSkip, true)) {
                continue;
            }

            foreach ($columnData as $columnName => $definition) {
                $statement = $callback($tableName, $columnName, $definition);
                if ($statement !== null) {
                    $code[] = $statement;
                }
            }
        }
    }

    /**
     * Generate add column statement
     * @param array<int|string, mixed> $columnDefinition
     */
    private function generateAddColumnStatement(string $tableName, string $columnName, array $columnDefinition): string
    {
        $columnType = $columnDefinition['COLUMN_TYPE'];
        $columnExtra = is_string($columnDefinition['EXTRA'] ?? null)
            ? $columnDefinition['EXTRA']
            : null;
        $columnDefault = $columnDefinition['COLUMN_DEFAULT'] ?? null;

        return $this->statementGenerator->generateAlterTableStatement(
            $tableName,
            is_string($columnType) ? $columnType : null,
            $columnName,
            $columnDefinition['IS_NULLABLE'] === 'YES',
            is_string($columnDefault) ? $columnDefault : null,
            $columnExtra,
            $this->typeConverter
        );
    }

    /**
     * Process simple nested arrays (array<string, array<string>>)
     * @param array<string, array<int, string>> $nestedArray
     * @param callable(string, string): ?string $callback
     * @param array<string> $code
     */
    private function processSimpleNestedItems(array $nestedArray, callable $callback, array &$code): void
    {
        foreach ($nestedArray as $tableName => $items) {
            foreach ($items as $itemName) {
                $statement = $callback($tableName, $itemName);
                if ($statement !== null) {
                    $code[] = $statement;
                }
            }
        }
    }
}
