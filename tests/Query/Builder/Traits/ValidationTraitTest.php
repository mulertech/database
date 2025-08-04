<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder\Traits;

use MulerTech\Database\Query\Builder\Traits\ValidationTrait;
use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for ValidationTrait
 */
class ValidationTraitTest extends TestCase
{
    private TestableValidationBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new TestableValidationBuilder();
    }

    public function testValidateTableNameValid(): void
    {
        // Should not throw exception
        $this->builder->testValidateTableName('users');
        $this->builder->testValidateTableName('user_profiles');
        $this->builder->testValidateTableName('table_123');
        $this->builder->testValidateTableName('_private_table');
        
        $this->addToAssertionCount(4);
    }

    public function testValidateTableNameEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Table name cannot be empty');
        
        $this->builder->testValidateTableName('');
    }

    public function testValidateTableNameInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name format');
        
        $this->builder->testValidateTableName('123invalid');
    }

    public function testValidateTableNameWithSpecialCharacters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name format');
        
        $this->builder->testValidateTableName('user-table');
    }

    public function testValidateTableNameWithSpaces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name format');
        
        $this->builder->testValidateTableName('user table');
    }

    public function testValidateTableNameWithDots(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid table name format');
        
        $this->builder->testValidateTableName('user.table');
    }

    public function testValidateColumnNameValid(): void
    {
        // Should not throw exception
        $this->builder->testValidateColumnName('id');
        $this->builder->testValidateColumnName('user_name');
        $this->builder->testValidateColumnName('column_123');
        $this->builder->testValidateColumnName('_private_column');
        
        $this->addToAssertionCount(4);
    }

    public function testValidateColumnNameEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column name cannot be empty');
        
        $this->builder->testValidateColumnName('');
    }

    public function testValidateColumnNameInvalidFormat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name format');
        
        $this->builder->testValidateColumnName('123invalid');
    }

    public function testValidateColumnNameWithSpecialCharacters(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name format');
        
        $this->builder->testValidateColumnName('user-name');
    }

    public function testValidateColumnNameWithSpaces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name format');
        
        $this->builder->testValidateColumnName('user name');
    }

    public function testValidateColumnNamesValid(): void
    {
        // Should not throw exception
        $this->builder->testValidateColumnNames(['id', 'name', 'email']);
        $this->builder->testValidateColumnNames(['user_id', 'profile_name']);
        $this->builder->testValidateColumnNames(['_private']);
        
        $this->addToAssertionCount(3);
    }

    public function testValidateColumnNamesEmpty(): void
    {
        // Should not throw exception for empty array
        $this->builder->testValidateColumnNames([]);
        
        $this->addToAssertionCount(1);
    }

    public function testValidateColumnNamesWithInvalidColumn(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid column name format');
        
        $this->builder->testValidateColumnNames(['id', 'name', '123invalid']);
    }

    public function testValidateColumnNamesWithEmptyColumn(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Column name cannot be empty');
        
        $this->builder->testValidateColumnNames(['id', '', 'name']);
    }

    public function testValidateNotEmptyWithString(): void
    {
        // Should not throw exception for non-empty string
        $this->builder->testValidateNotEmpty('value', 'Test Field');
        
        $this->addToAssertionCount(1);
    }

    public function testValidateNotEmptyWithStringZero(): void
    {
        // String '0' is considered empty by PHP's empty() function
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Field cannot be empty');
        
        $this->builder->testValidateNotEmpty('0', 'Test Field');
    }

    public function testValidateNotEmptyWithEmptyString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Field cannot be empty');
        
        $this->builder->testValidateNotEmpty('', 'Test Field');
    }

    public function testValidateNotEmptyWithNull(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Field cannot be empty');
        
        $this->builder->testValidateNotEmpty(null, 'Test Field');
    }

    public function testValidateNotEmptyWithZero(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Field cannot be empty');
        
        $this->builder->testValidateNotEmpty(0, 'Test Field');
    }

    public function testValidateNotEmptyWithFalse(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Field cannot be empty');
        
        $this->builder->testValidateNotEmpty(false, 'Test Field');
    }

    public function testValidateNotEmptyWithEmptyArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test Field cannot be empty');
        
        $this->builder->testValidateNotEmpty([], 'Test Field');
    }

    public function testValidateNotEmptyWithArray(): void
    {
        // Should not throw exception
        $this->builder->testValidateNotEmpty(['value'], 'Test Field');
        
        $this->addToAssertionCount(1);
    }

    public function testValidateNotEmptyWithNumber(): void
    {
        // Should not throw exception
        $this->builder->testValidateNotEmpty(1, 'Test Field');
        $this->builder->testValidateNotEmpty(1.5, 'Test Field');
        
        $this->addToAssertionCount(2);
    }

    public function testValidateNotEmptyWithTrue(): void
    {
        // Should not throw exception
        $this->builder->testValidateNotEmpty(true, 'Test Field');
        
        $this->addToAssertionCount(1);
    }

    public function testMultipleValidations(): void
    {
        // Should not throw exception
        $this->builder->testValidateTableName('users');
        $this->builder->testValidateColumnName('id');
        $this->builder->testValidateColumnNames(['name', 'email']);
        $this->builder->testValidateNotEmpty('value', 'Test Field');
        
        $this->addToAssertionCount(4);
    }

    public function testComplexTableNames(): void
    {
        // Valid complex table names
        $this->builder->testValidateTableName('user_profile_settings');
        $this->builder->testValidateTableName('table_with_123_numbers');
        $this->builder->testValidateTableName('_underscore_start');
        
        $this->addToAssertionCount(3);
    }

    public function testComplexColumnNames(): void
    {
        // Valid complex column names
        $this->builder->testValidateColumnName('very_long_column_name_with_underscores');
        $this->builder->testValidateColumnName('column_with_123_numbers');
        $this->builder->testValidateColumnName('_private_column_name');
        
        $this->addToAssertionCount(3);
    }
}

/**
 * Testable implementation of a query builder using ValidationTrait
 */
class TestableValidationBuilder extends AbstractQueryBuilder
{
    use ValidationTrait;

    public function getQueryType(): string
    {
        return 'TEST';
    }

    protected function buildSql(): string
    {
        return 'SELECT * FROM test';
    }

    // Expose protected methods for testing
    public function testValidateTableName(string $table): void
    {
        $this->validateTableName($table);
    }

    public function testValidateColumnName(string $column): void
    {
        $this->validateColumnName($column);
    }

    public function testValidateColumnNames(array $columns): void
    {
        $this->validateColumnNames($columns);
    }

    public function testValidateNotEmpty(mixed $value, string $fieldName): void
    {
        $this->validateNotEmpty($value, $fieldName);
    }
}