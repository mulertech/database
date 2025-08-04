<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\DirectStateManager;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateTransitionManager;
use MulerTech\Database\ORM\State\StateValidator;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class DirectStateManagerTest extends TestCase
{
    private DirectStateManager $stateManager;
    private IdentityMap $identityMap;
    private StateTransitionManager $transitionManager;
    private StateValidator $stateValidator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->identityMap = new IdentityMap();
        $this->transitionManager = new StateTransitionManager($this->identityMap);
        $this->stateValidator = new StateValidator();
        
        $this->stateManager = new DirectStateManager(
            $this->identityMap,
            $this->transitionManager,
            $this->stateValidator,
            null // No ChangeSetManager for simplicity
        );
    }

    public function testIsManaged(): void
    {
        $entity = new User();
        
        // Initially not managed
        $this->assertFalse($this->stateManager->isManaged($entity));
        
        // Manage the entity
        $this->stateManager->manage($entity);
        
        $this->assertTrue($this->stateManager->isManaged($entity));
    }

    public function testManage(): void
    {
        $entity = new User();
        
        $this->stateManager->manage($entity);
        
        $this->assertTrue($this->stateManager->isManaged($entity));
    }

    public function testScheduleForInsertion(): void
    {
        $entity = new User();
        
        // Should not throw exception
        $this->stateManager->scheduleForInsertion($entity);
        
        $this->assertTrue(true);
    }

    public function testScheduleForUpdate(): void
    {
        $entity = new User();
        // First make it managed
        $this->stateManager->manage($entity);
        
        // Should not throw exception
        $this->stateManager->scheduleForUpdate($entity);
        
        $this->assertTrue(true);
    }

    public function testScheduleForDeletion(): void
    {
        $entity = new User();
        // First make it managed
        $this->stateManager->manage($entity);
        
        // Should not throw exception
        $this->stateManager->scheduleForDeletion($entity);
        
        $this->assertTrue(true);
    }

    public function testDetach(): void
    {
        $entity = new User();
        $this->stateManager->manage($entity); // Make it managed first
        
        $this->stateManager->detach($entity);
        
        $this->assertFalse($this->stateManager->isManaged($entity));
    }

    public function testDetachWithInvalidState(): void
    {
        $entity = new User();
        // Entity is in DETACHED state, cannot detach again
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot detach entity in detached state');
        
        $this->stateManager->detach($entity);
    }

    public function testAddInsertionDependency(): void
    {
        $dependent = new User();
        $dependency = new User();
        
        // Should not throw exception
        $this->stateManager->addInsertionDependency($dependent, $dependency);
        
        $this->assertTrue(true);
    }

    public function testGetScheduledInsertions(): void
    {
        $scheduled = $this->stateManager->getScheduledInsertions();
        
        $this->assertIsArray($scheduled);
    }

    public function testGetScheduledUpdates(): void
    {
        $scheduled = $this->stateManager->getScheduledUpdates();
        
        $this->assertIsArray($scheduled);
    }

    public function testGetScheduledDeletions(): void
    {
        $scheduled = $this->stateManager->getScheduledDeletions();
        
        $this->assertIsArray($scheduled);
    }

    public function testGetManagedEntities(): void
    {
        $managed = $this->stateManager->getManagedEntities();
        
        // Should return an array (empty or not)
        $this->assertIsArray($managed);
    }

    public function testIsScheduledForInsertion(): void
    {
        $entity = new User();
        
        $this->assertFalse($this->stateManager->isScheduledForInsertion($entity));
    }

    public function testIsScheduledForUpdate(): void
    {
        $entity = new User();
        
        $this->assertFalse($this->stateManager->isScheduledForUpdate($entity));
    }

    public function testIsScheduledForDeletion(): void
    {
        $entity = new User();
        
        $this->assertFalse($this->stateManager->isScheduledForDeletion($entity));
    }

    public function testMarkAsPersisted(): void
    {
        $entity = new User();
        
        $this->stateManager->markAsPersisted($entity);
        
        $this->assertTrue($this->stateManager->isManaged($entity));
    }

    public function testMarkAsRemoved(): void
    {
        $entity = new User();
        $this->stateManager->manage($entity);
        
        $this->stateManager->markAsRemoved($entity);
        
        $this->assertFalse($this->stateManager->isManaged($entity));
    }

    public function testMerge(): void
    {
        $entity = new User();
        
        $merged = $this->stateManager->merge($entity);
        
        $this->assertSame($entity, $merged);
    }

    public function testIsNew(): void
    {
        $entity = new User();
        
        // By default, entities are in DETACHED state, not NEW
        $this->assertFalse($this->stateManager->isNew($entity));
    }

    public function testIsRemoved(): void
    {
        $entity = new User();
        
        $this->assertFalse($this->stateManager->isRemoved($entity));
    }

    public function testIsDetached(): void
    {
        $entity = new User();
        
        // By default, entities are in DETACHED state
        $this->assertTrue($this->stateManager->isDetached($entity));
    }

    public function testClear(): void
    {
        // Should not throw exception
        $this->stateManager->clear();
        
        $this->assertTrue(true);
    }

    public function testGetEntityState(): void
    {
        $entity = new User();
        
        $state = $this->stateManager->getEntityState($entity);
        
        $this->assertInstanceOf(EntityLifecycleState::class, $state);
        $this->assertSame(EntityLifecycleState::DETACHED, $state);
    }
}