<?php

namespace MulerTech\Database\Tests\Schema\Builder;

use MulerTech\Database\Schema\Builder\ColumnDefinition;
use PHPUnit\Framework\TestCase;

class ColumnDefinitionTest extends TestCase
{
    /**
     * Test creation of a column with a name
     */
    public function testConstructor(): void
    {
        $column = new ColumnDefinition('test_column');
        $this->assertEquals('test_column', $column->getName());
    }
    
    /**
     * Test integer column type
     */
    public function testInteger(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->integer();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` INT', $sql);
    }

    /**
     * Test tinyInt column type
     */
    public function testTinyInt(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->tinyInt();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` TINYINT', $sql);
    }

    /**
     * Test smallInt column type
     */
    public function testSmallInt(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->smallInt();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` SMALLINT', $sql);
    }

    /**
     * Test mediumInt column type
     */
    public function testMediumInt(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->mediumInt();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` MEDIUMINT', $sql);
    }
    
    /**
     * Test big integer column type
     */
    public function testBigInteger(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->bigInteger();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` BIGINT', $sql);
    }
    
    /**
     * Test string column type with default length
     */
    public function testStringWithDefaultLength(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` VARCHAR(255)', $sql);
    }
    
    /**
     * Test string column type with custom length
     */
    public function testStringWithCustomLength(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string(100);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` VARCHAR(100)', $sql);
    }

    /**
     * Test char column type
     */
    public function testChar(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->char(10);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` CHAR(10)', $sql);
    }
    
    /**
     * Test text column type
     */
    public function testText(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->text();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` TEXT', $sql);
    }

    /**
     * Test tinyText column type
     */
    public function testTinyText(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->tinyText();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` TINYTEXT', $sql);
    }

    /**
     * Test mediumText column type
     */
    public function testMediumText(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->mediumText();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` MEDIUMTEXT', $sql);
    }

    /**
     * Test longText column type
     */
    public function testLongText(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->longText();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` LONGTEXT', $sql);
    }

    /**
     * Test decimal column type with precision and scale
     */
    public function testDecimalWithPrecisionAndScale(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->decimal(10, 4);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` DECIMAL(10,4)', $sql);
    }

    /**
     * Test float column type with precision and scale
     */
    public function testFloatWithPrecisionAndScale(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->float(8, 2);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` FLOAT(8,2)', $sql);
    }

    /**
     * Test double column type
     */
    public function testDouble(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->double();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` DOUBLE', $sql);
    }

    /**
     * Test date column type
     */
    public function testDate(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->date();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` DATE', $sql);
    }

    /**
     * Test datetime column type
     */
    public function testDatetime(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->datetime();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` DATETIME', $sql);
    }

    /**
     * Test timestamp column type
     */
    public function testTimestamp(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->timestamp();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` TIMESTAMP', $sql);
    }

    /**
     * Test time column type
     */
    public function testTime(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->time();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` TIME', $sql);
    }

    /**
     * Test year column type
     */
    public function testYear(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->year();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` YEAR', $sql);
    }

    /**
     * Test binary column type
     */
    public function testBinary(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->binary(16);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` BINARY(16)', $sql);
    }

    /**
     * Test varbinary column type
     */
    public function testVarbinary(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->varbinary(255);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` VARBINARY(255)', $sql);
    }

    /**
     * Test blob column type
     */
    public function testBlob(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->blob();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` BLOB', $sql);
    }

    /**
     * Test tinyBlob column type
     */
    public function testTinyBlob(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->tinyBlob();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` TINYBLOB', $sql);
    }

    /**
     * Test mediumBlob column type
     */
    public function testMediumBlob(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->mediumBlob();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` MEDIUMBLOB', $sql);
    }

    /**
     * Test longBlob column type
     */
    public function testLongBlob(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->longBlob();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` LONGBLOB', $sql);
    }

    /**
     * Test json column type
     */
    public function testJson(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->json();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` JSON', $sql);
    }

    /**
     * Test enum column type
     */
    public function testEnum(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->enum(['active', 'inactive', 'pending']);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` ENUM(\'active\',\'inactive\',\'pending\')', $sql);
    }

    /**
     * Test set column type
     */
    public function testSet(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->set(['read', 'write', 'execute']);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` SET(\'read\',\'write\',\'execute\')', $sql);
    }

    /**
     * Test geometry column type
     */
    public function testGeometry(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->geometry();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` GEOMETRY', $sql);
    }

    /**
     * Test point column type
     */
    public function testPoint(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->point();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` POINT', $sql);
    }

    /**
     * Test lineString column type
     */
    public function testLineString(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->lineString();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` LINESTRING', $sql);
    }

    /**
     * Test polygon column type
     */
    public function testPolygon(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->polygon();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` POLYGON', $sql);
    }
    
    /**
     * Test not null constraint
     */
    public function testNotNull(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string()->notNull();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('NOT NULL', $sql);
    }
    
    /**
     * Test setting default value
     */
    public function testDefault(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string()->default('default_value');

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('DEFAULT \'default_value\'', $sql);
    }

    /**
     * Test setting null default value
     */
    public function testDefaultNull(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->string()->default(null);

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringNotContainsString('DEFAULT', $sql);
    }
    
    /**
     * Test auto increment flag
     */
    public function testAutoIncrement(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->integer()->autoIncrement();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('AUTO_INCREMENT', $sql);
    }
    
    /**
     * Test unsigned flag
     */
    public function testUnsigned(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->integer()->unsigned();

        $this->assertSame($column, $result, 'Method should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('UNSIGNED', $sql);
    }

    /**
     * Test method chaining
     */
    public function testMethodChaining(): void
    {
        $column = new ColumnDefinition('test_column');
        $result = $column->integer()->unsigned()->notNull()->autoIncrement()->default('0');

        $this->assertSame($column, $result, 'All methods should return $this for chaining');

        $sql = $column->toSql();
        $this->assertStringContainsString('`test_column` INT UNSIGNED NOT NULL DEFAULT \'0\' AUTO_INCREMENT', $sql);
    }

    /**
     * Test complex column definition
     */
    public function testComplexColumnDefinition(): void
    {
        $column = new ColumnDefinition('user_id');
        $column->bigInteger()->unsigned()->notNull()->autoIncrement();

        $sql = $column->toSql();

        $this->assertStringContainsString('`user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
    }

    /**
     * Test enum with special characters
     */
    public function testEnumWithSpecialCharacters(): void
    {
        $column = new ColumnDefinition('status');
        $column->enum(['pending', 'in-progress', 'done\'s']);

        $sql = $column->toSql();

        $this->assertStringContainsString('`status` ENUM(\'pending\',\'in-progress\',\'done\\\'s\')', $sql);
    }

    /**
     * Test decimal without scale and precision should use defaults
     */
    public function testDecimalDefaults(): void
    {
        $column = new ColumnDefinition('price');
        $column->decimal(8, 2);

        $sql = $column->toSql();

        $this->assertStringContainsString('`price` DECIMAL(8,2)', $sql);
    }

    /**
     * Test nullable column (default behavior)
     */
    public function testNullableByDefault(): void
    {
        $column = new ColumnDefinition('optional_field');
        $column->string();

        $sql = $column->toSql();

        $this->assertStringNotContainsString('NOT NULL', $sql);
    }

    /**
     * Test toSql method generates correct SQL for basic string column
     */
    public function testToSqlBasicString(): void
    {
        $column = new ColumnDefinition('name');
        $column->string(100);

        $sql = $column->toSql();

        $this->assertEquals('`name` VARCHAR(100)', $sql);
    }

    /**
     * Test toSql method generates correct SQL for complete column definition
     */
    public function testToSqlCompleteDefinition(): void
    {
        $column = new ColumnDefinition('user_score');
        $column->decimal(10, 2)->unsigned()->notNull()->default('0.00');

        $sql = $column->toSql();

        $this->assertEquals('`user_score` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT \'0.00\'', $sql);
    }
}
