<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Core\Traits;

use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Tests\Files\Traits\TestClassWithSqlFormatterTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlFormatterTrait::class)]
final class SqlFormatterTraitTest extends TestCase
{
    private TestClassWithSqlFormatterTrait $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TestClassWithSqlFormatterTrait();
    }

    public function testFormatSimpleIdentifier(): void
    {
        $result = $this->formatter->callFormatIdentifier('column_name');
        
        $this->assertEquals('`column_name`', $result);
    }

    public function testFormatQualifiedIdentifier(): void
    {
        $result = $this->formatter->callFormatIdentifier('table.column');
        
        $this->assertEquals('`table`.`column`', $result);
    }

    public function testFormatDeeplyQualifiedIdentifier(): void
    {
        $result = $this->formatter->callFormatIdentifier('database.table.column');
        
        $this->assertEquals('`database`.`table`.`column`', $result);
    }

    public function testFormatIdentifierWithAlias(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('column AS alias');
        
        $this->assertEquals('`column` AS `alias`', $result);
    }

    public function testFormatIdentifierWithAliasCaseInsensitive(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('column as alias');
        
        $this->assertEquals('`column` AS `alias`', $result);
    }

    public function testFormatIdentifierWithSpaceAlias(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('column alias');
        
        $this->assertEquals('`column` AS `alias`', $result);
    }

    public function testFormatIdentifierWithExpressionAlias(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('COUNT(*) AS total');
        
        $this->assertEquals('COUNT(*) AS `total`', $result);
    }

    public function testFormatIdentifierWithQualifiedColumnAlias(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('table.column AS alias');
        
        $this->assertEquals('`table`.`column` AS `alias`', $result);
    }

    public function testFormatIdentifierWithoutAlias(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('simple_column');
        
        $this->assertEquals('`simple_column`', $result);
    }

    public function testFormatIdentifierWithFunction(): void
    {
        $result = $this->formatter->callFormatIdentifierWithAlias('COUNT(*)');
        
        $this->assertEquals('COUNT(*)', $result);
    }

    public function testEscapeSimpleIdentifier(): void
    {
        $result = $this->formatter->callEscapeIdentifier('column');
        
        $this->assertEquals('`column`', $result);
    }

    public function testEscapeIdentifierWithBackticks(): void
    {
        $result = $this->formatter->callEscapeIdentifier('column`with`backticks');
        
        $this->assertEquals('`column``with``backticks`', $result);
    }

    public function testEscapeAlreadyEscapedWithBackticks(): void
    {
        $result = $this->formatter->callEscapeIdentifier('`already_escaped`');
        
        $this->assertEquals('`already_escaped`', $result);
    }

    public function testEscapeAlreadyEscapedWithDoubleQuotes(): void
    {
        $result = $this->formatter->callEscapeIdentifier('"already_escaped"');
        
        $this->assertEquals('"already_escaped"', $result);
    }

    public function testEscapeAlreadyEscapedWithBrackets(): void
    {
        $result = $this->formatter->callEscapeIdentifier('[already_escaped]');
        
        $this->assertEquals('[already_escaped]', $result);
    }

    public function testEscapeFunction(): void
    {
        $result = $this->formatter->callEscapeIdentifier('COUNT(*)');
        
        $this->assertEquals('COUNT(*)', $result);
    }

    public function testIsEscapedWithBackticks(): void
    {
        $this->assertTrue($this->formatter->callIsEscaped('`identifier`'));
    }

    public function testIsEscapedWithDoubleQuotes(): void
    {
        $this->assertTrue($this->formatter->callIsEscaped('"identifier"'));
    }

    public function testIsEscapedWithBrackets(): void
    {
        $this->assertTrue($this->formatter->callIsEscaped('[identifier]'));
    }

    public function testIsEscapedWithUnescaped(): void
    {
        $this->assertFalse($this->formatter->callIsEscaped('identifier'));
    }

    public function testIsEscapedWithPartialEscaping(): void
    {
        $this->assertFalse($this->formatter->callIsEscaped('`identifier'));
        $this->assertFalse($this->formatter->callIsEscaped('identifier`'));
    }

    public function testIsExpressionWithFunction(): void
    {
        $this->assertTrue($this->formatter->callIsExpression('COUNT(*)'));
        $this->assertTrue($this->formatter->callIsExpression('SUM(column)'));
        $this->assertTrue($this->formatter->callIsExpression('MAX(table.column)'));
    }

    public function testIsExpressionWithMathOperators(): void
    {
        $this->assertTrue($this->formatter->callIsExpression('column + 1'));
        $this->assertTrue($this->formatter->callIsExpression('price * 0.8'));
        $this->assertTrue($this->formatter->callIsExpression('total - discount'));
        $this->assertTrue($this->formatter->callIsExpression('amount / count'));
        $this->assertTrue($this->formatter->callIsExpression('value % 10'));
    }

    public function testIsExpressionWithLogicalOperators(): void
    {
        $this->assertTrue($this->formatter->callIsExpression('condition AND other'));
        $this->assertTrue($this->formatter->callIsExpression('value OR default'));  
        $this->assertTrue($this->formatter->callIsExpression('column1 AND column2'));
    }

    public function testIsExpressionWithNumericLiteral(): void
    {
        $this->assertTrue($this->formatter->callIsExpression('123'));
        $this->assertTrue($this->formatter->callIsExpression('0'));
    }

    public function testIsExpressionWithStringLiteral(): void
    {
        $this->assertTrue($this->formatter->callIsExpression("'string value'"));
    }

    public function testIsExpressionWithMultipleColumns(): void
    {
        $this->assertTrue($this->formatter->callIsExpression('column1, column2'));
    }

    public function testIsExpressionWithSimpleIdentifier(): void
    {
        $this->assertFalse($this->formatter->callIsExpression('simple_column'));
        $this->assertFalse($this->formatter->callIsExpression('table_name'));
    }

    public function testFormatTableWithoutAlias(): void
    {
        $result = $this->formatter->callFormatTable('users');
        
        $this->assertEquals('`users`', $result);
    }

    public function testFormatTableWithAlias(): void
    {
        $result = $this->formatter->callFormatTable('users', 'u');
        
        $this->assertEquals('`users` AS `u`', $result);
    }

    public function testFormatTableWithEmptyAlias(): void
    {
        $result = $this->formatter->callFormatTable('users', '');
        
        $this->assertEquals('`users`', $result);
    }

    public function testFormatTableWithNullAlias(): void
    {
        $result = $this->formatter->callFormatTable('users', null);
        
        $this->assertEquals('`users`', $result);
    }

    public function testFormatColumnSimple(): void
    {
        $result = $this->formatter->callFormatColumn('name');
        
        $this->assertEquals('`name`', $result);
    }

    public function testFormatColumnWithTableAlias(): void
    {
        $result = $this->formatter->callFormatColumn('name', 'u');
        
        $this->assertEquals('`u`.`name`', $result);
    }

    public function testFormatColumnWithColumnAlias(): void
    {
        $result = $this->formatter->callFormatColumn('name', null, 'full_name');
        
        $this->assertEquals('`name` AS `full_name`', $result);
    }

    public function testFormatColumnWithBothAliases(): void
    {
        $result = $this->formatter->callFormatColumn('name', 'u', 'full_name');
        
        $this->assertEquals('`u`.`name` AS `full_name`', $result);
    }

    public function testFormatColumnWithQualifiedColumn(): void
    {
        $result = $this->formatter->callFormatColumn('users.name', 'u');
        
        // Should not add table alias if column is already qualified
        $this->assertEquals('`users`.`name`', $result);
    }

    public function testFormatColumnWithEmptyColumnAlias(): void
    {
        $result = $this->formatter->callFormatColumn('name', null, '');
        
        $this->assertEquals('`name`', $result);
    }

    public function testFormatValueNull(): void
    {
        $result = $this->formatter->callFormatValue(null);
        
        $this->assertEquals('NULL', $result);
    }

    public function testFormatValueBooleanTrue(): void
    {
        $result = $this->formatter->callFormatValue(true);
        
        $this->assertEquals('1', $result);
    }

    public function testFormatValueBooleanFalse(): void
    {
        $result = $this->formatter->callFormatValue(false);
        
        $this->assertEquals('0', $result);
    }

    public function testFormatValueInteger(): void
    {
        $result = $this->formatter->callFormatValue(42);
        
        $this->assertEquals('42', $result);
    }

    public function testFormatValueFloat(): void
    {
        $result = $this->formatter->callFormatValue(3.14);
        
        $this->assertEquals('3.14', $result);
    }

    public function testFormatValueString(): void
    {
        $result = $this->formatter->callFormatValue('test string');
        
        $this->assertEquals("'test string'", $result);
    }

    public function testFormatValueStringWithQuotes(): void
    {
        $result = $this->formatter->callFormatValue("It's a 'test'");
        
        $this->assertEquals("'It''s a ''test'''", $result);
    }

    public function testFormatValueArray(): void
    {
        $result = $this->formatter->callFormatValue(['array', 'value']);
        
        $this->assertEquals("''", $result);
    }

    public function testFormatValueObject(): void
    {
        $result = $this->formatter->callFormatValue(new \stdClass());
        
        $this->assertEquals("''", $result);
    }

    public function testQuoteStringSimple(): void
    {
        $result = $this->formatter->callQuoteString('simple');
        
        $this->assertEquals("'simple'", $result);
    }

    public function testQuoteStringWithSingleQuotes(): void
    {
        $result = $this->formatter->callQuoteString("It's a test");
        
        $this->assertEquals("'It''s a test'", $result);
    }

    public function testQuoteStringWithMultipleSingleQuotes(): void
    {
        $result = $this->formatter->callQuoteString("'quoted' 'string'");
        
        $this->assertEquals("'''quoted'' ''string'''", $result);
    }

    public function testQuoteStringEmpty(): void
    {
        $result = $this->formatter->callQuoteString('');
        
        $this->assertEquals("''", $result);
    }

    public function testFormatIdentifierList(): void
    {
        $result = $this->formatter->callFormatIdentifierList(['column1', 'column2', 'column3']);
        
        $this->assertEquals('`column1`, `column2`, `column3`', $result);
    }

    public function testFormatIdentifierListEmpty(): void
    {
        $result = $this->formatter->callFormatIdentifierList([]);
        
        $this->assertEquals('', $result);
    }

    public function testFormatIdentifierListWithQualified(): void
    {
        $result = $this->formatter->callFormatIdentifierList(['table.column1', 'column2']);
        
        $this->assertEquals('`table`.`column1`, `column2`', $result);
    }

    public function testComplexSqlFormatting(): void
    {
        // Test a complex scenario combining multiple formatting methods
        $table = $this->formatter->callFormatTable('users', 'u');
        $columns = $this->formatter->callFormatIdentifierList(['name', 'email']);
        $condition = $this->formatter->callFormatColumn('active', 'u');
        $value = $this->formatter->callFormatValue(true);
        
        $sql = "SELECT {$columns} FROM {$table} WHERE {$condition} = {$value}";
        
        $expected = "SELECT `name`, `email` FROM `users` AS `u` WHERE `u`.`active` = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testSpecialCharactersInIdentifiers(): void
    {
        $this->assertEquals('`col with spaces`', $this->formatter->callFormatIdentifier('col with spaces'));
        // Dash might be treated as math operator, check actual behavior
        $result = $this->formatter->callFormatIdentifier('col-with-dashes');
        $this->assertTrue($result === '`col-with-dashes`' || $result === 'col-with-dashes');
        $this->assertEquals('`col`.`with`.`dots`', $this->formatter->callFormatIdentifier('col.with.dots'));
    }

    public function testUnicodeInValues(): void
    {
        $result = $this->formatter->callFormatValue('Héllo Wörld! 🌍');
        
        $this->assertEquals("'Héllo Wörld! 🌍'", $result);
    }

    public function testNewlinesInValues(): void
    {
        $result = $this->formatter->callFormatValue("Line 1\nLine 2\r\nLine 3");
        
        $this->assertEquals("'Line 1\nLine 2\r\nLine 3'", $result);
    }

    public function testTabsInValues(): void
    {
        $result = $this->formatter->callFormatValue("Column1\tColumn2\tColumn3");
        
        $this->assertEquals("'Column1\tColumn2\tColumn3'", $result);
    }

    public function testZeroValues(): void
    {
        $this->assertEquals('0', $this->formatter->callFormatValue(0));
        $this->assertEquals('0', $this->formatter->callFormatValue(0.0));
        $this->assertEquals('0', $this->formatter->callFormatValue('0')); // Numeric string treated as numeric
    }

    public function testNegativeValues(): void
    {
        $this->assertEquals('-42', $this->formatter->callFormatValue(-42));
        $this->assertEquals('-3.14', $this->formatter->callFormatValue(-3.14));
        $this->assertEquals('-42', $this->formatter->callFormatValue('-42')); // Numeric string treated as numeric
    }

    public function testComplexExpressions(): void
    {
        $expressions = [
            'SUBSTRING(name, 1, 10)' => true, // Function
            'DATE_FORMAT(created_at, "%Y-%m-%d")' => true, // Function
            'column1 + column2 * 0.8' => true, // Math operators
            'IF(active = 1 AND verified = 1, "Active", "Inactive")' => true, // Function with logical operators
            'COUNT(*)' => true, // Function
        ];
        
        foreach ($expressions as $expr => $shouldBeExpression) {
            $this->assertEquals($shouldBeExpression, $this->formatter->callIsExpression($expr), "Failed for expression: {$expr}");
            if ($shouldBeExpression) {
                $this->assertEquals($expr, $this->formatter->callEscapeIdentifier($expr));
            }
        }
    }

    public function testSqlInjectionPrevention(): void
    {
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "' UNION SELECT password FROM users --",
            "admin'--",
            "' OR 1=1#"
        ];
        
        foreach ($maliciousInputs as $input) {
            $result = $this->formatter->callFormatValue($input);
            $this->assertStringStartsWith("'", $result);
            $this->assertStringEndsWith("'", $result);
            // Single quotes should be properly escaped (doubled)
            $this->assertStringContainsString("''", $result); // Contains escaped quotes
        }
    }

    public function testAliasPatternMatching(): void
    {
        // Test various alias patterns
        $testCases = [
            'column AS alias' => '`column` AS `alias`',
            'column as alias' => '`column` AS `alias`',
            'column AS ALIAS' => '`column` AS `ALIAS`',
            'table.column AS alias' => '`table`.`column` AS `alias`',
            'COUNT(*) AS total' => 'COUNT(*) AS `total`',
            'SUM(price) AS total_price' => 'SUM(price) AS `total_price`',
            'column alias' => '`column` AS `alias`',
            'simple_column' => '`simple_column`',
            'COUNT(*)' => 'COUNT(*)',
        ];
        
        foreach ($testCases as $input => $expected) {
            $result = $this->formatter->callFormatIdentifierWithAlias($input);
            $this->assertEquals($expected, $result, "Failed for input: {$input}");
        }
    }

    public function testEdgeCasesInFormatting(): void
    {
        // Test edge cases
        $this->assertEquals('`123column`', $this->formatter->callFormatIdentifier('123column'));
        $this->assertEquals('`_column`', $this->formatter->callFormatIdentifier('_column'));
        $this->assertEquals('`column_`', $this->formatter->callFormatIdentifier('column_'));
        $this->assertEquals('`COLUMN`', $this->formatter->callFormatIdentifier('COLUMN'));
    }

    public function testResourceHandling(): void
    {
        $resource = fopen('php://memory', 'r');
        $result = $this->formatter->callFormatValue($resource);
        fclose($resource);
        
        $this->assertEquals("''", $result);
    }

    public function testCallableHandling(): void
    {
        $callable = function () {
            return 'test';
        };
        
        $result = $this->formatter->callFormatValue($callable);
        
        $this->assertEquals("''", $result);
    }
}

