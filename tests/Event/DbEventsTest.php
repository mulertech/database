<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\DbEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbEvents::class)]
class DbEventsTest extends TestCase
{
    public function testAllDbEventsHaveExpectedValues(): void
    {
        $expectedValues = [
            'preRemove' => DbEvents::preRemove,
            'postRemove' => DbEvents::postRemove,
            'prePersist' => DbEvents::prePersist,
            'postPersist' => DbEvents::postPersist,
            'preUpdate' => DbEvents::preUpdate,
            'postUpdate' => DbEvents::postUpdate,
            'postLoad' => DbEvents::postLoad,
            'loadClassMetadata' => DbEvents::loadClassMetadata,
            'onClassMetadataNotFound' => DbEvents::onClassMetadataNotFound,
            'preFlush' => DbEvents::preFlush,
            'onFlush' => DbEvents::onFlush,
            'postFlush' => DbEvents::postFlush,
            'onClear' => DbEvents::onClear,
            'preStateTransition' => DbEvents::preStateTransition,
            'postStateTransition' => DbEvents::postStateTransition,
        ];

        foreach ($expectedValues as $expectedValue => $dbEvent) {
            $this->assertEquals($expectedValue, $dbEvent->value);
        }
    }

    public function testDbEventsIsStringBackedEnum(): void
    {
        $this->assertInstanceOf(\BackedEnum::class, DbEvents::prePersist);
        $this->assertIsString(DbEvents::prePersist->value);
    }

    public function testPreRemoveEvent(): void
    {
        $this->assertEquals('preRemove', DbEvents::preRemove->value);
    }

    public function testPostRemoveEvent(): void
    {
        $this->assertEquals('postRemove', DbEvents::postRemove->value);
    }

    public function testPrePersistEvent(): void
    {
        $this->assertEquals('prePersist', DbEvents::prePersist->value);
    }

    public function testPostPersistEvent(): void
    {
        $this->assertEquals('postPersist', DbEvents::postPersist->value);
    }

    public function testPreUpdateEvent(): void
    {
        $this->assertEquals('preUpdate', DbEvents::preUpdate->value);
    }

    public function testPostUpdateEvent(): void
    {
        $this->assertEquals('postUpdate', DbEvents::postUpdate->value);
    }

    public function testPostLoadEvent(): void
    {
        $this->assertEquals('postLoad', DbEvents::postLoad->value);
    }

    public function testLoadClassMetadataEvent(): void
    {
        $this->assertEquals('loadClassMetadata', DbEvents::loadClassMetadata->value);
    }

    public function testOnClassMetadataNotFoundEvent(): void
    {
        $this->assertEquals('onClassMetadataNotFound', DbEvents::onClassMetadataNotFound->value);
    }

    public function testPreFlushEvent(): void
    {
        $this->assertEquals('preFlush', DbEvents::preFlush->value);
    }

    public function testOnFlushEvent(): void
    {
        $this->assertEquals('onFlush', DbEvents::onFlush->value);
    }

    public function testPostFlushEvent(): void
    {
        $this->assertEquals('postFlush', DbEvents::postFlush->value);
    }

    public function testOnClearEvent(): void
    {
        $this->assertEquals('onClear', DbEvents::onClear->value);
    }

    public function testPreStateTransitionEvent(): void
    {
        $this->assertEquals('preStateTransition', DbEvents::preStateTransition->value);
    }

    public function testPostStateTransitionEvent(): void
    {
        $this->assertEquals('postStateTransition', DbEvents::postStateTransition->value);
    }

    public function testFromStringValues(): void
    {
        $this->assertEquals(DbEvents::prePersist, DbEvents::from('prePersist'));
        $this->assertEquals(DbEvents::postPersist, DbEvents::from('postPersist'));
        $this->assertEquals(DbEvents::preUpdate, DbEvents::from('preUpdate'));
        $this->assertEquals(DbEvents::postUpdate, DbEvents::from('postUpdate'));
        $this->assertEquals(DbEvents::preRemove, DbEvents::from('preRemove'));
        $this->assertEquals(DbEvents::postRemove, DbEvents::from('postRemove'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(DbEvents::tryFrom('invalidEvent'));
    }

    public function testGetAllCases(): void
    {
        $cases = DbEvents::cases();
        $this->assertCount(15, $cases);
        $this->assertContains(DbEvents::prePersist, $cases);
        $this->assertContains(DbEvents::postFlush, $cases);
    }
}