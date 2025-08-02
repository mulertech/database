<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Diff;

use MulerTech\Database\Mapping\ColumnMapping;
use ReflectionException;
use RuntimeException;

/**
 * Compare columns between entity mapping and database schema
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class ColumnComparer
{
    private ColumnMapping $columnMapping;

    public function __construct()
    {
        $this->columnMapping = new ColumnMapping();
    }

    /**
     * Compare columns between entity mapping and database
     *
     * @param string $tableName
     * @param class-string $entityClass
     * @param array<string, string> $entityPropertiesColumns
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
     * @throws ReflectionException
     */
    public function compareColumns(
        string $tableName,
        string $entityClass,
        array $entityPropertiesColumns,
        array $databaseColumns,
        SchemaDifference $diff
    ): void {
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
        foreach ($entityPropertiesColumns as $property => $columnName) {
            if (isset($databaseColumns[$columnName])) {
                continue;
            }

            $columnInfo = $this->getColumnInfo($entityClass, $property);

            if ($columnInfo['COLUMN_TYPE'] === null) {
                throw new RuntimeException(
                    "Column type for $entityClass::$property is not defined in entity metadata"
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
            $entityColumnInfo = $this->getColumnInfo($entityClass, $property);

            if ($entityColumnInfo['COLUMN_TYPE'] === null) {
                throw new RuntimeException(
                    "Column type for $entityClass::$property is not defined in entity metadata"
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
        foreach ($databaseColumns as $columnName => $value) {
            if (!in_array($columnName, $entityPropertiesColumns, true)) {
                $diff->addColumnToDrop($tableName, $columnName);
            }
        }
    }

    /**
     * Get column info for entity property
     *
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
    private function getColumnInfo(string $entityClass, string $property): array
    {
        return [
            'COLUMN_TYPE' => $this->columnMapping->getColumnTypeDefinition($entityClass, $property),
            'IS_NULLABLE' => $this->columnMapping->isNullable($entityClass, $property) === false ? 'NO' : 'YES',
            'COLUMN_DEFAULT' => $this->columnMapping->getColumnDefault($entityClass, $property),
            'EXTRA' => $this->columnMapping->getExtra($entityClass, $property),
            'COLUMN_KEY' => $this->columnMapping->getColumnKey($entityClass, $property),
        ];
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
     * @param array{
     *      COLUMN_TYPE?: array{from: string, to: string},
     *      IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *      COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *      EXTRA?: array{from: string|null, to: string|null}
     *  } $differences
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
     * @param array{
     *      COLUMN_TYPE?: array{from: string, to: string},
     *      IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *      COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *      EXTRA?: array{from: string|null, to: string|null}
     *  } $differences
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
     * @param array{
     *      COLUMN_TYPE?: array{from: string, to: string},
     *      IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *      COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *      EXTRA?: array{from: string|null, to: string|null}
     *  } $differences
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
     * @param array{
     *      COLUMN_TYPE?: array{from: string, to: string},
     *      IS_NULLABLE?: array{from: 'YES'|'NO', to: 'YES'|'NO'},
     *      COLUMN_DEFAULT?: array{from: string|null, to: string|null},
     *      EXTRA?: array{from: string|null, to: string|null}
     *  } $differences
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
     * @param string|null $value
     * @return string|null
     */
    private function normalizeDefaultValue(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * Normalize extra value (convert empty string to null)
     * @param string|null $value
     * @return string|null
     */
    private function normalizeExtraValue(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
