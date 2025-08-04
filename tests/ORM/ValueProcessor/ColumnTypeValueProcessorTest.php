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
}