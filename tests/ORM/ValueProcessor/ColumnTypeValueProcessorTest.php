<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\ORM\ValueProcessor\ColumnTypeValueProcessor;
use PHPUnit\Framework\TestCase;

class ColumnTypeValueProcessorTest extends TestCase
{
    private ColumnTypeValueProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ColumnTypeValueProcessor();
    }

    public function testConvertToStringColumn(): void
    {
        $result = $this->processor->convertToColumnValue('test', 'string');
        
        self::assertEquals('test', $result);
        self::assertIsString($result);
    }

    public function testConvertToIntColumn(): void
    {
        $result = $this->processor->convertToColumnValue('42', 'int');
        
        self::assertEquals(42, $result);
        self::assertIsInt($result);
    }

    public function testConvertToFloatColumn(): void
    {
        $result = $this->processor->convertToColumnValue('3.14', 'float');
        
        self::assertEquals(3.14, $result);
        self::assertIsFloat($result);
    }

    public function testConvertToBoolColumn(): void
    {
        $result = $this->processor->convertToColumnValue('1', 'bool');
        
        self::assertTrue($result);
        self::assertIsBool($result);
        
        $result = $this->processor->convertToColumnValue('0', 'bool');
        
        self::assertFalse($result);
        self::assertIsBool($result);
    }

    public function testConvertToDateColumn(): void
    {
        $result = $this->processor->convertToColumnValue('2023-01-01', 'date');
        
        self::assertEquals('2023-01-01', $result);
        self::assertIsString($result);
    }

    public function testConvertToDateTimeColumn(): void
    {
        $result = $this->processor->convertToColumnValue('2023-01-01 12:00:00', 'datetime');
        
        self::assertEquals('2023-01-01 12:00:00', $result);
        self::assertIsString($result);
    }

    public function testConvertToTimeColumn(): void
    {
        $result = $this->processor->convertToColumnValue('12:00:00', 'time');
        
        self::assertEquals('12:00:00', $result);
        self::assertIsString($result);
    }

    public function testConvertToTimestampColumn(): void
    {
        $result = $this->processor->convertToColumnValue('2023-01-01 12:00:00', 'timestamp');
        
        self::assertEquals('2023-01-01 12:00:00', $result);
        self::assertIsString($result);
    }

    public function testConvertToJsonColumn(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $result = $this->processor->convertToColumnValue($data, 'json');
        
        self::assertEquals('{"key":"value","number":42}', $result);
        self::assertIsString($result);
    }

    public function testConvertToTextColumn(): void
    {
        $result = $this->processor->convertToColumnValue('Long text content', 'text');
        
        self::assertEquals('Long text content', $result);
        self::assertIsString($result);
    }

    public function testConvertToBinaryColumn(): void
    {
        $result = $this->processor->convertToColumnValue('binary data', 'binary');
        
        self::assertEquals('binary data', $result);
        self::assertIsString($result);
    }

    public function testConvertWithNullValue(): void
    {
        $result = $this->processor->convertToColumnValue(null, 'string');
        
        self::assertNull($result);
    }

    public function testConvertWithEmptyString(): void
    {
        $result = $this->processor->convertToColumnValue('', 'string');
        
        self::assertEquals('', $result);
        self::assertIsString($result);
    }

    public function testConvertWithZeroValue(): void
    {
        $result = $this->processor->convertToColumnValue(0, 'int');
        
        self::assertEquals(0, $result);
        self::assertIsInt($result);
    }

    public function testConvertBooleanStringToInt(): void
    {
        $result = $this->processor->convertToColumnValue('true', 'int');
        
        self::assertEquals(1, $result);
        self::assertIsInt($result);
        
        $result = $this->processor->convertToColumnValue('false', 'int');
        
        self::assertEquals(0, $result);
        self::assertIsInt($result);
    }

    public function testConvertArrayToJson(): void
    {
        $data = ['users' => [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']]];
        $result = $this->processor->convertToColumnValue($data, 'json');
        
        self::assertIsString($result);
        self::assertJson($result);
        
        $decoded = json_decode($result, true);
        self::assertEquals($data, $decoded);
    }

    public function testConvertNestedArrayToJson(): void
    {
        $data = [
            'config' => [
                'database' => ['host' => 'localhost', 'port' => 3306],
                'cache' => ['enabled' => true, 'ttl' => 3600]
            ]
        ];
        
        $result = $this->processor->convertToColumnValue($data, 'json');
        
        self::assertIsString($result);
        self::assertJson($result);
        
        $decoded = json_decode($result, true);
        self::assertEquals($data, $decoded);
    }

    public function testConvertFloatStringToFloat(): void
    {
        $result = $this->processor->convertToColumnValue('3.14159', 'float');
        
        self::assertEquals(3.14159, $result);
        self::assertIsFloat($result);
    }

    public function testConvertScientificNotationToFloat(): void
    {
        $result = $this->processor->convertToColumnValue('1.23e-4', 'float');
        
        self::assertEquals(0.000123, $result);
        self::assertIsFloat($result);
    }

    public function testConvertLargeIntegerToString(): void
    {
        $largeNumber = '9223372036854775808'; // Larger than PHP_INT_MAX
        $result = $this->processor->convertToColumnValue($largeNumber, 'string');
        
        self::assertEquals($largeNumber, $result);
        self::assertIsString($result);
    }

    public function testIsValidType(): void
    {
        self::assertTrue($this->processor->isValidType('string'));
        self::assertTrue($this->processor->isValidType('int'));
        self::assertTrue($this->processor->isValidType('float'));
        self::assertTrue($this->processor->isValidType('bool'));
        self::assertTrue($this->processor->isValidType('date'));
        self::assertTrue($this->processor->isValidType('datetime'));
        self::assertTrue($this->processor->isValidType('time'));
        self::assertTrue($this->processor->isValidType('timestamp'));
        self::assertTrue($this->processor->isValidType('json'));
        self::assertTrue($this->processor->isValidType('text'));
        self::assertTrue($this->processor->isValidType('binary'));
        
        self::assertFalse($this->processor->isValidType('invalid_type'));
        self::assertFalse($this->processor->isValidType(''));
        self::assertFalse($this->processor->isValidType('unknown'));
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->processor->getSupportedTypes();
        
        self::assertIsArray($types);
        self::assertContains('string', $types);
        self::assertContains('int', $types);
        self::assertContains('float', $types);
        self::assertContains('bool', $types);
        self::assertContains('date', $types);
        self::assertContains('datetime', $types);
        self::assertContains('time', $types);
        self::assertContains('timestamp', $types);
        self::assertContains('json', $types);
        self::assertContains('text', $types);
        self::assertContains('binary', $types);
    }

    public function testConvertInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON data');
        
        $resource = fopen('php://memory', 'r');
        $this->processor->convertToColumnValue($resource, 'json');
        fclose($resource);
    }

    public function testConvertInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported column type: invalid_type');
        
        $this->processor->convertToColumnValue('test', 'invalid_type');
    }

    public function testCanProcess(): void
    {
        $processor = new ColumnTypeValueProcessor();
        
        self::assertTrue($processor->canProcess(\MulerTech\Database\Mapping\Types\ColumnType::INT));
        self::assertTrue($processor->canProcess(\MulerTech\Database\Mapping\Types\ColumnType::VARCHAR));
        self::assertFalse($processor->canProcess('string'));
        self::assertFalse($processor->canProcess(123));
        self::assertFalse($processor->canProcess([]));
        self::assertFalse($processor->canProcess(null));
    }

    public function testProcessWithColumnType(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::INT);
        
        $result = $processor->process('42');
        self::assertEquals(42, $result);
        self::assertIsInt($result);
    }

    public function testProcessWithNullValue(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::INT);
        
        $result = $processor->process(null);
        self::assertNull($result);
    }

    public function testProcessWithoutColumnType(): void
    {
        $processor = new ColumnTypeValueProcessor();
        
        $result = $processor->process('test');
        self::assertEquals('test', $result);
    }

    public function testProcessIntegerTypes(): void
    {
        $intTypes = [
            \MulerTech\Database\Mapping\Types\ColumnType::INT,
            \MulerTech\Database\Mapping\Types\ColumnType::SMALLINT,
            \MulerTech\Database\Mapping\Types\ColumnType::MEDIUMINT,
            \MulerTech\Database\Mapping\Types\ColumnType::BIGINT,
            \MulerTech\Database\Mapping\Types\ColumnType::YEAR,
        ];

        foreach ($intTypes as $type) {
            $processor = new ColumnTypeValueProcessor($type);
            $result = $processor->process('42');
            self::assertEquals(42, $result);
            self::assertIsInt($result);
        }
    }

    public function testProcessTinyIntAsBool(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::TINYINT);
        
        $result = $processor->process('1');
        self::assertTrue($result);
        
        $result = $processor->process('0');
        self::assertFalse($result);
    }

    public function testProcessFloatTypes(): void
    {
        $floatTypes = [
            \MulerTech\Database\Mapping\Types\ColumnType::DECIMAL,
            \MulerTech\Database\Mapping\Types\ColumnType::FLOAT,
            \MulerTech\Database\Mapping\Types\ColumnType::DOUBLE,
        ];

        foreach ($floatTypes as $type) {
            $processor = new ColumnTypeValueProcessor($type);
            $result = $processor->process('3.14');
            self::assertEquals(3.14, $result);
            self::assertIsFloat($result);
        }
    }

    public function testProcessStringTypes(): void
    {
        $stringTypes = [
            \MulerTech\Database\Mapping\Types\ColumnType::VARCHAR,
            \MulerTech\Database\Mapping\Types\ColumnType::CHAR,
            \MulerTech\Database\Mapping\Types\ColumnType::TEXT,
            \MulerTech\Database\Mapping\Types\ColumnType::TINYTEXT,
            \MulerTech\Database\Mapping\Types\ColumnType::MEDIUMTEXT,
            \MulerTech\Database\Mapping\Types\ColumnType::LONGTEXT,
            \MulerTech\Database\Mapping\Types\ColumnType::ENUM,
            \MulerTech\Database\Mapping\Types\ColumnType::SET,
            \MulerTech\Database\Mapping\Types\ColumnType::TIME,
        ];

        foreach ($stringTypes as $type) {
            $processor = new ColumnTypeValueProcessor($type);
            $result = $processor->process(123);
            self::assertEquals('123', $result);
            self::assertIsString($result);
        }
    }

    public function testProcessDateTypes(): void
    {
        $dateTypes = [
            \MulerTech\Database\Mapping\Types\ColumnType::DATE,
            \MulerTech\Database\Mapping\Types\ColumnType::DATETIME,
            \MulerTech\Database\Mapping\Types\ColumnType::TIMESTAMP,
        ];

        foreach ($dateTypes as $type) {
            $processor = new ColumnTypeValueProcessor($type);
            $result = $processor->process(new \DateTime('2023-01-01 12:00:00'));
            self::assertEquals('2023-01-01 12:00:00', $result);
            self::assertIsString($result);
        }
    }

    public function testProcessJsonType(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::JSON);
        
        $result = $processor->process(['key' => 'value']);
        self::assertEquals(['key' => 'value'], $result);
        
        $result = $processor->process('{"key":"value"}');
        self::assertEquals(['key' => 'value'], $result);
        
        $result = $processor->process('invalid json');
        self::assertEquals([], $result);
    }

    public function testProcessBinaryTypes(): void
    {
        $binaryTypes = [
            \MulerTech\Database\Mapping\Types\ColumnType::BINARY,
            \MulerTech\Database\Mapping\Types\ColumnType::VARBINARY,
            \MulerTech\Database\Mapping\Types\ColumnType::BLOB,
            \MulerTech\Database\Mapping\Types\ColumnType::TINYBLOB,
            \MulerTech\Database\Mapping\Types\ColumnType::MEDIUMBLOB,
            \MulerTech\Database\Mapping\Types\ColumnType::LONGBLOB,
        ];

        foreach ($binaryTypes as $type) {
            $processor = new ColumnTypeValueProcessor($type);
            $result = $processor->process(123);
            self::assertEquals('123', $result);
            self::assertIsString($result);
        }
    }

    public function testConvertToPhpValue(): void
    {
        self::assertEquals('test', $this->processor->convertToPhpValue('test', 'string'));
        self::assertEquals(42, $this->processor->convertToPhpValue('42', 'int'));
        self::assertEquals(3.14, $this->processor->convertToPhpValue('3.14', 'float'));
        self::assertTrue($this->processor->convertToPhpValue('1', 'bool'));
        self::assertEquals('2023-01-01', $this->processor->convertToPhpValue('2023-01-01', 'date'));
        self::assertEquals('{"key":"value"}', $this->processor->convertToPhpValue(['key' => 'value'], 'json'));
        self::assertEquals('binary', $this->processor->convertToPhpValue('binary', 'binary'));
    }

    public function testConvertToPhpValueWithNull(): void
    {
        $result = $this->processor->convertToPhpValue(null, 'string');
        self::assertNull($result);
    }

    public function testConvertToPhpValueUnsupportedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported column type: invalid');
        
        $this->processor->convertToPhpValue('test', 'invalid');
    }

    public function testGetDefaultValue(): void
    {
        self::assertEquals('', $this->processor->getDefaultValue('string'));
        self::assertEquals('', $this->processor->getDefaultValue('varchar'));
        self::assertEquals('', $this->processor->getDefaultValue('char'));
        self::assertEquals('', $this->processor->getDefaultValue('text'));
        self::assertEquals('', $this->processor->getDefaultValue('binary'));
        
        self::assertEquals(0, $this->processor->getDefaultValue('int'));
        self::assertEquals(0, $this->processor->getDefaultValue('smallint'));
        self::assertEquals(0, $this->processor->getDefaultValue('bigint'));
        self::assertEquals(0, $this->processor->getDefaultValue('year'));
        
        self::assertEquals(0.0, $this->processor->getDefaultValue('float'));
        self::assertEquals(0.0, $this->processor->getDefaultValue('double'));
        self::assertEquals(0.0, $this->processor->getDefaultValue('decimal'));
        
        self::assertFalse($this->processor->getDefaultValue('bool'));
        self::assertFalse($this->processor->getDefaultValue('tinyint'));
        
        self::assertEquals('1970-01-01', $this->processor->getDefaultValue('date'));
        self::assertEquals('1970-01-01 00:00:00', $this->processor->getDefaultValue('datetime'));
        self::assertEquals('1970-01-01 00:00:00', $this->processor->getDefaultValue('timestamp'));
        self::assertEquals('00:00:00', $this->processor->getDefaultValue('time'));
        
        self::assertEquals('{}', $this->processor->getDefaultValue('json'));
        
        self::assertNull($this->processor->getDefaultValue('unknown'));
    }

    public function testNormalizeType(): void
    {
        self::assertEquals('int', $this->processor->normalizeType('integer'));
        self::assertEquals('int', $this->processor->normalizeType('smallint'));
        self::assertEquals('int', $this->processor->normalizeType('mediumint'));
        self::assertEquals('int', $this->processor->normalizeType('bigint'));
        
        self::assertEquals('float', $this->processor->normalizeType('double'));
        self::assertEquals('float', $this->processor->normalizeType('decimal'));
        
        self::assertEquals('bool', $this->processor->normalizeType('boolean'));
        self::assertEquals('bool', $this->processor->normalizeType('tinyint'));
        
        self::assertEquals('string', $this->processor->normalizeType('varchar'));
        self::assertEquals('string', $this->processor->normalizeType('char'));
        
        self::assertEquals('text', $this->processor->normalizeType('tinytext'));
        self::assertEquals('text', $this->processor->normalizeType('mediumtext'));
        self::assertEquals('text', $this->processor->normalizeType('longtext'));
        
        self::assertEquals('binary', $this->processor->normalizeType('varbinary'));
        
        self::assertEquals('blob', $this->processor->normalizeType('tinyblob'));
        self::assertEquals('blob', $this->processor->normalizeType('mediumblob'));
        self::assertEquals('blob', $this->processor->normalizeType('longblob'));
        
        self::assertEquals('unknown', $this->processor->normalizeType('unknown'));
    }

    public function testConvertToIntWithVariousInputs(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::INT);
        
        self::assertEquals(42, $processor->process(42));
        self::assertEquals(42, $processor->process('42'));
        self::assertEquals(42, $processor->process(42.9));
        self::assertEquals(1, $processor->process(true));
        self::assertEquals(0, $processor->process(false));
        self::assertEquals(1, $processor->process('true'));
        self::assertEquals(0, $processor->process('false'));
        self::assertEquals(0, $processor->process('invalid'));
    }

    public function testConvertToFloatWithVariousInputs(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::FLOAT);
        
        self::assertEquals(42.0, $processor->process(42));
        self::assertEquals(42.5, $processor->process('42.5'));
        self::assertEquals(42.9, $processor->process(42.9));
        self::assertEquals(0.0, $processor->process('invalid'));
    }

    public function testConvertToBoolWithVariousInputs(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::TINYINT);
        
        self::assertTrue($processor->process(true));
        self::assertFalse($processor->process(false));
        self::assertTrue($processor->process(1));
        self::assertFalse($processor->process(0));
        self::assertTrue($processor->process('yes'));
        self::assertFalse($processor->process('no'));
        self::assertTrue($processor->process('on'));
        self::assertFalse($processor->process('off'));
        self::assertTrue($processor->process('1'));
        self::assertFalse($processor->process('0'));
        self::assertFalse($processor->process(''));
        self::assertFalse($processor->process('anything')); // 'anything' is not in the true list, so returns false
    }

    public function testConvertToDateStringWithDateTime(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::DATETIME);
        
        $date = new \DateTime('2023-01-01 12:00:00');
        $result = $processor->process($date);
        
        self::assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testConvertToDateStringWithDateTimeImmutable(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::DATETIME);
        
        $date = new \DateTimeImmutable('2023-01-01 12:00:00');
        $result = $processor->process($date);
        
        self::assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testConvertToDateStringWithString(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::DATETIME);
        
        $result = $processor->process('2023-01-01 12:00:00');
        
        self::assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testConvertToDateStringWithNonDateTime(): void
    {
        $processor = new ColumnTypeValueProcessor(\MulerTech\Database\Mapping\Types\ColumnType::DATETIME);
        
        $result = $processor->process(123);
        
        self::assertIsString($result);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function testConvertJsonStringWithValidJson(): void
    {
        $result = $this->processor->convertToColumnValue('{"key":"value"}', 'json');
        
        self::assertEquals('{"key":"value"}', $result);
    }

    public function testConvertJsonStringWithObject(): void
    {
        $obj = (object)['key' => 'value'];
        $result = $this->processor->convertToColumnValue($obj, 'json');
        
        self::assertEquals('{"key":"value"}', $result);
    }

    public function testConvertAllColumnTypeAliases(): void
    {
        $aliases = [
            'varchar' => 'string',
            'char' => 'string', 
            'text' => 'text',
            'tinytext' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'integer' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'bigint' => 'int',
            // 'year' => 'year', // year is not normalized, it stays as 'year'
            'double' => 'float',
            'decimal' => 'float',
            'boolean' => 'bool',
            'tinyint' => 'bool',
            'binary' => 'binary',
            'varbinary' => 'binary',
            'blob' => 'blob',
            'tinyblob' => 'blob',
            'mediumblob' => 'blob',
            'longblob' => 'blob',
        ];

        foreach ($aliases as $alias => $expected) {
            self::assertEquals($expected, $this->processor->normalizeType($alias));
        }
    }
}