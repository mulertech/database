<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use PHPUnit\Framework\TestCase;

class EntityStateTest extends TestCase
{
    public function testConstructorAndBasicProperties(): void
    {
        $timestamp = new DateTimeImmutable();
        $originalData = ['username' => 'John', 'age' => 25];
        
        $entityState = new EntityState(
            EntityLifecycleState::NEW,
            $originalData,
            $timestamp
        );
        
        self::assertEquals(EntityLifecycleState::NEW, $entityState->state);
        self::assertEquals($originalData, $entityState->originalData);
        self::assertSame($timestamp, $entityState->timestamp);
    }

    public function testWithDifferentStates(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $newState = new EntityState(
            EntityLifecycleState::NEW,
            [],
            $timestamp
        );
        
        $managedState = new EntityState(
            EntityLifecycleState::MANAGED,
            [],
            $timestamp
        );
        
        $removedState = new EntityState(
            EntityLifecycleState::REMOVED,
            [],
            $timestamp
        );
        
        $detachedState = new EntityState(
            EntityLifecycleState::DETACHED,
            [],
            $timestamp
        );
        
        self::assertEquals(EntityLifecycleState::NEW, $newState->state);
        self::assertEquals(EntityLifecycleState::MANAGED, $managedState->state);
        self::assertEquals(EntityLifecycleState::REMOVED, $removedState->state);
        self::assertEquals(EntityLifecycleState::DETACHED, $detachedState->state);
    }

    public function testIsNew(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $newState = new EntityState(
            EntityLifecycleState::NEW,
            [],
            $timestamp
        );
        
        $managedState = new EntityState(
            EntityLifecycleState::MANAGED,
            [],
            $timestamp
        );
        
        self::assertTrue($newState->isNew());
        self::assertFalse($managedState->isNew());
    }

    public function testIsManaged(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $newState = new EntityState(
            EntityLifecycleState::NEW,
            [],
            $timestamp
        );
        
        $managedState = new EntityState(
            EntityLifecycleState::MANAGED,
            [],
            $timestamp
        );
        
        self::assertFalse($newState->isManaged());
        self::assertTrue($managedState->isManaged());
    }

    public function testIsRemoved(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $managedState = new EntityState(
            EntityLifecycleState::MANAGED,
            [],
            $timestamp
        );
        
        $removedState = new EntityState(
            EntityLifecycleState::REMOVED,
            [],
            $timestamp
        );
        
        self::assertFalse($managedState->isRemoved());
        self::assertTrue($removedState->isRemoved());
    }

    public function testIsDetached(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $managedState = new EntityState(
            EntityLifecycleState::MANAGED,
            [],
            $timestamp
        );
        
        $detachedState = new EntityState(
            EntityLifecycleState::DETACHED,
            [],
            $timestamp
        );
        
        self::assertFalse($managedState->isDetached());
        self::assertTrue($detachedState->isDetached());
    }

    public function testWithEmptyOriginalData(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $entityState = new EntityState(
            EntityLifecycleState::NEW,
            [],
            $timestamp
        );
        
        self::assertEquals([], $entityState->originalData);
        self::assertTrue($entityState->isNew());
    }

    public function testWithComplexOriginalData(): void
    {
        $timestamp = new DateTimeImmutable();
        $complexData = [
            'username' => 'John',
            'age' => 25,
            'profile' => [
                'email' => 'john@example.com',
                'preferences' => ['theme' => 'dark']
            ],
            'active' => true,
            'lastLogin' => null
        ];
        
        $entityState = new EntityState(
            EntityLifecycleState::MANAGED,
            $complexData,
            $timestamp
        );
        
        self::assertEquals($complexData, $entityState->originalData);
        self::assertEquals('John', $entityState->originalData['username']);
        self::assertEquals(25, $entityState->originalData['age']);
        self::assertEquals('john@example.com', $entityState->originalData['profile']['email']);
        self::assertTrue($entityState->originalData['active']);
        self::assertNull($entityState->originalData['lastLogin']);
    }

    public function testTimestampPrecision(): void
    {
        $timestamp1 = new DateTimeImmutable('2023-01-01 12:00:00.123456');
        $timestamp2 = new DateTimeImmutable('2023-01-01 12:00:00.654321');
        
        $state1 = new EntityState(
            EntityLifecycleState::NEW,
            [],
            $timestamp1
        );
        
        $state2 = new EntityState(
            EntityLifecycleState::NEW,
            [],
            $timestamp2
        );
        
        self::assertNotEquals($state1->timestamp, $state2->timestamp);
        self::assertEquals('2023-01-01 12:00:00.123456', $state1->timestamp->format('Y-m-d H:i:s.u'));
        self::assertEquals('2023-01-01 12:00:00.654321', $state2->timestamp->format('Y-m-d H:i:s.u'));
    }

    public function testReadonlyClass(): void
    {
        $entityState = new EntityState(
            EntityLifecycleState::NEW,
            [],
            new DateTimeImmutable()
        );
        
        $reflection = new \ReflectionClass($entityState);
        
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    public function testMultipleInstances(): void
    {
        $timestamp = new DateTimeImmutable();
        
        $state1 = new EntityState(
            EntityLifecycleState::NEW,
            ['username' => 'John'],
            $timestamp
        );
        
        $state2 = new EntityState(
            EntityLifecycleState::MANAGED,
            ['username' => 'Jane'],
            $timestamp
        );
        
        self::assertNotSame($state1, $state2);
        self::assertNotEquals($state1->state, $state2->state);
        self::assertNotEquals($state1->originalData, $state2->originalData);
        self::assertSame($state1->timestamp, $state2->timestamp);
    }
}