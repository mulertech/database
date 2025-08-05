<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use DateMalformedStringException;
use InvalidArgumentException;
use JsonException;
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

    /**
     * @throws DateMalformedStringException
     * @throws JsonException
     */
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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string');
        
        $this->processor->convertToPhpValue('{"invalid": json}', 'array');
    }

    public function testConvertInvalidDateToDateTime(): void
    {
        $this->expectException(InvalidArgumentException::class);
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
        self::assertNull($this->processor->getDefaultValue('unknown'));
    }

    public function testCanProcess(): void
    {
        $processor = new PhpTypeValueProcessor();
        
        self::assertTrue($processor->canProcess(\DateTime::class));
        self::assertTrue($processor->canProcess(\DateTimeImmutable::class));
        self::assertTrue($processor->canProcess(\stdClass::class));
        self::assertFalse($processor->canProcess('non_existent_class'));
        self::assertFalse($processor->canProcess(123));
        self::assertFalse($processor->canProcess([]));
        self::assertFalse($processor->canProcess(null));
    }

    public function testProcessWithClassName(): void
    {
        $processor = new PhpTypeValueProcessor('string');
        
        $result = $processor->process(123);
        self::assertEquals('123', $result);
        self::assertIsString($result);
    }

    public function testProcessWithNullValue(): void
    {
        $processor = new PhpTypeValueProcessor('string');
        
        $result = $processor->process(null);
        self::assertNull($result);
    }

    public function testProcessWithIntType(): void
    {
        $processor = new PhpTypeValueProcessor('int');
        
        $result = $processor->process('42');
        self::assertEquals(42, $result);
        self::assertIsInt($result);
    }

    public function testProcessWithFloatType(): void
    {
        $processor = new PhpTypeValueProcessor('float');
        
        $result = $processor->process('3.14');
        self::assertEquals(3.14, $result);
        self::assertIsFloat($result);
    }

    public function testProcessWithBoolType(): void
    {
        $processor = new PhpTypeValueProcessor('bool');
        
        $result = $processor->process('true');
        self::assertTrue($result);
        
        $result = $processor->process('false');
        self::assertFalse($result);
    }

    public function testProcessWithArrayType(): void
    {
        $processor = new PhpTypeValueProcessor('array');
        
        $result = $processor->process('["a","b","c"]');
        self::assertEquals(['a', 'b', 'c'], $result);
        self::assertIsArray($result);
    }

    public function testProcessWithObjectType(): void
    {
        $processor = new PhpTypeValueProcessor('object');
        
        $result = $processor->process('{"name":"John"}');
        self::assertIsObject($result);
        self::assertEquals('John', $result->name);
    }

    public function testProcessWithDateTimeType(): void
    {
        $processor = new PhpTypeValueProcessor(\DateTime::class);
        
        $result = $processor->process('2023-01-01 12:00:00');
        self::assertInstanceOf(\DateTime::class, $result);
        self::assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testProcessWithDateTimeImmutableType(): void
    {
        $processor = new PhpTypeValueProcessor(\DateTimeImmutable::class);
        
        $result = $processor->process('2023-01-01 12:00:00');
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testProcessBasicPhpType(): void
    {
        $processor = new PhpTypeValueProcessor();
        
        self::assertEquals('test', $processor->process('test'));
        self::assertEquals(42, $processor->process(42));
        self::assertEquals(3.14, $processor->process(3.14));
        self::assertTrue($processor->process(true));
        self::assertEquals(['a', 'b'], $processor->process(['a', 'b']));
    }

    public function testConvertToColumnValueWithNull(): void
    {
        $result = $this->processor->convertToColumnValue(null, 'string');
        self::assertNull($result);
    }

    public function testConvertToColumnValueWithScalar(): void
    {
        self::assertEquals('test', $this->processor->convertToColumnValue('test', 'string'));
        self::assertEquals(42, $this->processor->convertToColumnValue(42, 'int'));
        self::assertEquals(3.14, $this->processor->convertToColumnValue(3.14, 'float'));
    }

    public function testConvertToColumnValueWithBoolean(): void
    {
        self::assertEquals(1, $this->processor->convertToColumnValue(true, 'bool'));
        self::assertEquals(0, $this->processor->convertToColumnValue(false, 'bool'));
    }

    public function testConvertToColumnValueWithArray(): void
    {
        $array = ['a', 'b', 'c'];
        $result = $this->processor->convertToColumnValue($array, 'array');
        
        self::assertEquals('["a","b","c"]', $result);
    }

    public function testConvertToColumnValueWithObject(): void
    {
        $object = (object)['name' => 'John', 'age' => 30];
        $result = $this->processor->convertToColumnValue($object, 'object');
        
        self::assertEquals('{"name":"John","age":30}', $result);
    }

    public function testConvertToColumnValueWithDateTime(): void
    {
        $date = new \DateTime('2023-01-01 12:00:00');
        $result = $this->processor->convertToColumnValue($date, 'datetime');
        
        self::assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testConvertToColumnValueWithDateTimeImmutable(): void
    {
        $date = new \DateTimeImmutable('2023-01-01 12:00:00');
        $result = $this->processor->convertToColumnValue($date, 'datetime_immutable');
        
        self::assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testProcessStringWithDifferentTypes(): void
    {
        $processor = new PhpTypeValueProcessor('string');
        
        self::assertEquals('', $processor->process(null));
        self::assertEquals('42', $processor->process(42));
        self::assertEquals('3.14', $processor->process(3.14));
        self::assertEquals('1', $processor->process(true));
        self::assertEquals('["a","b"]', $processor->process(['a', 'b']));
    }

    public function testProcessIntWithDifferentTypes(): void
    {
        $processor = new PhpTypeValueProcessor('int');
        
        self::assertEquals(42, $processor->process(42));
        self::assertEquals(42, $processor->process('42'));
        self::assertEquals(42, $processor->process(42.9));
        self::assertEquals(1, $processor->process(true));
        self::assertEquals(0, $processor->process(false));
        self::assertEquals(0, $processor->process('non-numeric'));
    }

    public function testProcessFloatWithDifferentTypes(): void
    {
        $processor = new PhpTypeValueProcessor('float');
        
        self::assertEquals(42.0, $processor->process(42));
        self::assertEquals(42.5, $processor->process('42.5'));
        self::assertEquals(42.9, $processor->process(42.9));
        self::assertEquals(0.0, $processor->process('non-numeric'));
    }

    public function testProcessBoolWithDifferentTypes(): void
    {
        $processor = new PhpTypeValueProcessor('bool');
        
        self::assertTrue($processor->process(true));
        self::assertFalse($processor->process(false));
        self::assertTrue($processor->process(1));
        self::assertFalse($processor->process(0));
        self::assertTrue($processor->process('yes'));
        self::assertFalse($processor->process('no'));
        self::assertTrue($processor->process('on'));
        self::assertFalse($processor->process('off'));
        self::assertFalse($processor->process('anything_else'));
    }

    /**
     * @throws JsonException
     * @throws DateMalformedStringException
     */
    public function testProcessArrayWithDifferentTypes(): void
    {
        $processor = new PhpTypeValueProcessor('array');
        
        self::assertEquals(['a', 'b'], $processor->process(['a', 'b']));
        self::assertEquals([], $processor->process(''));
        self::assertEquals(['a', 'b'], $processor->process('["a","b"]'));
        self::assertEquals(['name' => 'John'], $processor->process((object)['name' => 'John']));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON string');
        $processor->process('test');
    }

    public function testProcessObjectWithDifferentTypes(): void
    {
        $processor = new PhpTypeValueProcessor('object');
        
        $obj = (object)['name' => 'John'];
        self::assertEquals($obj, $processor->process($obj));
        self::assertEquals((object)['a' => 1], $processor->process(['a' => 1]));
        
        $result = $processor->process('{"name":"John"}');
        self::assertEquals('John', $result->name);
        
        $result = $processor->process('invalid json');
        self::assertEquals('invalid json', $result->value);
    }

    public function testProcessCustomClass(): void
    {
        $processor = new PhpTypeValueProcessor(\stdClass::class);
        
        $obj = new \stdClass();
        self::assertSame($obj, $processor->process($obj));
        
        $result = $processor->process('anything');
        self::assertInstanceOf(\stdClass::class, $result);
    }

    public function testProcessCustomClassWithHydrateCallback(): void
    {
        $callback = function ($data, $className) {
            $obj = new $className();
            if (is_array($data) && isset($data['name'])) {
                $obj->name = $data['name'];
            }
            return $obj;
        };
        
        $processor = new PhpTypeValueProcessor(\stdClass::class, $callback);
        
        $result = $processor->process(['name' => 'John']);
        self::assertInstanceOf(\stdClass::class, $result);
        self::assertEquals('John', $result->name);
    }

    public function testProcessDateTimeWithDifferentInputs(): void
    {
        $processor = new PhpTypeValueProcessor(\DateTime::class);
        
        $date = new \DateTime('2023-01-01');
        self::assertSame($date, $processor->process($date));
        
        $immutable = new \DateTimeImmutable('2023-01-01 12:00:00');
        $result = $processor->process($immutable);
        self::assertInstanceOf(\DateTime::class, $result);
        self::assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
        
        $result = $processor->process(null);
        self::assertNull($result);
    }

    public function testProcessDateTimeImmutableWithDifferentInputs(): void
    {
        $processor = new PhpTypeValueProcessor(\DateTimeImmutable::class);
        
        $date = new \DateTimeImmutable('2023-01-01');
        self::assertSame($date, $processor->process($date));
        
        $mutable = new \DateTime('2023-01-01 12:00:00');
        $result = $processor->process($mutable);
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
        
        $result = $processor->process(null);
        self::assertNull($result);
    }
}