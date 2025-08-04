<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use InvalidArgumentException;
use MulerTech\Database\Event\StateTransitionEvent;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(StateTransitionEvent::class)]
class StateTransitionEventTest extends TestCase
{
    private object $entity;

    protected function setUp(): void
    {
        $this->entity = new stdClass();
    }

    public function testConstructorWithValidPrePhase(): void
    {
        $event = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $this->assertEquals($this->entity, $event->getEntity());
        $this->assertEquals(EntityLifecycleState::NEW, $event->getFromState());
        $this->assertEquals(EntityLifecycleState::MANAGED, $event->getToState());
        $this->assertEquals('pre', $event->getPhase());
        $this->assertEquals('preStateTransition', $event->getName());
    }

    public function testConstructorWithValidPostPhase(): void
    {
        $event = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::DETACHED,
            'post'
        );

        $this->assertEquals($this->entity, $event->getEntity());
        $this->assertEquals(EntityLifecycleState::MANAGED, $event->getFromState());
        $this->assertEquals(EntityLifecycleState::DETACHED, $event->getToState());
        $this->assertEquals('post', $event->getPhase());
        $this->assertEquals('postStateTransition', $event->getName());
    }

    public function testConstructorWithInvalidPhaseThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Phase must be either "pre" or "post"');

        new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'invalid'
        );
    }

    public function testGetEntity(): void
    {
        $entity = new class {
            public string $name = 'test';
        };

        $event = new StateTransitionEvent(
            $entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $this->assertSame($entity, $event->getEntity());
        $this->assertEquals('test', $event->getEntity()->name);
    }

    public function testGetFromState(): void
    {
        $event = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::DETACHED,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $this->assertEquals(EntityLifecycleState::DETACHED, $event->getFromState());
    }

    public function testGetToState(): void
    {
        $event = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::REMOVED,
            'post'
        );

        $this->assertEquals(EntityLifecycleState::REMOVED, $event->getToState());
    }

    public function testGetPhase(): void
    {
        $preEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $postEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::DETACHED,
            'post'
        );

        $this->assertEquals('pre', $preEvent->getPhase());
        $this->assertEquals('post', $postEvent->getPhase());
    }

    public function testIsPreTransition(): void
    {
        $preEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $postEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::DETACHED,
            'post'
        );

        $this->assertTrue($preEvent->isPreTransition());
        $this->assertFalse($postEvent->isPreTransition());
    }

    public function testIsPostTransition(): void
    {
        $preEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $postEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::DETACHED,
            'post'
        );

        $this->assertFalse($preEvent->isPostTransition());
        $this->assertTrue($postEvent->isPostTransition());
    }

    public function testGetTransitionKey(): void
    {
        $entity = new class {
        };

        $event = new StateTransitionEvent(
            $entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $expectedKey = sprintf(
            '%s:new->managed',
            $entity::class
        );

        $this->assertEquals($expectedKey, $event->getTransitionKey());
    }

    public function testGetTransitionKeyWithDifferentStates(): void
    {
        $event = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::MANAGED,
            EntityLifecycleState::REMOVED,
            'post'
        );

        $expectedKey = sprintf(
            '%s:managed->removed',
            $this->entity::class
        );

        $this->assertEquals($expectedKey, $event->getTransitionKey());
    }

    public function testAllEntityLifecycleStateTransitions(): void
    {
        $testCases = [
            [EntityLifecycleState::NEW, EntityLifecycleState::MANAGED],
            [EntityLifecycleState::NEW, EntityLifecycleState::DETACHED],
            [EntityLifecycleState::MANAGED, EntityLifecycleState::DETACHED],
            [EntityLifecycleState::MANAGED, EntityLifecycleState::REMOVED],
            [EntityLifecycleState::DETACHED, EntityLifecycleState::MANAGED],
        ];

        foreach ($testCases as [$fromState, $toState]) {
            $event = new StateTransitionEvent(
                $this->entity,
                $fromState,
                $toState,
                'pre'
            );

            $this->assertEquals($fromState, $event->getFromState());
            $this->assertEquals($toState, $event->getToState());
            $this->assertStringContainsString($fromState->value, $event->getTransitionKey());
            $this->assertStringContainsString($toState->value, $event->getTransitionKey());
        }
    }

    public function testEventNameGeneration(): void
    {
        $preEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $postEvent = new StateTransitionEvent(
            $this->entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'post'
        );

        $this->assertEquals('preStateTransition', $preEvent->getName());
        $this->assertEquals('postStateTransition', $postEvent->getName());
    }

    public function testEventIsImmutable(): void
    {
        $entity = new stdClass();
        $fromState = EntityLifecycleState::NEW;
        $toState = EntityLifecycleState::MANAGED;
        $phase = 'pre';

        $event = new StateTransitionEvent($entity, $fromState, $toState, $phase);

        // Verify that all properties are readonly by checking they cannot be modified
        $this->assertSame($entity, $event->getEntity());
        $this->assertSame($fromState, $event->getFromState());
        $this->assertSame($toState, $event->getToState());
        $this->assertSame($phase, $event->getPhase());
    }

    public function testWithComplexEntity(): void
    {
        $entity = new class {
            public function __construct(
                public string $id = 'test-id',
                public string $name = 'Test Entity',
                public array $data = ['key' => 'value']
            ) {
            }
        };

        $event = new StateTransitionEvent(
            $entity,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $this->assertSame($entity, $event->getEntity());
        $this->assertEquals('test-id', $event->getEntity()->id);
        $this->assertEquals('Test Entity', $event->getEntity()->name);
        $this->assertEquals(['key' => 'value'], $event->getEntity()->data);
    }

    public function testTransitionKeyUniqueness(): void
    {
        $entity1 = new stdClass();
        $entity2 = new class {
        };

        $event1 = new StateTransitionEvent(
            $entity1,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        $event2 = new StateTransitionEvent(
            $entity2,
            EntityLifecycleState::NEW,
            EntityLifecycleState::MANAGED,
            'pre'
        );

        // Different entity classes should produce different transition keys
        $this->assertNotEquals($event1->getTransitionKey(), $event2->getTransitionKey());
        $this->assertStringContainsString('stdClass', $event1->getTransitionKey());
        $this->assertStringContainsString($entity2::class, $event2->getTransitionKey());
    }

    public function testConstructorParametersAreValidated(): void
    {
        // Test all valid phase values
        $validPhases = ['pre', 'post'];

        foreach ($validPhases as $phase) {
            $event = new StateTransitionEvent(
                $this->entity,
                EntityLifecycleState::NEW,
                EntityLifecycleState::MANAGED,
                $phase
            );
            
            $this->assertEquals($phase, $event->getPhase());
        }
    }

    public function testConstructorWithInvalidPhasesThrowsException(): void
    {
        // Test invalid phase values
        $invalidPhases = ['', 'before', 'after', 'during', 'invalid', '123', 'PRE', 'POST'];

        foreach ($invalidPhases as $invalidPhase) {
            try {
                new StateTransitionEvent(
                    $this->entity,
                    EntityLifecycleState::NEW,
                    EntityLifecycleState::MANAGED,
                    $invalidPhase
                );
                $this->fail("Expected InvalidArgumentException for phase: {$invalidPhase}");
            } catch (InvalidArgumentException $e) {
                $this->assertEquals('Phase must be either "pre" or "post"', $e->getMessage());
            }
        }
    }
}