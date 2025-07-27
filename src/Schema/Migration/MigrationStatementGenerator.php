<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\Types\FkRule;
use ReflectionException;
use RuntimeException;

/**
 * Class MigrationStatementGenerator
 *
 * Generates individual migration statements
 *
 * @package MulerTech\Database\Schema\Migration
 */
class MigrationStatementGenerator
{
    /**
     * Generate code to create a table with all its columns
     *
     * @param string $tableName
     * @param DbMappingInterface $dbMapping
     * @return string
     * @throws ReflectionException
     */
    public function generateCreateTableStatement(string $tableName, DbMappingInterface $dbMapping): string
    {
        $entityClass = $this->findEntityClassForTable($tableName, $dbMapping);

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->createTable("' . $tableName . '");';

        $this->addColumnDefinitions($code, $entityClass, $dbMapping);
        $this->addTableConfiguration($code);
        $this->addExecutionCode($code);

        return implode("\n", $code);
    }

    /**
     * @param string $tableName
     * @param string|null $columnType
     * @param string $columnName
     * @param bool $isNullable
     * @param string|null $columnDefault
     * @param string|null $columnExtra
     * @param SqlTypeConverter $typeConverter
     * @return string
     */
    public function generateAlterTableStatement(
        string $tableName,
        ?string $columnType,
        string $columnName,
        bool $isNullable,
        ?string $columnDefault,
        ?string $columnExtra,
        SqlTypeConverter $typeConverter
    ): string {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        $columnDefinitionCode = $this->generateColumnDefinitionFromType(
            $columnType,
            $columnName,
            $isNullable,
            $columnDefault,
            $columnExtra,
            $typeConverter
        );

        $code[] = '        ' . $columnDefinitionCode;
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to modify a column
     * @param string $tableName
     * @param string $columnName
     * @param array{
     *        COLUMN_TYPE?: array{from: string, to: string},
     *        IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *        COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *        EXTRA?: array{from: string|null, to: string|null}
     *        } $differences
     * * @return string
     */
    public function generateModifyColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        // Get the new values from differences
        $newColumnType = $differences['COLUMN_TYPE']['to'] ?? null;
        $newIsNullable = ($differences['IS_NULLABLE']['to'] ?? 'NO') === 'YES';
        $newColumnDefault = $differences['COLUMN_DEFAULT']['to'] ?? null;
        $newColumnExtra = $differences['EXTRA']['to'] ?? null;

        $columnDefinitionCode = $this->generateColumnDefinitionFromType(
            $newColumnType,
            $columnName,
            $newIsNullable,
            $newColumnDefault,
            $newColumnExtra,
            new SqlTypeConverter()
        );

        $code[] = '        $tableDefinition->modifyColumn(' . $columnDefinitionCode . ');';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate code to restore a column to its previous state
     *
     * @param string $tableName
     * @param string $columnName
     * @param array{
     *        COLUMN_TYPE?: array{from: string, to: string},
     *        IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *        COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *        EXTRA?: array{from: string|null, to: string|null}
     *        } $differences
     * * @return string
     */
    public function generateRestoreColumnStatement(string $tableName, string $columnName, array $differences): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';

        // Get the original values from differences
        $originalColumnType = $differences['COLUMN_TYPE']['from'] ?? null;
        $originalIsNullable = ($differences['IS_NULLABLE']['from'] ?? 'NO') === 'YES';
        $originalColumnDefault = $differences['COLUMN_DEFAULT']['from'] ?? null;
        $originalColumnExtra = $differences['EXTRA']['from'] ?? null;

        $columnDefinitionCode = $this->generateColumnDefinitionFromType(
            $originalColumnType,
            $columnName,
            $originalIsNullable,
            $originalColumnDefault,
            $originalColumnExtra,
            new SqlTypeConverter()
        );

        $code[] = '        $tableDefinition->modifyColumn(' . $columnDefinitionCode . ');';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate drop table statement
     * @param string $tableName
     * @return string
     */
    public function generateDropTableStatement(string $tableName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $sql = $schema->dropTable("' . $tableName . '");';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate drop column statement
     * @param string $tableName
     * @param string $columnName
     * @return string
     */
    public function generateDropColumnStatement(string $tableName, string $columnName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';
        $code[] = '        $tableDefinition->dropColumn("' . $columnName . '");';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate add foreign key statement
     * @param string $tableName
     * @param string $constraintName
     * @param array{
     *          COLUMN_NAME: string,
     *          REFERENCED_TABLE_NAME: string|null,
     *          REFERENCED_COLUMN_NAME: string|null,
     *          DELETE_RULE: FkRule|null,
     *          UPDATE_RULE: FkRule|null
     *          } $foreignKeyInfo
     * * @return string
     */
    public function generateAddForeignKeyStatement(
        string $tableName,
        string $constraintName,
        array $foreignKeyInfo
    ): string {
        $columnName = $foreignKeyInfo['COLUMN_NAME'];
        $referencedTable = $foreignKeyInfo['REFERENCED_TABLE_NAME'];
        $referencedColumn = $foreignKeyInfo['REFERENCED_COLUMN_NAME'];
        $updateRule = $foreignKeyInfo['UPDATE_RULE'] ?? FkRule::NO_ACTION;
        $deleteRule = $foreignKeyInfo['DELETE_RULE'] ?? FkRule::NO_ACTION;

        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';
        $code[] = '        $tableDefinition->foreignKey("' . $constraintName . '")';
        $code[] = '            ->column("' . $columnName . '")';
        $code[] = '            ->references("' . $referencedTable . '", "' . $referencedColumn . '")';
        $code[] = '            ->onUpdate(' . $updateRule->toEnumCallString() . ')';
        $code[] = '            ->onDelete(' . $deleteRule->toEnumCallString() . ');';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * Generate drop foreign key statement
     * @param string $tableName
     * @param string $constraintName
     * @return string
     */
    public function generateDropForeignKeyStatement(string $tableName, string $constraintName): string
    {
        $code = [];
        $code[] = '$schema = new SchemaBuilder();';
        $code[] = '        $tableDefinition = $schema->alterTable("' . $tableName . '");';
        $code[] = '        $tableDefinition->dropForeignKey("' . $constraintName . '");';
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';

        return implode("\n", $code);
    }

    /**
     * @param string $tableName
     * @param DbMappingInterface $dbMapping
     * @return class-string
     * @throws ReflectionException
     */
    private function findEntityClassForTable(string $tableName, DbMappingInterface $dbMapping): string
    {
        foreach ($dbMapping->getEntities() as $entity) {
            if ($dbMapping->getTableName($entity) === $tableName) {
                return $entity;
            }
        }
        throw new RuntimeException("Could not find entity class for table '$tableName'");
    }

    /**
     * @param array<string> &$code
     * @param class-string $entityClass
     * @param DbMappingInterface $dbMapping
     * @return void
     * @throws ReflectionException
     */
    private function addColumnDefinitions(array &$code, string $entityClass, DbMappingInterface $dbMapping): void
    {
        foreach ($dbMapping->getPropertiesColumns($entityClass) as $property => $columnName) {
            $columnDefinitionCode = $this->generateColumnDefinitionFromMapping(
                $entityClass,
                $property,
                $columnName,
                $dbMapping
            );
            $code[] = '        ' . $columnDefinitionCode;

            if ($dbMapping->getColumnKey($entityClass, $property) === 'PRI') {
                $code[] = '        $tableDefinition->primaryKey("' . $columnName . '");';
            }
        }
    }

    /**
     * @param array<string> &$code
     * @return void
     */
    private function addTableConfiguration(array &$code): void
    {
        $code[] = '        $tableDefinition->engine("InnoDB")';
        $code[] = '            ->charset("utf8mb4")';
        $code[] = '            ->collation("utf8mb4_unicode_ci");';
    }

    /**
     * @param array<string> &$code
     * @return void
     */
    private function addExecutionCode(array &$code): void
    {
        $code[] = '        $sql = $tableDefinition->toSql();';
        $code[] = '        $this->entityManager->getPdm()->exec($sql);';
    }

    /**
     * @param class-string $entityClass
     * @param string $property
     * @param string $columnName
     * @param DbMappingInterface $dbMapping
     * @return string
     * @throws ReflectionException
     */
    private function generateColumnDefinitionFromMapping(
        string $entityClass,
        string $property,
        string $columnName,
        DbMappingInterface $dbMapping
    ): string {
        $code = '$tableDefinition->column("' . $columnName . '")';

        $columnTypeDefinition = $dbMapping->getColumnTypeDefinition($entityClass, $property);
        if ($columnTypeDefinition) {
            $typeConverter = new SqlTypeConverter();
            $code .= $typeConverter->convertToBuilderMethod($columnTypeDefinition);
        } else {
            $code .= '->string()';
        }

        $this->addColumnConstraints($code, $entityClass, $property, $dbMapping);

        return $code . ';';
    }

    /**
     * Add constraints to the column definition
     * @param string &$code
     * @param class-string $entityClass
     * @param string $property
     * @param DbMappingInterface $dbMapping
     * @return void
     * @throws ReflectionException
     */
    private function addColumnConstraints(
        string &$code,
        string $entityClass,
        string $property,
        DbMappingInterface $dbMapping
    ): void {
        if ($dbMapping->isNullable($entityClass, $property) === false) {
            $code .= '->notNull()';
        }

        $columnDefault = $dbMapping->getColumnDefault($entityClass, $property);
        if ($columnDefault !== null && $columnDefault !== '') {
            $code .= '->default("' . addslashes($columnDefault) . '")';
        }

        $columnExtra = $dbMapping->getExtra($entityClass, $property);
        if ($columnExtra && str_contains($columnExtra, 'auto_increment')) {
            $code .= '->autoIncrement()';
        }
    }

    /**
     * @param string|null $columnType
     * @param string $columnName
     * @param bool $isNullable
     * @param string|null $columnDefault
     * @param string|null $columnExtra
     * @param SqlTypeConverter $typeConverter
     * @return string
     */
    public function generateColumnDefinitionFromType(
        ?string $columnType,
        string $columnName,
        bool $isNullable,
        ?string $columnDefault,
        ?string $columnExtra,
        SqlTypeConverter $typeConverter
    ): string {
        $code = '$tableDefinition->column("' . $columnName . '")';

        if ($columnType) {
            $code .= $typeConverter->convertToBuilderMethod($columnType);
        } else {
            $code .= '->string()';
        }

        if (!$isNullable) {
            $code .= '->notNull()';
        }

        if ($columnDefault !== null && $columnDefault !== '') {
            $code .= '->default("' . addslashes($columnDefault) . '")';
        }

        if ($columnExtra && str_contains($columnExtra, 'auto_increment')) {
            $code .= '->autoIncrement()';
        }

        return $code . ';';
    }
}
