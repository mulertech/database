<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Validator;

use InvalidArgumentException;
use MulerTech\Database\ORM\Validator\ArrayValidator;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class ArrayValidatorTest extends TestCase
{
    private ArrayValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ArrayValidator();
    }

    public function testValidateEntityArrayWithValidData(): void
    {
        $data = [
            '__entity__' => User::class,
            '__id__' => 1,
            '__hash__' => 123456
        ];
        
        $result = $this->validator->validateEntityArray($data);
        
        self::assertEquals($data, $result);
        self::assertEquals(User::class, $result['__entity__']);
        self::assertEquals(1, $result['__id__']);
        self::assertEquals(123456, $result['__hash__']);
    }

    public function testValidateEntityArrayWithNullId(): void
    {
        $data = [
            '__entity__' => User::class,
            '__id__' => null,
            '__hash__' => 123456
        ];
        
        $result = $this->validator->validateEntityArray($data);
        
        self::assertEquals($data, $result);
        self::assertNull($result['__id__']);
    }

    public function testValidateEntityArrayWithNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is not an array');
        
        $this->validator->validateEntityArray('not an array');
    }

    public function testValidateEntityArrayWithMissingEntityKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __entity__ key');
        
        $data = [
            '__id__' => 1,
            '__hash__' => 123456
        ];
        
        $this->validator->validateEntityArray($data);
    }

    public function testValidateEntityArrayWithInvalidEntityKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __entity__ key');
        
        $data = [
            '__entity__' => 123,
            '__id__' => 1,
            '__hash__' => 123456
        ];
        
        $this->validator->validateEntityArray($data);
    }

    public function testValidateEntityArrayWithMissingIdKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing __id__ key');
        
        $data = [
            '__entity__' => User::class,
            '__hash__' => 123456
        ];
        
        $this->validator->validateEntityArray($data);
    }

    public function testValidateEntityArrayWithMissingHashKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __hash__ key');
        
        $data = [
            '__entity__' => User::class,
            '__id__' => 1
        ];
        
        $this->validator->validateEntityArray($data);
    }

    public function testValidateEntityArrayWithInvalidHashType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __hash__ key');
        
        $data = [
            '__entity__' => User::class,
            '__id__' => 1,
            '__hash__' => 'not an integer'
        ];
        
        $this->validator->validateEntityArray($data);
    }

    public function testValidateEntityArrayWithNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name in __entity__');
        
        $data = [
            '__entity__' => 'NonExistentClass',
            '__id__' => 1,
            '__hash__' => 123456
        ];
        
        $this->validator->validateEntityArray($data);
    }

    public function testValidateObjectArrayWithValidData(): void
    {
        $data = [
            '__object__' => \stdClass::class,
            '__hash__' => 123456
        ];
        
        $result = $this->validator->validateObjectArray($data);
        
        self::assertEquals($data, $result);
        self::assertEquals(\stdClass::class, $result['__object__']);
        self::assertEquals(123456, $result['__hash__']);
    }

    public function testValidateObjectArrayWithNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is not an array');
        
        $this->validator->validateObjectArray('not an array');
    }

    public function testValidateObjectArrayWithMissingObjectKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __object__ key');
        
        $data = ['__hash__' => 123456];
        
        $this->validator->validateObjectArray($data);
    }

    public function testValidateObjectArrayWithInvalidObjectKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __object__ key');
        
        $data = [
            '__object__' => 123,
            '__hash__' => 123456
        ];
        
        $this->validator->validateObjectArray($data);
    }

    public function testValidateObjectArrayWithMissingHashKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __hash__ key');
        
        $data = ['__object__' => \stdClass::class];
        
        $this->validator->validateObjectArray($data);
    }

    public function testValidateObjectArrayWithNonExistentClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name in __object__');
        
        $data = [
            '__object__' => 'NonExistentClass',
            '__hash__' => 123456
        ];
        
        $this->validator->validateObjectArray($data);
    }

    public function testValidateCollectionArrayWithValidData(): void
    {
        $items = [
            ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123],
            ['__entity__' => Unit::class, '__id__' => 2, '__hash__' => 456]
        ];
        
        $data = [
            '__collection__' => true,
            '__items__' => $items
        ];
        
        $result = $this->validator->validateCollectionArray($data);
        
        self::assertTrue($result['__collection__']);
        self::assertCount(2, $result['__items__']);
        self::assertEquals($items, $result['__items__']);
    }

    public function testValidateCollectionArrayWithEmptyItems(): void
    {
        $data = [
            '__collection__' => true,
            '__items__' => []
        ];
        
        $result = $this->validator->validateCollectionArray($data);
        
        self::assertTrue($result['__collection__']);
        self::assertEmpty($result['__items__']);
    }

    public function testValidateCollectionArrayWithNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is not an array');
        
        $this->validator->validateCollectionArray('not an array');
    }

    public function testValidateCollectionArrayWithMissingCollectionKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __collection__ key');
        
        $data = ['__items__' => []];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithInvalidCollectionKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __collection__ key');
        
        $data = [
            '__collection__' => 'not a boolean',
            '__items__' => []
        ];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithMissingItemsKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __items__ key');
        
        $data = ['__collection__' => true];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithInvalidItemsKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid __items__ key');
        
        $data = [
            '__collection__' => true,
            '__items__' => 'not an array'
        ];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithInvalidItemIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Collection items must have integer indices');
        
        $data = [
            '__collection__' => true,
            '__items__' => [
                'invalid_key' => ['__entity__' => User::class, '__id__' => 1, '__hash__' => 123]
            ]
        ];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithInvalidItemStructure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Collection item must be an array');
        
        $data = [
            '__collection__' => true,
            '__items__' => [0 => 'not an array']
        ];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithMissingItemFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid collection item structure');
        
        $data = [
            '__collection__' => true,
            '__items__' => [
                0 => ['__entity__' => User::class]
            ]
        ];
        
        $this->validator->validateCollectionArray($data);
    }

    public function testValidateCollectionArrayWithNonExistentClassInItem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name in collection item');
        
        $data = [
            '__collection__' => true,
            '__items__' => [
                0 => ['__entity__' => 'NonExistentClass', '__id__' => 1, '__hash__' => 123]
            ]
        ];
        
        $this->validator->validateCollectionArray($data);
    }
}