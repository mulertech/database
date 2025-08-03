<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\State\EntityLifecycleState;
use PHPUnit\Framework\TestCase;

class EntityLifecycleStateTest extends TestCase
{
    public function testAllStatesExist(): void
    {
        $states = [
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::REMOVED,
            EntityLifecycleState::DETACHED
        ];
        
        foreach ($states as $state) {
            self::assertInstanceOf(EntityLifecycleState::class, $state);
        }
    }

    public function testStateValues(): void
    {
        self::assertEquals('new', EntityLifecycleState::NEW->value);
        self::assertEquals('managed', EntityLifecycleState::MANAGED->value);
        self::assertEquals('removed', EntityLifecycleState::REMOVED->value);
        self::assertEquals('detached', EntityLifecycleState::DETACHED->value);
    }

    public function testStateNames(): void
    {
        self::assertEquals('NEW', EntityLifecycleState::NEW->name);
        self::assertEquals('MANAGED', EntityLifecycleState::MANAGED->name);
        self::assertEquals('REMOVED', EntityLifecycleState::REMOVED->name);
        self::assertEquals('DETACHED', EntityLifecycleState::DETACHED->name);
    }

    public function testStatesAreUnique(): void
    {
        $states = [
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::REMOVED,
            EntityLifecycleState::DETACHED
        ];
        
        $values = array_map(fn($state) => $state->value, $states);
        $uniqueValues = array_unique($values);
        
        self::assertCount(count($states), $uniqueValues);
    }

    public function testFromValue(): void
    {
        self::assertEquals(EntityLifecycleState::NEW, EntityLifecycleState::from('new'));
        self::assertEquals(EntityLifecycleState::MANAGED, EntityLifecycleState::from('managed'));
        self::assertEquals(EntityLifecycleState::REMOVED, EntityLifecycleState::from('removed'));
        self::assertEquals(EntityLifecycleState::DETACHED, EntityLifecycleState::from('detached'));
    }

    public function testTryFromValue(): void
    {
        self::assertEquals(EntityLifecycleState::NEW, EntityLifecycleState::tryFrom('new'));
        self::assertEquals(EntityLifecycleState::MANAGED, EntityLifecycleState::tryFrom('managed'));
        self::assertEquals(EntityLifecycleState::REMOVED, EntityLifecycleState::tryFrom('removed'));
        self::assertEquals(EntityLifecycleState::DETACHED, EntityLifecycleState::tryFrom('detached'));
        
        self::assertNull(EntityLifecycleState::tryFrom('invalid'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        EntityLifecycleState::from('invalid');
    }

    public function testCases(): void
    {
        $cases = EntityLifecycleState::cases();
        
        self::assertCount(4, $cases);
        self::assertContains(EntityLifecycleState::NEW, $cases);
        self::assertContains(EntityLifecycleState::MANAGED, $cases);
        self::assertContains(EntityLifecycleState::REMOVED, $cases);
        self::assertContains(EntityLifecycleState::DETACHED, $cases);
    }

    public function testComparison(): void
    {
        $new1 = EntityLifecycleState::NEW;
        $new2 = EntityLifecycleState::NEW;
        $managed = EntityLifecycleState::MANAGED;
        
        self::assertTrue($new1 === $new2);
        self::assertFalse($new1 === $managed);
        self::assertTrue($new1 == $new2);
        self::assertFalse($new1 == $managed);
    }

    public function testStringRepresentation(): void
    {
        self::assertEquals('new', EntityLifecycleState::NEW->value);
        self::assertEquals('managed', EntityLifecycleState::MANAGED->value);
        self::assertEquals('removed', EntityLifecycleState::REMOVED->value);
        self::assertEquals('detached', EntityLifecycleState::DETACHED->value);
    }

    public function testStateTransitions(): void
    {
        $allStates = EntityLifecycleState::cases();
        
        foreach ($allStates as $state) {
            self::assertInstanceOf(EntityLifecycleState::class, $state);
            self::assertIsString($state->value);
            self::assertNotEmpty($state->value);
        }
    }

    public function testSerializationCompatibility(): void
    {
        $state = EntityLifecycleState::MANAGED;
        $serialized = serialize($state);
        $unserialized = unserialize($serialized);
        
        self::assertEquals($state, $unserialized);
        self::assertTrue($state === $unserialized);
    }

    public function testJsonSerialization(): void
    {
        $state = EntityLifecycleState::NEW;
        $json = json_encode($state);
        
        self::assertEquals('"new"', $json);
    }

    public function testMatchExpression(): void
    {
        $state = EntityLifecycleState::MANAGED;
        
        $result = match($state) {
            EntityLifecycleState::NEW => 'new entity',
            EntityLifecycleState::MANAGED => 'managed entity',
            EntityLifecycleState::REMOVED => 'removed entity',
            EntityLifecycleState::DETACHED => 'detached entity',
        };
        
        self::assertEquals('managed entity', $result);
    }
}