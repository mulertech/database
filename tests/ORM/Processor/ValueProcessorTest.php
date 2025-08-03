<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Processor;

use MulerTech\Database\ORM\Processor\ValueProcessor;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class ValueProcessorTest extends TestCase
{
    private ValueProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new ValueProcessor();
    }

    public function testProcessValueWithScalar(): void
    {
        $result = $this->processor->processValue('test string');
        
        self::assertEquals('test string', $result);
    }

    public function testProcessValueWithInteger(): void
    {
        $result = $this->processor->processValue(42);
        
        self::assertEquals(42, $result);
    }

    public function testProcessValueWithFloat(): void
    {
        $result = $this->processor->processValue(3.14);
        
        self::assertEquals(3.14, $result);
    }

    public function testProcessValueWithBoolean(): void
    {
        $result = $this->processor->processValue(true);
        
        self::assertTrue($result);
    }

    public function testProcessValueWithNull(): void
    {
        $result = $this->processor->processValue(null);
        
        self::assertNull($result);
    }

    public function testProcessValueWithArray(): void
    {
        $array = ['a', 'b', 'c'];
        $result = $this->processor->processValue($array);
        
        self::assertEquals($array, $result);
    }

    public function testProcessValueWithEntity(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $result = $this->processor->processValue($user);
        
        self::assertIsArray($result);
        self::assertArrayHasKey('__entity__', $result);
        self::assertArrayHasKey('__id__', $result);
        self::assertArrayHasKey('__hash__', $result);
        self::assertEquals(User::class, $result['__entity__']);
        self::assertEquals(123, $result['__id__']);
        self::assertIsInt($result['__hash__']);
    }

    public function testProcessValueWithEntityNoId(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $result = $this->processor->processValue($user);
        
        self::assertIsArray($result);
        self::assertEquals(User::class, $result['__entity__']);
        self::assertNull($result['__id__']);
        self::assertIsInt($result['__hash__']);
    }

    public function testProcessValueWithCollection(): void
    {
        $user1 = new User();
        $user1->setId(1);
        $user2 = new User();
        $user2->setId(2);
        
        $collection = new DatabaseCollection([$user1, $user2]);
        
        $result = $this->processor->processValue($collection);
        
        self::assertIsArray($result);
        self::assertArrayHasKey('__collection__', $result);
        self::assertArrayHasKey('__items__', $result);
        self::assertTrue($result['__collection__']);
        self::assertCount(2, $result['__items__']);
        
        foreach ($result['__items__'] as $item) {
            self::assertIsArray($item);
            self::assertArrayHasKey('__entity__', $item);
            self::assertArrayHasKey('__id__', $item);
            self::assertArrayHasKey('__hash__', $item);
        }
    }

    public function testProcessValueWithObject(): void
    {
        $object = new \stdClass();
        $object->name = 'test';
        
        $result = $this->processor->processValue($object);
        
        self::assertIsArray($result);
        self::assertArrayHasKey('__object__', $result);
        self::assertArrayHasKey('__hash__', $result);
        self::assertEquals(\stdClass::class, $result['__object__']);
        self::assertIsInt($result['__hash__']);
    }

    public function testGetValueTypeWithScalar(): void
    {
        self::assertEquals('scalar', $this->processor->getValueType('string'));
        self::assertEquals('scalar', $this->processor->getValueType(42));
        self::assertEquals('scalar', $this->processor->getValueType(3.14));
        self::assertEquals('scalar', $this->processor->getValueType(true));
        self::assertEquals('scalar', $this->processor->getValueType(null));
    }

    public function testGetValueTypeWithArray(): void
    {
        self::assertEquals('array', $this->processor->getValueType(['a', 'b', 'c']));
    }

    public function testGetValueTypeWithEntity(): void
    {
        $user = new User();
        
        self::assertEquals('entity', $this->processor->getValueType($user));
    }

    public function testGetValueTypeWithCollection(): void
    {
        $collection = new DatabaseCollection();
        
        self::assertEquals('collection', $this->processor->getValueType($collection));
    }

    public function testGetValueTypeWithObject(): void
    {
        $object = new \stdClass();
        
        self::assertEquals('object', $this->processor->getValueType($object));
    }

    public function testIsEntityWithEntity(): void
    {
        $user = new User();
        $unit = new Unit();
        
        self::assertTrue($this->processor->isEntity($user));
        self::assertTrue($this->processor->isEntity($unit));
    }

    public function testIsEntityWithNonEntity(): void
    {
        $object = new \stdClass();
        $array = [];
        $string = 'test';
        
        self::assertFalse($this->processor->isEntity($object));
        self::assertFalse($this->processor->isEntity($array));
        self::assertFalse($this->processor->isEntity($string));
    }

    public function testIsCollectionWithCollection(): void
    {
        $collection = new DatabaseCollection();
        
        self::assertTrue($this->processor->isCollection($collection));
    }

    public function testIsCollectionWithNonCollection(): void
    {
        $array = [];
        $object = new \stdClass();
        $entity = new User();
        
        self::assertFalse($this->processor->isCollection($array));
        self::assertFalse($this->processor->isCollection($object));
        self::assertFalse($this->processor->isCollection($entity));
    }

    public function testProcessComplexNestedValue(): void
    {
        $user = new User();
        $user->setId(123);
        
        $complexValue = [
            'simple' => 'string',
            'entity' => $user,
            'nested' => [
                'level2' => 'value'
            ]
        ];
        
        $result = $this->processor->processValue($complexValue);
        
        self::assertIsArray($result);
        self::assertEquals('string', $result['simple']);
        self::assertIsArray($result['entity']);
        self::assertEquals(User::class, $result['entity']['__entity__']);
        self::assertIsArray($result['nested']);
        self::assertEquals('value', $result['nested']['level2']);
    }

    public function testProcessValueWithEmptyCollection(): void
    {
        $collection = new DatabaseCollection();
        
        $result = $this->processor->processValue($collection);
        
        self::assertIsArray($result);
        self::assertTrue($result['__collection__']);
        self::assertEmpty($result['__items__']);
    }

    public function testProcessValueConsistency(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $result1 = $this->processor->processValue($user);
        $result2 = $this->processor->processValue($user);
        
        self::assertEquals($result1, $result2);
    }

    public function testGetEntityHash(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $hash1 = $this->processor->getEntityHash($user);
        $hash2 = $this->processor->getEntityHash($user);
        
        self::assertIsInt($hash1);
        self::assertEquals($hash1, $hash2);
    }

    public function testGetObjectHash(): void
    {
        $object = new \stdClass();
        $object->name = 'test';
        
        $hash1 = $this->processor->getObjectHash($object);
        $hash2 = $this->processor->getObjectHash($object);
        
        self::assertIsInt($hash1);
        self::assertEquals($hash1, $hash2);
    }
}