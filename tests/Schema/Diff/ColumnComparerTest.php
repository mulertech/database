<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Diff;

use MulerTech\Database\Schema\Diff\ColumnComparer;
use MulerTech\Database\Schema\Diff\SchemaDifference;
use MulerTech\Database\Tests\Files\Mapping\TestEntityWithColumns;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;

/**
 * Test cases for ColumnComparer class
 */
class ColumnComparerTest extends TestCase
{
    private ColumnComparer $comparer;
    private SchemaDifference $diff;

    protected function setUp(): void
    {
        $this->comparer = new ColumnComparer();
        $this->diff = new SchemaDifference();
    }

    public function testCompareColumnsAddsNewColumn(): void
    {
        $entityProperties = ['id' => 'id', 'name' => 'name'];
        $databaseColumns = ['id' => [
            'TABLE_NAME' => 'test_table',
            'COLUMN_NAME' => 'id',
            'COLUMN_TYPE' => 'int(11)',
            'IS_NULLABLE' => 'NO',
            'EXTRA' => 'auto_increment',
            'COLUMN_DEFAULT' => null,
            'COLUMN_KEY' => 'PRI'
        ]];

        $this->comparer->compareColumns(
            'test_table',
            TestEntityWithColumns::class,
            $entityProperties,
            $databaseColumns,
            $this->diff
        );

        $columnsToAdd = $this->diff->getColumnsToAdd();
        $this->assertArrayHasKey('test_table', $columnsToAdd);
        $this->assertArrayHasKey('name', $columnsToAdd['test_table']);
    }

    public function testCompareColumnsDetectsModifications(): void
    {
        $entityProperties = ['id' => 'id'];
        $databaseColumns = ['id' => [
            'TABLE_NAME' => 'test_table',
            'COLUMN_NAME' => 'id',
            'COLUMN_TYPE' => 'varchar(255)', // Different from entity (INT)
            'IS_NULLABLE' => 'NO',
            'EXTRA' => '',
            'COLUMN_DEFAULT' => null,
            'COLUMN_KEY' => null
        ]];

        $this->comparer->compareColumns(
            'test_table',
            TestEntityWithColumns::class,
            $entityProperties,
            $databaseColumns,
            $this->diff
        );

        $columnsToModify = $this->diff->getColumnsToModify();
        $this->assertArrayHasKey('test_table', $columnsToModify);
        $this->assertArrayHasKey('id', $columnsToModify['test_table']);
    }

    public function testCompareColumnsDetectsColumnsToDrop(): void
    {
        $entityProperties = ['id' => 'id'];
        $databaseColumns = [
            'id' => [
                'TABLE_NAME' => 'test_table',
                'COLUMN_NAME' => 'id',
                'COLUMN_TYPE' => 'int(11)',
                'IS_NULLABLE' => 'NO',
                'EXTRA' => 'auto_increment',
                'COLUMN_DEFAULT' => null,
                'COLUMN_KEY' => 'PRI'
            ],
            'old_column' => [
                'TABLE_NAME' => 'test_table',
                'COLUMN_NAME' => 'old_column',
                'COLUMN_TYPE' => 'varchar(255)',
                'IS_NULLABLE' => 'YES',
                'EXTRA' => '',
                'COLUMN_DEFAULT' => null,
                'COLUMN_KEY' => null
            ]
        ];

        $this->comparer->compareColumns(
            'test_table',
            TestEntityWithColumns::class,
            $entityProperties,
            $databaseColumns,
            $this->diff
        );

        $columnsToDrop = $this->diff->getColumnsToDrop();
        $this->assertArrayHasKey('test_table', $columnsToDrop);
        $this->assertContains('old_column', $columnsToDrop['test_table']);
    }

    public function testThrowsExceptionWhenColumnTypeIsNullForNewColumn(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column type for ' . TestEntityWithColumns::class . '::noColumnAttribute is not defined in entity metadata');

        $entityProperties = ['noColumnAttribute' => 'no_column_attribute'];
        $databaseColumns = [];

        $this->comparer->compareColumns(
            'test_table',
            TestEntityWithColumns::class,
            $entityProperties,
            $databaseColumns,
            $this->diff
        );
    }

    public function testThrowsExceptionWhenColumnTypeIsNullForModification(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column type for ' . TestEntityWithColumns::class . '::noColumnAttribute is not defined in entity metadata');

        $entityProperties = ['noColumnAttribute' => 'no_column_attribute'];
        $databaseColumns = ['no_column_attribute' => [
            'TABLE_NAME' => 'test_table',
            'COLUMN_NAME' => 'no_column_attribute',
            'COLUMN_TYPE' => 'varchar(255)',
            'IS_NULLABLE' => 'YES',
            'EXTRA' => '',
            'COLUMN_DEFAULT' => null,
            'COLUMN_KEY' => null
        ]];

        $this->comparer->compareColumns(
            'test_table',
            TestEntityWithColumns::class,
            $entityProperties,
            $databaseColumns,
            $this->diff
        );
    }
}