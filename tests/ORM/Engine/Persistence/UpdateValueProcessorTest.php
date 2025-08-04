<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\ORM\Engine\Persistence\UpdateValueProcessor;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class UpdateValueProcessorTest extends TestCase
{
    private UpdateValueProcessor $valueProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->valueProcessor = new UpdateValueProcessor();
    }

    public function testExtractForeignKeyIdFromArray(): void
    {
        $serializedEntity = [
            '__entity__' => 'User',
            '__id__' => 123
        ];
        
        $result = $this->valueProcessor->extractForeignKeyId($serializedEntity);
        
        $this->assertEquals(123, $result);
    }

    public function testExtractForeignKeyIdFromObject(): void
    {
        $user = new User();
        $user->setId(456);
        $user->setUsername('John');
        
        $result = $this->valueProcessor->extractForeignKeyId($user);
        
        $this->assertEquals(456, $result);
    }

    public function testExtractForeignKeyIdFromString(): void
    {
        $stringValue = 'test-id';
        
        $result = $this->valueProcessor->extractForeignKeyId($stringValue);
        
        $this->assertEquals('test-id', $result);
    }

    public function testExtractForeignKeyIdFromNull(): void
    {
        $result = $this->valueProcessor->extractForeignKeyId(null);
        
        $this->assertNull($result);
    }

    public function testExtractForeignKeyIdFromObjectWithoutGetId(): void
    {
        $obj = new \stdClass();
        
        $result = $this->valueProcessor->extractForeignKeyId($obj);
        
        $this->assertNull($result);
    }

    public function testIsValidArrayWithStringKeys(): void
    {
        $validArray = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];
        
        $result = $this->valueProcessor->isValidArrayWithStringKeys($validArray);
        
        $this->assertTrue($result);
    }

    public function testIsValidArrayWithStringKeysInvalid(): void
    {
        $invalidArray = [
            0 => 'value1',
            1 => 'value2'
        ];
        
        $result = $this->valueProcessor->isValidArrayWithStringKeys($invalidArray);
        
        $this->assertFalse($result);
    }

    public function testIsProcessableValueWithArray(): void
    {
        $validArray = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];
        
        $result = $this->valueProcessor->isProcessableValue($validArray);
        
        $this->assertTrue($result);
    }

    public function testIsProcessableValueWithObject(): void
    {
        $user = new User();
        
        $result = $this->valueProcessor->isProcessableValue($user);
        
        $this->assertTrue($result);
    }

    public function testIsProcessableValueWithString(): void
    {
        $result = $this->valueProcessor->isProcessableValue('test');
        
        $this->assertTrue($result);
    }

    public function testIsProcessableValueWithNull(): void
    {
        $result = $this->valueProcessor->isProcessableValue(null);
        
        $this->assertTrue($result);
    }

    public function testIsProcessableValueWithInvalidArray(): void
    {
        $invalidArray = [0, 1, 2];
        
        $result = $this->valueProcessor->isProcessableValue($invalidArray);
        
        $this->assertFalse($result);
    }

    public function testIsProcessableValueWithNumber(): void
    {
        $result = $this->valueProcessor->isProcessableValue(123);
        
        $this->assertFalse($result);
    }

    public function testExtractForeignKeyIdFromComplexArray(): void
    {
        $complexArray = [
            '__entity__' => 'User',
            '__id__' => 'string-id',
            'other' => 'data'
        ];
        
        $result = $this->valueProcessor->extractForeignKeyId($complexArray);
        
        $this->assertEquals('string-id', $result);
    }

    public function testExtractForeignKeyIdFromInvalidArray(): void
    {
        $invalidArray = [
            'normal' => 'array',
            'without' => 'entity_keys'
        ];
        
        $result = $this->valueProcessor->extractForeignKeyId($invalidArray);
        
        $this->assertNull($result);
    }
}