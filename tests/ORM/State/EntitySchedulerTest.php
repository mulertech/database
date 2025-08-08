<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use InvalidArgumentException;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\EntityScheduler;
use MulerTech\Database\ORM\State\StateValidator;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class EntitySchedulerTest extends TestCase
{
    private EntityScheduler $scheduler;
    private IdentityMap $identityMap;
    private StateValidator $stateValidator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->identityMap = new IdentityMap();
        $this->stateValidator = new StateValidator();
        
        $this->scheduler = new EntityScheduler(
            $this->identityMap,
            $this->stateValidator,
            null // No ChangeSetManager for simplicity
        );
    }

    public function testScheduleForInsertionWithValidState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        // Should not throw exception
        $this->scheduler->scheduleForInsertion($entity, $currentState);
        
        $this->assertTrue(true);
    }

    public function testScheduleForInsertionWithInvalidState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule entity in removed state for insertion');
        
        $this->scheduler->scheduleForInsertion($entity, $currentState);
    }

    public function testScheduleForUpdateWithValidState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        // Should not throw exception
        $this->scheduler->scheduleForUpdate($entity, $currentState);
        
        $this->assertTrue(true);
    }

    public function testScheduleForUpdateWithInvalidState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule entity in new state for update');
        
        $this->scheduler->scheduleForUpdate($entity, $currentState);
    }

    public function testScheduleForDeletionWithValidState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::MANAGED;
        
        // Should not throw exception
        $this->scheduler->scheduleForDeletion($entity, $currentState);
        
        $this->assertTrue(true);
    }

    public function testScheduleForDeletionWithInvalidState(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot schedule entity in removed state for deletion');
        
        $this->scheduler->scheduleForDeletion($entity, $currentState);
    }

    public function testGetScheduledInsertionsWithoutChangeSetManager(): void
    {
        $entity = new User();
        $this->identityMap->add($entity);
        
        $scheduled = $this->scheduler->getScheduledInsertions();
        
        // The entity will be in NEW state when added to IdentityMap
        $this->assertCount(1, $scheduled);
        $this->assertContains($entity, $scheduled);
    }

    public function testGetScheduledUpdatesWithoutChangeSetManager(): void
    {
        $scheduled = $this->scheduler->getScheduledUpdates();
        
        $this->assertEmpty($scheduled);
    }

    public function testGetScheduledDeletionsWithoutChangeSetManager(): void
    {
        $scheduled = $this->scheduler->getScheduledDeletions();
        
        // Without ChangeSetManager and no entities in REMOVED state, should be empty
        $this->assertEmpty($scheduled);
    }

    public function testIsScheduledForInsertionWithoutChangeSetManager(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::NEW;
        
        $this->assertTrue($this->scheduler->isScheduledForInsertion($entity, $currentState));
        
        $currentState = EntityLifecycleState::MANAGED;
        $this->assertFalse($this->scheduler->isScheduledForInsertion($entity, $currentState));
    }

    public function testIsScheduledForUpdateWithoutChangeSetManager(): void
    {
        $entity = new User();
        
        $this->assertFalse($this->scheduler->isScheduledForUpdate($entity));
    }

    public function testIsScheduledForDeletionWithoutChangeSetManager(): void
    {
        $entity = new User();
        $currentState = EntityLifecycleState::REMOVED;
        
        $this->assertTrue($this->scheduler->isScheduledForDeletion($entity, $currentState));
        
        $currentState = EntityLifecycleState::MANAGED;
        $this->assertFalse($this->scheduler->isScheduledForDeletion($entity, $currentState));
    }

    public function testSchedulingValidatesEntityStates(): void
    {
        $entity = new User();
        
        // Test that validation is properly applied for each operation
        $validInsertionStates = [EntityLifecycleState::NEW, EntityLifecycleState::DETACHED];
        $validUpdateStates = [EntityLifecycleState::MANAGED];
        $validDeletionStates = [EntityLifecycleState::MANAGED, EntityLifecycleState::NEW, EntityLifecycleState::DETACHED];
        
        foreach ($validInsertionStates as $state) {
            try {
                $this->scheduler->scheduleForInsertion($entity, $state);
                $this->assertTrue(true, "Insertion should be valid for state {$state->value}");
            } catch (InvalidArgumentException $e) {
                $this->fail("Insertion should be valid for state {$state->value}");
            }
        }
        
        foreach ($validUpdateStates as $state) {
            try {
                $this->scheduler->scheduleForUpdate($entity, $state);
                $this->assertTrue(true, "Update should be valid for state {$state->value}");
            } catch (InvalidArgumentException $e) {
                $this->fail("Update should be valid for state {$state->value}");
            }
        }
        
        foreach ($validDeletionStates as $state) {
            try {
                $this->scheduler->scheduleForDeletion($entity, $state);
                $this->assertTrue(true, "Deletion should be valid for state {$state->value}");
            } catch (InvalidArgumentException $e) {
                $this->fail("Deletion should be valid for state {$state->value}");
            }
        }
    }

    public function testGetScheduledDeletionsWithRemovedEntity(): void
    {
        $identityMap = new IdentityMap();
        $stateValidator = new StateValidator();
        
        // Create scheduler WITHOUT ChangeSetManager to trigger the echo case
        $scheduler = new EntityScheduler($identityMap, $stateValidator, null);
        
        $entity = new User();
        $removedState = new EntityState(
            User::class,
            EntityLifecycleState::REMOVED,
            [],
            new \DateTimeImmutable()
        );
        
        $identityMap->add($entity, null, $removedState);
        
        // This should trigger the echo for getScheduledDeletions when entity is in REMOVED state
        $scheduled = $scheduler->getScheduledDeletions();
        
        $this->assertIsArray($scheduled);
        $this->assertCount(1, $scheduled);
        $this->assertContains($entity, $scheduled);
    }

}