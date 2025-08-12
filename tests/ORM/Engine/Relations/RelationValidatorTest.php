<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Relations;

use MulerTech\Database\ORM\Engine\Relations\RelationValidator;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RelationValidatorTest extends TestCase
{
    private RelationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validator = new RelationValidator();
    }

    public function testValidateTargetEntityWithValidClass(): void
    {
        $relationData = ['targetEntity' => User::class];
        
        $result = $this->validator->validateTargetEntity(
            $relationData,
            'TestEntity',
            'testProperty'
        );
        
        $this->assertSame(User::class, $result);
    }

    public function testValidateTargetEntityWithNonExistentClass(): void
    {
        $relationData = ['targetEntity' => 'NonExistentClass'];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target entity class "NonExistentClass" for relation "testProperty" on entity "TestEntity" does not exist.');
        
        $this->validator->validateTargetEntity(
            $relationData,
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateTargetEntityWithEmptyString(): void
    {
        $relationData = ['targetEntity' => ''];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target entity class "" for relation "testProperty" on entity "TestEntity" does not exist.');
        
        $this->validator->validateTargetEntity(
            $relationData,
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateTargetEntityWithNonString(): void
    {
        $relationData = ['targetEntity' => 123];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target entity class "integer" for relation "testProperty" on entity "TestEntity" does not exist.');
        
        $this->validator->validateTargetEntity(
            $relationData,
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateTargetEntityWithMissingKey(): void
    {
        $relationData = [];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target entity class "" for relation "testProperty" on entity "TestEntity" does not exist.');
        
        $this->validator->validateTargetEntity(
            $relationData,
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateRelationPropertyWithValidString(): void
    {
        $result = $this->validator->validateRelationProperty(
            'validProperty',
            'join property',
            'TestEntity',
            'testProperty'
        );
        
        $this->assertSame('validProperty', $result);
    }

    public function testValidateRelationPropertyWithNull(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "join property" is not defined for the class "TestEntity" and property "testProperty". Please check the mapping configuration.');
        
        $this->validator->validateRelationProperty(
            null,
            'join property',
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateRelationPropertyWithEmptyString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "join property" is not defined for the class "TestEntity" and property "testProperty". Please check the mapping configuration.');
        
        $this->validator->validateRelationProperty(
            '',
            'join property',
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateRelationPropertyWithNonString(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "join property" must be a string for the class "TestEntity" and property "testProperty". Please check the mapping configuration.');
        
        $this->validator->validateRelationProperty(
            123,
            'join property',
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateMappedByWithValidValue(): void
    {
        $relation = ['mappedBy' => User::class];
        
        $result = $this->validator->validateMappedBy($relation, 'TestEntity', 'testProperty');
        
        $this->assertSame(User::class, $result);
    }

    public function testValidateMappedByWithMissingKey(): void
    {
        $relation = [];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mapped by property is not defined for the class "TestEntity" and property "testProperty". Please check the mapping configuration.');
        
        $this->validator->validateMappedBy($relation, 'TestEntity', 'testProperty');
    }

    public function testSetterAcceptsNullWithNullableParameter(): void
    {
        $user = new User();
        
        // Test with setUsername method that may accept null
        $result = $this->validator->setterAcceptsNull($user, 'setUsername');
        
        $this->assertIsBool($result);
    }

    public function testSetterAcceptsNullWithNonExistentMethod(): void
    {
        $user = new User();
        
        $result = $this->validator->setterAcceptsNull($user, 'nonExistentMethod');
        
        $this->assertFalse($result);
    }

    public function testSetRelationValueWithValidSetter(): void
    {
        $user = new User();
        
        $result = $this->validator->setRelationValue($user, 'username', 'testValue');
        
        $this->assertTrue($result);
        $this->assertSame('testValue', $user->getUsername());
    }

    public function testSetRelationValueWithNonExistentSetter(): void
    {
        $user = new User();
        
        $result = $this->validator->setRelationValue($user, 'nonExistentProperty', 'testValue');
        
        $this->assertFalse($result);
    }

    public function testSetRelationValueWithNullValue(): void
    {
        $user = new User();
        
        // This should work if the setter accepts null
        $result = $this->validator->setRelationValue($user, 'username', null);
        
        $this->assertIsBool($result);
    }

    public function testValidateInverseJoinPropertyWithValidValue(): void
    {
        $oneToMany = ['inverseJoinProperty' => 'validProperty'];
        
        $result = $this->validator->validateInverseJoinProperty(
            $oneToMany,
            'TestEntity',
            'testProperty'
        );
        
        $this->assertSame('validProperty', $result);
    }

    public function testValidateInverseJoinPropertyWithEmptyValue(): void
    {
        $oneToMany = ['inverseJoinProperty' => ''];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "mappedBy" attribute is not defined for the OneToMany relation "testProperty" on entity "TestEntity".');
        
        $this->validator->validateInverseJoinProperty(
            $oneToMany,
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateInverseJoinPropertyWithNonString(): void
    {
        $oneToMany = ['inverseJoinProperty' => 123];
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The "mappedBy" attribute must be a string for the OneToMany relation "testProperty" on entity "TestEntity".');
        
        $this->validator->validateInverseJoinProperty(
            $oneToMany,
            'TestEntity',
            'testProperty'
        );
    }

    public function testValidateEntityDataWithValidArray(): void
    {
        $entityData = [
            'id' => 123,
            'name' => 'John',
            'active' => true,
            'score' => 99.5,
            'nullable' => null
        ];
        
        $result = $this->validator->validateEntityData($entityData);
        
        $this->assertSame([
            'id' => 123,
            'name' => 'John',
            'active' => true,
            'score' => 99.5,
            'nullable' => null
        ], $result);
    }

    public function testValidateEntityDataWithNonArray(): void
    {
        $result = $this->validator->validateEntityData('not an array');
        
        $this->assertSame([], $result);
    }

    public function testValidateEntityDataWithObjectValues(): void
    {
        $entityData = [
            'valid' => 'string',
            'object' => new \stdClass(),
            'array' => ['nested']
        ];
        
        $result = $this->validator->validateEntityData($entityData);
        
        $this->assertSame([
            'valid' => 'string',
            'object' => null,
            'array' => null
        ], $result);
    }

    public function testValidateEntityDataWithNumericKeys(): void
    {
        $entityData = [
            0 => 'first',
            1 => 'second',
            'string_key' => 'third'
        ];
        
        $result = $this->validator->validateEntityData($entityData);
        
        $this->assertSame([
            '0' => 'first',
            '1' => 'second',
            'string_key' => 'third'
        ], $result);
    }

    public function testSetterAcceptsNullWithMethodHavingNoParameters(): void
    {
        // Create a test entity with a setter method that has no parameters
        $entity = new class {
            public function setNoParams(): void
            {
                // Method with no parameters to trigger first echo
            }
        };
        
        // This should trigger the echo for empty parameters
        $result = $this->validator->setterAcceptsNull($entity, 'setNoParams');
        
        $this->assertFalse($result);
    }

    public function testSetterAcceptsNullWithMethodHavingUntypedParameter(): void
    {
        // Create a test entity with a setter method that has no type hint
        $entity = new class {
            public function setUntypedParam($value): void
            {
                // Method with untyped parameter to trigger second echo
            }
        };
        
        // This should trigger the echo for no type hint (assumes it accepts null)
        $result = $this->validator->setterAcceptsNull($entity, 'setUntypedParam');
        
        $this->assertTrue($result);
    }
}