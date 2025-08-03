<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use MulerTech\Database\ORM\PropertyChange;
use PHPUnit\Framework\TestCase;

class PropertyChangeTest extends TestCase
{
    public function testConstructorAndBasicProperties(): void
    {
        $propertyChange = new PropertyChange('username', 'John', 'Jane');
        
        self::assertEquals('username', $propertyChange->property);
        self::assertEquals('John', $propertyChange->oldValue);
        self::assertEquals('Jane', $propertyChange->newValue);
    }

    public function testWithNullValues(): void
    {
        $propertyChange = new PropertyChange('age', null, 25);
        
        self::assertEquals('age', $propertyChange->property);
        self::assertNull($propertyChange->oldValue);
        self::assertEquals(25, $propertyChange->newValue);
    }

    public function testWithNullNewValue(): void
    {
        $propertyChange = new PropertyChange('description', 'Some text', null);
        
        self::assertEquals('description', $propertyChange->property);
        self::assertEquals('Some text', $propertyChange->oldValue);
        self::assertNull($propertyChange->newValue);
    }

    public function testWithBothNullValues(): void
    {
        $propertyChange = new PropertyChange('optional', null, null);
        
        self::assertEquals('optional', $propertyChange->property);
        self::assertNull($propertyChange->oldValue);
        self::assertNull($propertyChange->newValue);
    }

    public function testWithNumericValues(): void
    {
        $propertyChange = new PropertyChange('age', 25, 30);
        
        self::assertEquals('age', $propertyChange->property);
        self::assertEquals(25, $propertyChange->oldValue);
        self::assertEquals(30, $propertyChange->newValue);
    }

    public function testWithFloatValues(): void
    {
        $propertyChange = new PropertyChange('price', 19.99, 24.99);
        
        self::assertEquals('price', $propertyChange->property);
        self::assertEquals(19.99, $propertyChange->oldValue);
        self::assertEquals(24.99, $propertyChange->newValue);
    }

    public function testWithBooleanValues(): void
    {
        $propertyChange = new PropertyChange('isActive', false, true);
        
        self::assertEquals('isActive', $propertyChange->property);
        self::assertFalse($propertyChange->oldValue);
        self::assertTrue($propertyChange->newValue);
    }

    public function testWithArrayValues(): void
    {
        $oldArray = ['a', 'b', 'c'];
        $newArray = ['d', 'e', 'f'];
        
        $propertyChange = new PropertyChange('tags', $oldArray, $newArray);
        
        self::assertEquals('tags', $propertyChange->property);
        self::assertEquals($oldArray, $propertyChange->oldValue);
        self::assertEquals($newArray, $propertyChange->newValue);
    }

    public function testWithObjectValues(): void
    {
        $oldObject = new \stdClass();
        $oldObject->name = 'old';
        
        $newObject = new \stdClass();
        $newObject->name = 'new';
        
        $propertyChange = new PropertyChange('config', $oldObject, $newObject);
        
        self::assertEquals('config', $propertyChange->property);
        self::assertSame($oldObject, $propertyChange->oldValue);
        self::assertSame($newObject, $propertyChange->newValue);
    }

    public function testWithMixedTypes(): void
    {
        $propertyChange = new PropertyChange('value', 'string', 42);
        
        self::assertEquals('value', $propertyChange->property);
        self::assertEquals('string', $propertyChange->oldValue);
        self::assertEquals(42, $propertyChange->newValue);
        self::assertIsString($propertyChange->oldValue);
        self::assertIsInt($propertyChange->newValue);
    }

    public function testReadonlyClass(): void
    {
        $propertyChange = new PropertyChange('test', 'old', 'new');
        
        $reflection = new \ReflectionClass($propertyChange);
        
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    public function testWithEmptyString(): void
    {
        $propertyChange = new PropertyChange('comment', '', 'New comment');
        
        self::assertEquals('comment', $propertyChange->property);
        self::assertEquals('', $propertyChange->oldValue);
        self::assertEquals('New comment', $propertyChange->newValue);
    }

    public function testWithZeroValues(): void
    {
        $propertyChange = new PropertyChange('count', 0, 5);
        
        self::assertEquals('count', $propertyChange->property);
        self::assertEquals(0, $propertyChange->oldValue);
        self::assertEquals(5, $propertyChange->newValue);
    }

    public function testMultipleInstances(): void
    {
        $change1 = new PropertyChange('prop1', 'old1', 'new1');
        $change2 = new PropertyChange('prop2', 'old2', 'new2');
        
        self::assertNotSame($change1, $change2);
        self::assertEquals('prop1', $change1->property);
        self::assertEquals('prop2', $change2->property);
        self::assertNotEquals($change1->property, $change2->property);
    }
}