<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\ValueProcessor;

use MulerTech\Database\ORM\ValueProcessor\ValueProcessorManager;
use MulerTech\Database\ORM\ValueProcessor\ColumnTypeValueProcessor;
use MulerTech\Database\ORM\ValueProcessor\PhpTypeValueProcessor;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

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
        $result = $this->manager->processValue('test string');
        
        self::assertEquals('test string', $result);
    }

    public function testProcessValueWithInteger(): void
    {
        $result = $this->manager->processValue(42);
        
        self::assertEquals(42, $result);
    }

    public function testProcessValueWithFloat(): void
    {
        $result = $this->manager->processValue(3.14);
        
        self::assertEquals(3.14, $result);
    }

    public function testProcessValueWithBoolean(): void
    {
        $result = $this->manager->processValue(true);
        
        self::assertTrue($result);
    }

    public function testProcessValueWithNull(): void
    {
        $result = $this->manager->processValue(null);
        
        self::assertNull($result);
    }

    public function testProcessValueWithArray(): void
    {
        $array = ['a', 'b', 'c'];
        $result = $this->manager->processValue($array);
        
        self::assertEquals($array, $result);
    }

    public function testProcessValueWithObject(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->manager->processValue($user);
        
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
        $user = new User();
        $user->setUsername('John');
        
        $complexValue = [
            'user' => $user,
            'metadata' => ['key' => 'value'],
            'count' => 42
        ];
        
        $result = $this->manager->processValue($complexValue);
        
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
}