<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateTransitionManager;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use PHPUnit\Framework\TestCase;

class StateTransitionManagerTest extends TestCase
{
    private StateTransitionManager $transitionManager;
    private IdentityMap $identityMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->identityMap = new IdentityMap();
        $this->transitionManager = new StateTransitionManager($this->identityMap);
    }

    public function testTransitionFromNewToManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::MANAGED);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testTransitionFromManagedToRemoved(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::REMOVED);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::REMOVED, $metadata->state);
    }

    public function testTransitionFromManagedToDetached(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::DETACHED);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::DETACHED, $metadata->state);
    }

    public function testTransitionFromNewToDetached(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::DETACHED);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::DETACHED, $metadata->state);
    }

    public function testTransitionFromNewToRemoved(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::REMOVED);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::REMOVED, $metadata->state);
    }

    public function testInvalidTransitionFromRemovedToManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        // First transition to REMOVED state
        $this->transitionManager->transition($user, EntityLifecycleState::REMOVED);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid state transition from REMOVED to MANAGED');
        
        $this->transitionManager->transition($user, EntityLifecycleState::MANAGED);
    }

    public function testInvalidTransitionFromDetachedToManaged(): void
    {
        $user = new User();
        $user->setUsername('John');
        $user->setId(1);

        $this->identityMap->add($user);

        // First transition to DETACHED state
        $this->transitionManager->transition($user, EntityLifecycleState::DETACHED);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid state transition from DETACHED to MANAGED');
        
        $this->transitionManager->transition($user, EntityLifecycleState::MANAGED);
    }

    public function testTransitionUnmanagedEntity(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // The StateTransitionManager should now automatically manage unmanaged entities
        $this->transitionManager->transition($user, EntityLifecycleState::MANAGED);
        
        $metadata = $this->identityMap->getMetadata($user);
        self::assertNotNull($metadata);
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata->state);
    }

    public function testCanTransitionFromNewToManaged(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::NEW, EntityLifecycleState::MANAGED);
        
        self::assertTrue($result);
    }

    public function testCanTransitionFromManagedToRemoved(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::MANAGED, EntityLifecycleState::REMOVED);
        
        self::assertTrue($result);
    }

    public function testCanTransitionFromManagedToDetached(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::MANAGED, EntityLifecycleState::DETACHED);
        
        self::assertTrue($result);
    }

    public function testCanTransitionFromNewToDetached(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::NEW, EntityLifecycleState::DETACHED);
        
        self::assertTrue($result);
    }

    public function testCanTransitionFromNewToRemoved(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::NEW, EntityLifecycleState::REMOVED);
        
        self::assertTrue($result);
    }

    public function testCannotTransitionFromRemovedToManaged(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::REMOVED, EntityLifecycleState::MANAGED);
        
        self::assertFalse($result);
    }

    public function testCannotTransitionFromDetachedToManaged(): void
    {
        $result = $this->transitionManager->canTransition(EntityLifecycleState::DETACHED, EntityLifecycleState::MANAGED);
        
        self::assertFalse($result);
    }

    public function testGetValidTransitions(): void
    {
        $validTransitions = $this->transitionManager->getValidTransitions(EntityLifecycleState::NEW);
        
        self::assertIsArray($validTransitions);
        self::assertContains(EntityLifecycleState::MANAGED, $validTransitions);
        self::assertContains(EntityLifecycleState::DETACHED, $validTransitions);
        self::assertContains(EntityLifecycleState::REMOVED, $validTransitions);
    }

    public function testGetValidTransitionsFromManaged(): void
    {
        $validTransitions = $this->transitionManager->getValidTransitions(EntityLifecycleState::MANAGED);
        
        self::assertIsArray($validTransitions);
        self::assertContains(EntityLifecycleState::REMOVED, $validTransitions);
        self::assertContains(EntityLifecycleState::DETACHED, $validTransitions);
        self::assertNotContains(EntityLifecycleState::NEW, $validTransitions);
    }

    public function testGetValidTransitionsFromRemoved(): void
    {
        $validTransitions = $this->transitionManager->getValidTransitions(EntityLifecycleState::REMOVED);
        
        self::assertIsArray($validTransitions);
        self::assertEmpty($validTransitions);
    }

    public function testGetValidTransitionsFromDetached(): void
    {
        $validTransitions = $this->transitionManager->getValidTransitions(EntityLifecycleState::DETACHED);
        
        self::assertIsArray($validTransitions);
        self::assertEmpty($validTransitions);
    }

    public function testGetTransitionHistory(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::MANAGED);
        $this->transitionManager->transition($user, EntityLifecycleState::DETACHED);
        
        $history = $this->transitionManager->getTransitionHistory($user);
        
        self::assertIsArray($history);
        self::assertCount(2, $history);
        self::assertEquals(EntityLifecycleState::MANAGED, $history[0]['to']);
        self::assertEquals(EntityLifecycleState::DETACHED, $history[1]['to']);
    }

    public function testGetTransitionHistoryEmpty(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $history = $this->transitionManager->getTransitionHistory($user);
        
        self::assertIsArray($history);
        self::assertEmpty($history);
    }

    public function testClearTransitionHistory(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->identityMap->add($user);

        $this->transitionManager->transition($user, EntityLifecycleState::MANAGED);
        
        $history = $this->transitionManager->getTransitionHistory($user);
        self::assertNotEmpty($history);
        
        $this->transitionManager->clearTransitionHistory($user);
        
        $history = $this->transitionManager->getTransitionHistory($user);
        self::assertEmpty($history);
    }

    public function testMultipleEntitiesTransitions(): void
    {
        $user1 = new User();
        $user1->setUsername('John');
        $user2 = new User();
        $user2->setUsername('Jane');
        
        $this->identityMap->add($user1);
        $this->identityMap->add($user2);

        $this->transitionManager->transition($user1, EntityLifecycleState::MANAGED);
        $this->transitionManager->transition($user2, EntityLifecycleState::DETACHED);
        
        $metadata1 = $this->identityMap->getMetadata($user1);
        $metadata2 = $this->identityMap->getMetadata($user2);
        
        self::assertEquals(EntityLifecycleState::MANAGED, $metadata1->state);
        self::assertEquals(EntityLifecycleState::DETACHED, $metadata2->state);
    }

}
