<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\ORM\ValueProcessor\PhpTypeValueProcessor;
use PHPUnit\Framework\TestCase;

class PhpTypeValueProcessorTest extends TestCase
{
    private PhpTypeValueProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new PhpTypeValueProcessor();
    }

    public function testConvertToStringPhpType(): void
    {
        $result = $this->processor->convertToPhpValue(42, 'string');
        
        self::assertEquals('42', $result);
        self::assertIsString($result);
    }

    public function testConvertToIntPhpType(): void
    {
        $result = $this->processor->convertToPhpValue('42', 'int');
        
        self::assertEquals(42, $result);
        self::assertIsInt($result);
    }

    public function testConvertToFloatPhpType(): void
    {
        $result = $this->processor->convertToPhpValue('3.14', 'float');
        
        self::assertEquals(3.14, $result);
        self::assertIsFloat($result);
    }

    public function testConvertToBoolPhpType(): void
    {
        $result = $this->processor->convertToPhpValue('1', 'bool');
        
        self::assertTrue($result);
        self::assertIsBool($result);
        
        $result = $this->processor->convertToPhpValue('0', 'bool');
        
        self::assertFalse($result);
        self::assertIsBool($result);
    }

    public function testConvertToArrayPhpType(): void
    {
        $result = $this->processor->convertToPhpValue('["a","b","c"]', 'array');
        
        self::assertEquals(['a', 'b', 'c'], $result);
        self::assertIsArray($result);
    }

    public function testConvertToObjectPhpType(): void
    {
        $jsonString = '{"name":"John","age":30}';
        $result = $this->processor->convertToPhpValue($jsonString, 'object');
        
        self::assertIsObject($result);
        self::assertEquals('John', $result->name);
        self::assertEquals(30, $result->age);
    }

    public function testConvertToDateTimePhpType(): void
    {
        $result = $this->processor->convertToPhpValue('2023-01-01 12:00:00', 'datetime');
        
        self::assertInstanceOf(\DateTime::class, $result);
        self::assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testConvertToDateTimeImmutablePhpType(): void
    {
        $result = $this->processor->convertToPhpValue('2023-01-01 12:00:00', 'datetime_immutable');
        
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testConvertWithNullValue(): void
    {
        $result = $this->processor->convertToPhpValue(null, 'string');
        
        self::assertNull($result);
    }

    public function testConvertIntegerStringToInt(): void
    {
        $result = $this->processor->convertToPhpValue('123', 'int');
        
        self::assertEquals(123, $result);
        self::assertIsInt($result);
    }

    public function testConvertNegativeIntegerStringToInt(): void
    {
        $result = $this->processor->convertToPhpValue('-456', 'int');
        
        self::assertEquals(-456, $result);
        self::assertIsInt($result);
    }

    public function testConvertFloatStringToFloat(): void
    {
        $result = $this->processor->convertToPhpValue('3.14159', 'float');
        
        self::assertEquals(3.14159, $result);
        self::assertIsFloat($result);
    }

    public function testConvertScientificNotationToFloat(): void
    {
        $result = $this->processor->convertToPhpValue('1.23e-4', 'float');
        
        self::assertEquals(0.000123, $result);
        self::assertIsFloat($result);
    }

    public function testConvertBooleanStringsToBool(): void
    {
        $truthy = ['1', 'true', 'TRUE', 'True', 'yes', 'YES', 'on', 'ON'];
        $falsy = ['0', 'false', 'FALSE', 'False', 'no', 'NO', 'off', 'OFF', ''];
        
        foreach ($truthy as $value) {
            $result = $this->processor->convertToPhpValue($value, 'bool');
            self::assertTrue($result, "Value '$value' should convert to true");
        }
        
        foreach ($falsy as $value) {
            $result = $this->processor->convertToPhpValue($value, 'bool');
            self::assertFalse($result, "Value '$value' should convert to false");
        }
    }

    public function testConvertComplexArrayFromJson(): void
    {
        $jsonString = '{"users":[{"id":1,"name":"John"},{"id":2,"name":"Jane"}],"meta":{"total":2}}';
        $result = $this->processor->convertToPhpValue($jsonString, 'array');
        
        self::assertIsArray($result);
        self::assertArrayHasKey('users', $result);
        self::assertArrayHasKey('meta', $result);
        self::assertCount(2, $result['users']);
        self::assertEquals('John', $result['users'][0]['name']);
        self::assertEquals(2, $result['meta']['total']);
    }

    public function testConvertEmptyStringToArray(): void
    {
        $result = $this->processor->convertToPhpValue('', 'array');
        
        self::assertEquals([], $result);
        self::assertIsArray($result);
    }

    public function testConvertDateWithDifferentFormats(): void
    {
        $formats = [
            '2023-01-01',
            '2023-01-01 12:00:00',
            '2023-01-01T12:00:00Z',
            '01/01/2023',
            'January 1, 2023'
        ];
        
        foreach ($formats as $dateString) {
            $result = $this->processor->convertToPhpValue($dateString, 'datetime');
            self::assertInstanceOf(\DateTime::class, $result, "Failed to convert date: $dateString");
        }
    }

    public function testConvertInvalidJsonToArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string');
        
        $this->processor->convertToPhpValue('{"invalid": json}', 'array');
    }

    public function testConvertInvalidDateToDateTime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format');
        
        $this->processor->convertToPhpValue('invalid-date', 'datetime');
    }

    public function testIsValidType(): void
    {
        $validTypes = ['string', 'int', 'float', 'bool', 'array', 'object', 'datetime', 'datetime_immutable'];
        
        foreach ($validTypes as $type) {
            self::assertTrue($this->processor->isValidType($type), "Type '$type' should be valid");
        }
        
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
        self::assertContains('array', $types);
        self::assertContains('object', $types);
        self::assertContains('datetime', $types);
        self::assertContains('datetime_immutable', $types);
    }

    public function testNormalizeType(): void
    {
        self::assertEquals('string', $this->processor->normalizeType('string'));
        self::assertEquals('int', $this->processor->normalizeType('integer'));
        self::assertEquals('float', $this->processor->normalizeType('double'));
        self::assertEquals('bool', $this->processor->normalizeType('boolean'));
        self::assertEquals('array', $this->processor->normalizeType('array'));
        self::assertEquals('object', $this->processor->normalizeType('stdClass'));
    }

    public function testGetDefaultValue(): void
    {
        self::assertEquals('', $this->processor->getDefaultValue('string'));
        self::assertEquals(0, $this->processor->getDefaultValue('int'));
        self::assertEquals(0.0, $this->processor->getDefaultValue('float'));
        self::assertFalse($this->processor->getDefaultValue('bool'));
        self::assertEquals([], $this->processor->getDefaultValue('array'));
        self::assertInstanceOf(\stdClass::class, $this->processor->getDefaultValue('object'));
        self::assertInstanceOf(\DateTime::class, $this->processor->getDefaultValue('datetime'));
        self::assertInstanceOf(\DateTimeImmutable::class, $this->processor->getDefaultValue('datetime_immutable'));
    }
}