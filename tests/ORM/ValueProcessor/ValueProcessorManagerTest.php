<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\ORM\ValueProcessor\ValueProcessorManager;
use MulerTech\Database\ORM\ValueProcessor\ColumnTypeValueProcessor;
use MulerTech\Database\ORM\ValueProcessor\PhpTypeValueProcessor;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\ORM\ValueProcessor\EntityHydratorInterface;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ValueProcessorManagerTest extends TestCase
{
    private ValueProcessorManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ValueProcessorManager();
    }

    public function testGetColumnTypeProcessor(): void
    {
        $processor = $this->manager->getColumnTypeProcessor();
        
        self::assertInstanceOf(ColumnTypeValueProcessor::class, $processor);
    }

    public function testGetPhpTypeProcessor(): void
    {
        $processor = $this->manager->getPhpTypeProcessor();
        
        self::assertInstanceOf(PhpTypeValueProcessor::class, $processor);
    }

    public function testProcessValueWithString(): void
    {
        // Create mock metadata and use basic type processing
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue('test string', null, $metadata, 'testProperty');

        self::assertEquals('test string', $result);
    }

    public function testProcessValueWithInteger(): void
    {
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue(42, null, $metadata, 'testProperty');

        self::assertEquals(42, $result);
    }

    public function testProcessValueWithFloat(): void
    {
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue(3.14, null, $metadata, 'testProperty');

        self::assertEquals(3.14, $result);
    }

    public function testProcessValueWithBoolean(): void
    {
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue(true, null, $metadata, 'testProperty');

        self::assertTrue($result);
    }

    public function testProcessValueWithNull(): void
    {
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue(null, null, $metadata, 'testProperty');

        self::assertNull($result);
    }

    public function testProcessValueWithArray(): void
    {
        $metadata = $this->createMockMetadata();
        $array = ['a', 'b', 'c'];
        $result = $this->manager->processValue($array, null, $metadata, 'testProperty');

        self::assertEquals($array, $result);
    }

    public function testProcessValueWithObject(): void
    {
        $metadata = $this->createMockMetadata();
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->manager->processValue($user, null, $metadata, 'testProperty');

        self::assertIsArray($result);
        self::assertArrayHasKey('__entity__', $result);
        self::assertEquals(User::class, $result['__entity__']);
    }

    public function testConvertToColumnValue(): void
    {
        $result = $this->manager->convertToColumnValue('test', 'string');
        
        self::assertEquals('test', $result);
    }

    public function testConvertToColumnValueWithInteger(): void
    {
        $result = $this->manager->convertToColumnValue('42', 'int');
        
        self::assertEquals(42, $result);
    }

    public function testConvertToColumnValueWithFloat(): void
    {
        $result = $this->manager->convertToColumnValue('3.14', 'float');
        
        self::assertEquals(3.14, $result);
    }

    public function testConvertToColumnValueWithBoolean(): void
    {
        $result = $this->manager->convertToColumnValue('1', 'bool');
        
        self::assertTrue($result);
        
        $result = $this->manager->convertToColumnValue('0', 'bool');
        
        self::assertFalse($result);
    }

    public function testConvertToPhpValue(): void
    {
        $result = $this->manager->convertToPhpValue('test', 'string');
        
        self::assertEquals('test', $result);
    }

    public function testConvertToPhpValueWithInteger(): void
    {
        $result = $this->manager->convertToPhpValue('42', 'int');
        
        self::assertEquals(42, $result);
    }

    public function testConvertToPhpValueWithFloat(): void
    {
        $result = $this->manager->convertToPhpValue('3.14', 'float');
        
        self::assertEquals(3.14, $result);
    }

    public function testConvertToPhpValueWithBoolean(): void
    {
        $result = $this->manager->convertToPhpValue('1', 'bool');
        
        self::assertTrue($result);
    }

    public function testIsValidType(): void
    {
        self::assertTrue($this->manager->isValidType('string'));
        self::assertTrue($this->manager->isValidType('int'));
        self::assertTrue($this->manager->isValidType('float'));
        self::assertTrue($this->manager->isValidType('bool'));
        self::assertTrue($this->manager->isValidType('array'));
        self::assertFalse($this->manager->isValidType('invalid_type'));
    }

    public function testGetSupportedTypes(): void
    {
        $types = $this->manager->getSupportedTypes();
        
        self::assertIsArray($types);
        self::assertContains('string', $types);
        self::assertContains('int', $types);
        self::assertContains('float', $types);
        self::assertContains('bool', $types);
    }

    public function testNormalizeType(): void
    {
        self::assertEquals('string', $this->manager->normalizeType('string'));
        self::assertEquals('int', $this->manager->normalizeType('integer'));
        self::assertEquals('float', $this->manager->normalizeType('double'));
        self::assertEquals('bool', $this->manager->normalizeType('boolean'));
    }

    public function testValidateValue(): void
    {
        self::assertTrue($this->manager->validateValue('test', 'string'));
        self::assertTrue($this->manager->validateValue(42, 'int'));
        self::assertTrue($this->manager->validateValue(3.14, 'float'));
        self::assertTrue($this->manager->validateValue(true, 'bool'));
        self::assertFalse($this->manager->validateValue('test', 'int'));
    }

    public function testGetDefaultValue(): void
    {
        self::assertEquals('', $this->manager->getDefaultValue('string'));
        self::assertEquals(0, $this->manager->getDefaultValue('int'));
        self::assertEquals(0.0, $this->manager->getDefaultValue('float'));
        self::assertFalse($this->manager->getDefaultValue('bool'));
        self::assertEquals([], $this->manager->getDefaultValue('array'));
    }

    public function testCanConvert(): void
    {
        self::assertTrue($this->manager->canConvert('42', 'int'));
        self::assertTrue($this->manager->canConvert('3.14', 'float'));
        self::assertTrue($this->manager->canConvert('true', 'bool'));
        self::assertFalse($this->manager->canConvert('invalid', 'int'));
    }

    public function testProcessComplexValue(): void
    {
        $metadata = $this->createMockMetadata();
        $user = new User();
        $user->setUsername('John');
        
        $complexValue = [
            'user' => $user,
            'metadata' => ['key' => 'value'],
            'count' => 42
        ];
        
        $result = $this->manager->processValue($complexValue, null, $metadata, 'testProperty');

        self::assertIsArray($result);
        self::assertArrayHasKey('user', $result);
        self::assertArrayHasKey('metadata', $result);
        self::assertArrayHasKey('count', $result);
        self::assertIsArray($result['user']);
        self::assertEquals(User::class, $result['user']['__entity__']);
    }

    public function testProcessorCaching(): void
    {
        $processor1 = $this->manager->getColumnTypeProcessor();
        $processor2 = $this->manager->getColumnTypeProcessor();
        
        self::assertSame($processor1, $processor2);
        
        $processor3 = $this->manager->getPhpTypeProcessor();
        $processor4 = $this->manager->getPhpTypeProcessor();
        
        self::assertSame($processor3, $processor4);
    }

    public function testProcessValueWithProperty(): void
    {
        // Test with ColumnType directly
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue('test', ColumnType::VARCHAR, $metadata, 'testProperty');

        self::assertEquals('test', $result);
    }

    public function testValidateValueWithNull(): void
    {
        // This should trigger the validateValue null echo case
        $result = $this->manager->validateValue(null, 'string');
        
        self::assertTrue($result);
    }

    public function testValidateValueWithArrayAndObjectTypes(): void
    {
        // This should trigger the validateValue array/object types echo case
        $result = $this->manager->validateValue(['test'], 'array');
        self::assertTrue($result);
        
        $result = $this->manager->validateValue(new \stdClass(), 'object');
        self::assertTrue($result);
    }

    public function testCanConvertWithNull(): void
    {
        // This should trigger the first canConvert null echo case
        $result = $this->manager->canConvert(null, 'string');
        
        self::assertTrue($result);
    }

    public function testCanConvertWithSameType(): void
    {
        // This should trigger the canConvert same type echo case
        $result = $this->manager->canConvert('test', 'string');
        
        self::assertTrue($result);
    }

    public function testCanConvertWithStringArrayObjectConversions(): void
    {
        // This should trigger the canConvert string/array/object conversions echo case
        
        // String conversion (almost anything can be converted to string)
        $result = $this->manager->canConvert(123, 'string');
        self::assertTrue($result);
        
        // Array conversion
        $result = $this->manager->canConvert('{"key":"value"}', 'array');
        self::assertTrue($result);
        
        $result = $this->manager->canConvert(new \stdClass(), 'array');
        self::assertTrue($result);
        
        // Object conversion
        $result = $this->manager->canConvert(['key' => 'value'], 'object');
        self::assertTrue($result);
        
        $result = $this->manager->canConvert('{"key":"value"}', 'object');
        self::assertTrue($result);
    }

    public function testProcessValueWithPropertyAndHydrator(): void
    {
        // Create metadata with ColumnType
        $metadata = new EntityMetadata(
            className: User::class,
            tableName: 'users',
            columns: ['username' => new MtColumn(columnName: 'username', columnType: ColumnType::VARCHAR)]
        );

        $result = $this->manager->processValue('test', null, $metadata, 'username');

        self::assertEquals('test', $result);
    }

    public function testProcessValueWithPropertyAndHydratorNullColumnType(): void
    {
        // Create metadata with no columns (getColumnType returns null)
        $metadata = new EntityMetadata(
            className: User::class,
            tableName: 'users',
            columns: [] // No columns defined
        );

        $result = $this->manager->processValue('test', null, $metadata, 'nonExistentProperty');

        self::assertEquals('test', $result);
    }

    public function testProcessValueWithNonExistentClass(): void
    {
        // Test basic type processing fallback
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue('test', null, $metadata, 'someProperty');

        self::assertEquals('test', $result);
    }

    public function testProcessValueWithPropertyAndHydratorPhpTypeRoute(): void
    {
        // Test with DateTime object (should trigger basic type processing)
        $metadata = new EntityMetadata(
            className: User::class,
            tableName: 'users',
            columns: [] // No columns so getColumnType returns null
        );

        $dateTime = new \DateTime('2023-01-01 12:00:00');
        $result = $this->manager->processValue($dateTime, null, $metadata, 'username');

        // Should process the object and add __entity__ key
        self::assertIsArray($result);
        self::assertArrayHasKey('__entity__', $result);
        self::assertEquals(\DateTime::class, $result['__entity__']);
    }

    public function testProcessValueWithPropertyAndHydratorNoTypeInfo(): void
    {
        // Test basic type processing when no ColumnType is available
        $metadata = new EntityMetadata(
            className: User::class,
            tableName: 'users',
            columns: [] // No columns so getColumnType returns null
        );

        $result = $this->manager->processValue('test', null, $metadata, 'username');

        self::assertEquals('test', $result);
    }

    public function testProcessValueWithNonBuiltinType(): void
    {
        // Test with ColumnType for DateTime processing
        $metadata = $this->createMockMetadata();
        $result = $this->manager->processValue('2023-01-01 12:00:00', ColumnType::DATETIME, $metadata, 'testProperty');

        // ColumnTypeValueProcessor converts DATETIME to date string format, not DateTime object
        self::assertIsString($result);
        self::assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result);
    }

    /**
     * Create a mock EntityMetadata for simple tests
     *
     * @return EntityMetadata
     */
    private function createMockMetadata(): EntityMetadata
    {
        return new EntityMetadata(
            className: User::class,
            tableName: 'users',
            columns: []
        );
    }
}

