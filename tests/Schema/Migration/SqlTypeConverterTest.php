<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Schema\Migration;

use MulerTech\Database\Schema\Migration\SqlTypeConverter;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for SqlTypeConverter class
 */
class SqlTypeConverterTest extends TestCase
{
    private SqlTypeConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new SqlTypeConverter();
    }

    public function testIntegerTypes(): void
    {
        $this->assertEquals('->integer()', $this->converter->convertToBuilderMethod('int'));
        $this->assertEquals('->integer()', $this->converter->convertToBuilderMethod('int(11)'));
        $this->assertEquals('->integer()->unsigned()', $this->converter->convertToBuilderMethod('int unsigned'));
        $this->assertEquals('->integer()->unsigned()', $this->converter->convertToBuilderMethod('int(11) unsigned'));
    }

    public function testTinyIntTypes(): void
    {
        $this->assertEquals('->tinyInt()', $this->converter->convertToBuilderMethod('tinyint'));
        $this->assertEquals('->tinyInt()', $this->converter->convertToBuilderMethod('tinyint(4)'));
        $this->assertEquals('->tinyInt()->unsigned()', $this->converter->convertToBuilderMethod('tinyint unsigned'));
        $this->assertEquals('->tinyInt()->unsigned()', $this->converter->convertToBuilderMethod('tinyint(4) unsigned'));
    }

    public function testSmallIntTypes(): void
    {
        $this->assertEquals('->smallInt()', $this->converter->convertToBuilderMethod('smallint'));
        $this->assertEquals('->smallInt()', $this->converter->convertToBuilderMethod('smallint(6)'));
        $this->assertEquals('->smallInt()->unsigned()', $this->converter->convertToBuilderMethod('smallint unsigned'));
        $this->assertEquals('->smallInt()->unsigned()', $this->converter->convertToBuilderMethod('smallint(6) unsigned'));
    }

    public function testMediumIntTypes(): void
    {
        $this->assertEquals('->mediumInt()', $this->converter->convertToBuilderMethod('mediumint'));
        $this->assertEquals('->mediumInt()', $this->converter->convertToBuilderMethod('mediumint(9)'));
        $this->assertEquals('->mediumInt()->unsigned()', $this->converter->convertToBuilderMethod('mediumint unsigned'));
        $this->assertEquals('->mediumInt()->unsigned()', $this->converter->convertToBuilderMethod('mediumint(9) unsigned'));
    }

    public function testBigIntTypes(): void
    {
        $this->assertEquals('->bigInteger()', $this->converter->convertToBuilderMethod('bigint'));
        $this->assertEquals('->bigInteger()', $this->converter->convertToBuilderMethod('bigint(20)'));
        $this->assertEquals('->bigInteger()->unsigned()', $this->converter->convertToBuilderMethod('bigint unsigned'));
        $this->assertEquals('->bigInteger()->unsigned()', $this->converter->convertToBuilderMethod('bigint(20) unsigned'));
    }

    public function testStringTypes(): void
    {
        $this->assertEquals('->string(255)', $this->converter->convertToBuilderMethod('varchar(255)'));
        $this->assertEquals('->string(100)', $this->converter->convertToBuilderMethod('varchar(100)'));
        $this->assertEquals('->char(10)', $this->converter->convertToBuilderMethod('char(10)'));
        $this->assertEquals('->char(1)', $this->converter->convertToBuilderMethod('char(1)'));
    }

    public function testDecimalTypes(): void
    {
        $this->assertEquals('->decimal(8, 2)', $this->converter->convertToBuilderMethod('decimal(8,2)'));
        $this->assertEquals('->decimal(10, 4)', $this->converter->convertToBuilderMethod('decimal(10,4)'));
        $this->assertEquals('->float(7, 4)', $this->converter->convertToBuilderMethod('float(7,4)'));
        $this->assertEquals('->float(5, 2)', $this->converter->convertToBuilderMethod('float(5,2)'));
        $this->assertEquals('->double()', $this->converter->convertToBuilderMethod('double'));
    }

    public function testBinaryTypes(): void
    {
        $this->assertEquals('->binary(16)', $this->converter->convertToBuilderMethod('binary(16)'));
        $this->assertEquals('->binary(32)', $this->converter->convertToBuilderMethod('binary(32)'));
        $this->assertEquals('->varbinary(255)', $this->converter->convertToBuilderMethod('varbinary(255)'));
        $this->assertEquals('->varbinary(100)', $this->converter->convertToBuilderMethod('varbinary(100)'));
    }

    public function testBlobTypes(): void
    {
        $this->assertEquals('->tinyBlob()', $this->converter->convertToBuilderMethod('tinyblob'));
        $this->assertEquals('->blob()', $this->converter->convertToBuilderMethod('blob'));
        $this->assertEquals('->mediumBlob()', $this->converter->convertToBuilderMethod('mediumblob'));
        $this->assertEquals('->longBlob()', $this->converter->convertToBuilderMethod('longblob'));
    }

    public function testTextTypes(): void
    {
        $this->assertEquals('->tinyText()', $this->converter->convertToBuilderMethod('tinytext'));
        $this->assertEquals('->text()', $this->converter->convertToBuilderMethod('text'));
        $this->assertEquals('->mediumText()', $this->converter->convertToBuilderMethod('mediumtext'));
        $this->assertEquals('->longText()', $this->converter->convertToBuilderMethod('longtext'));
    }

    public function testDateTimeTypes(): void
    {
        $this->assertEquals('->datetime()', $this->converter->convertToBuilderMethod('datetime'));
        $this->assertEquals('->timestamp()', $this->converter->convertToBuilderMethod('timestamp'));
        $this->assertEquals('->date()', $this->converter->convertToBuilderMethod('date'));
        $this->assertEquals('->time()', $this->converter->convertToBuilderMethod('time'));
        $this->assertEquals('->year()', $this->converter->convertToBuilderMethod('year'));
    }

    public function testBooleanTypes(): void
    {
        $this->assertEquals('->boolean()', $this->converter->convertToBuilderMethod('boolean'));
        $this->assertEquals('->boolean()', $this->converter->convertToBuilderMethod('bool'));
    }

    public function testJsonTypes(): void
    {
        $this->assertEquals('->json()', $this->converter->convertToBuilderMethod('json'));
    }

    public function testEnumTypes(): void
    {
        $this->assertEquals("->enum(['active', 'inactive'])", 
            $this->converter->convertToBuilderMethod("enum('active','inactive')"));
        
        $this->assertEquals("->enum(['small', 'medium', 'large'])", 
            $this->converter->convertToBuilderMethod("enum('small','medium','large')"));
        
        // Test with spaces
        $this->assertEquals("->enum(['yes', 'no'])", 
            $this->converter->convertToBuilderMethod("enum('yes', 'no')"));
    }

    public function testSetTypes(): void
    {
        $this->assertEquals("->set(['read', 'write', 'execute'])", 
            $this->converter->convertToBuilderMethod("set('read','write','execute')"));
        
        $this->assertEquals("->set(['admin', 'user'])", 
            $this->converter->convertToBuilderMethod("set('admin','user')"));
    }

    public function testEnumWithEscapedQuotes(): void
    {
        $this->assertEquals("->enum(['it\\'s', 'not'])", 
            $this->converter->convertToBuilderMethod("enum('it''s','not')"));
        
        $this->assertEquals("->enum(['val\\'ue', 'other'])", 
            $this->converter->convertToBuilderMethod("enum('val''ue','other')"));
    }

    public function testGeometryTypes(): void
    {
        $this->assertEquals('->geometry()', $this->converter->convertToBuilderMethod('geometry'));
        $this->assertEquals('->point()', $this->converter->convertToBuilderMethod('point'));
        $this->assertEquals('->lineString()', $this->converter->convertToBuilderMethod('linestring'));
        $this->assertEquals('->polygon()', $this->converter->convertToBuilderMethod('polygon'));
        $this->assertEquals('->multiPoint()', $this->converter->convertToBuilderMethod('multipoint'));
        $this->assertEquals('->multiLineString()', $this->converter->convertToBuilderMethod('multilinestring'));
        $this->assertEquals('->multiPolygon()', $this->converter->convertToBuilderMethod('multipolygon'));
        $this->assertEquals('->geometryCollection()', $this->converter->convertToBuilderMethod('geometrycollection'));
    }

    public function testCaseInsensitiveTypes(): void
    {
        $this->assertEquals('->integer()', $this->converter->convertToBuilderMethod('INT'));
        $this->assertEquals('->string(255)', $this->converter->convertToBuilderMethod('VARCHAR(255)'));
        $this->assertEquals('->text()', $this->converter->convertToBuilderMethod('TEXT'));
        $this->assertEquals('->datetime()', $this->converter->convertToBuilderMethod('DATETIME'));
        $this->assertEquals('->boolean()', $this->converter->convertToBuilderMethod('BOOLEAN'));
    }

    public function testUnknownTypeFallback(): void
    {
        $this->assertEquals('->string()', $this->converter->convertToBuilderMethod('unknown_type'));
        $this->assertEquals('->string()', $this->converter->convertToBuilderMethod('custom_type'));
        $this->assertEquals('->string()', $this->converter->convertToBuilderMethod(''));
    }

    public function testComplexEnumParsing(): void
    {
        // Test enum with various quote types and escaping
        $this->assertEquals("->enum(['option1', 'option2', 'opt\\'ion3'])", 
            $this->converter->convertToBuilderMethod("enum('option1','option2','opt''ion3')"));
    }

    public function testComplexSetParsing(): void
    {
        // Test set with various quote types and escaping
        $this->assertEquals("->set(['perm1', 'perm2', 'perm\\'3'])", 
            $this->converter->convertToBuilderMethod("set('perm1','perm2','perm''3')"));
    }

    public function testEmptyEnumSet(): void
    {
        $this->assertEquals("->enum([])", $this->converter->convertToBuilderMethod("enum()"));
        $this->assertEquals("->set([])", $this->converter->convertToBuilderMethod("set()"));
    }

    public function testIntegerTypesWithVariousCases(): void
    {
        // Test mixed case
        $this->assertEquals('->tinyInt()', $this->converter->convertToBuilderMethod('TinyInt'));
        $this->assertEquals('->smallInt()', $this->converter->convertToBuilderMethod('SmallInt'));
        $this->assertEquals('->mediumInt()', $this->converter->convertToBuilderMethod('MediumInt'));
        $this->assertEquals('->bigInteger()', $this->converter->convertToBuilderMethod('BigInt'));
    }

    public function testUnsignedWithSpaces(): void
    {
        $this->assertEquals('->integer()->unsigned()', $this->converter->convertToBuilderMethod('int(11)  unsigned'));
        $this->assertEquals('->bigInteger()->unsigned()', $this->converter->convertToBuilderMethod('bigint   unsigned'));
    }

    public function testBlobTypesWithCase(): void
    {
        $this->assertEquals('->tinyBlob()', $this->converter->convertToBuilderMethod('TINYBLOB'));
        $this->assertEquals('->blob()', $this->converter->convertToBuilderMethod('BLOB'));
        $this->assertEquals('->mediumBlob()', $this->converter->convertToBuilderMethod('MEDIUMBLOB'));
        $this->assertEquals('->longBlob()', $this->converter->convertToBuilderMethod('LONGBLOB'));
    }

    public function testTextTypesWithCase(): void
    {
        $this->assertEquals('->tinyText()', $this->converter->convertToBuilderMethod('TINYTEXT'));
        $this->assertEquals('->text()', $this->converter->convertToBuilderMethod('TEXT'));
        $this->assertEquals('->mediumText()', $this->converter->convertToBuilderMethod('MEDIUMTEXT'));
        $this->assertEquals('->longText()', $this->converter->convertToBuilderMethod('LONGTEXT'));
    }

    public function testDateTimeTypesWithCase(): void
    {
        $this->assertEquals('->datetime()', $this->converter->convertToBuilderMethod('DATETIME'));
        $this->assertEquals('->timestamp()', $this->converter->convertToBuilderMethod('TIMESTAMP'));
        $this->assertEquals('->date()', $this->converter->convertToBuilderMethod('DATE'));
        $this->assertEquals('->time()', $this->converter->convertToBuilderMethod('TIME'));
        $this->assertEquals('->year()', $this->converter->convertToBuilderMethod('YEAR'));
    }

    public function testGeometryTypesWithCase(): void
    {
        $this->assertEquals('->geometry()', $this->converter->convertToBuilderMethod('GEOMETRY'));
        $this->assertEquals('->point()', $this->converter->convertToBuilderMethod('POINT'));
        $this->assertEquals('->lineString()', $this->converter->convertToBuilderMethod('LINESTRING'));
        $this->assertEquals('->polygon()', $this->converter->convertToBuilderMethod('POLYGON'));
    }

    public function testSpecialCharactersInEnumSet(): void
    {
        // Test enum with special characters
        $result = $this->converter->convertToBuilderMethod("enum('hello world','test123','special-chars')");
        $this->assertEquals("->enum(['hello world', 'test123', 'special-chars'])", $result);
    }

    public function testDoubleQuotesInEnumSet(): void
    {
        // Test with double quotes (should work the same way)
        $result = $this->converter->convertToBuilderMethod('enum("value1","value2")');
        $this->assertEquals("->enum(['value1', 'value2'])", $result);
    }

    public function testMixedQuotesInEnumSet(): void
    {
        // Test with mixed quotes - this might not work perfectly but should handle gracefully
        $result = $this->converter->convertToBuilderMethod("enum('value1',\"value2\")");
        // Should at least not crash and provide some result
        $this->assertIsString($result);
        $this->assertStringContainsString('->enum(', $result);
    }

    public function testRealWorldEnumExamples(): void
    {
        // Test real-world enum examples
        $this->assertEquals("->enum(['pending', 'approved', 'rejected'])", 
            $this->converter->convertToBuilderMethod("enum('pending','approved','rejected')"));
        
        $this->assertEquals("->enum(['XS', 'S', 'M', 'L', 'XL'])", 
            $this->converter->convertToBuilderMethod("enum('XS','S','M','L','XL')"));
    }

    public function testRealWorldSetExamples(): void
    {
        // Test real-world set examples
        $this->assertEquals("->set(['create', 'read', 'update', 'delete'])", 
            $this->converter->convertToBuilderMethod("set('create','read','update','delete')"));
    }
}