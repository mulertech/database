<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\State;

use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class StateManagerInterfaceTest extends TestCase
{
    private StateManagerInterface $stateManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a mock implementation of the interface
        $this->stateManager = $this->createMock(StateManagerInterface::class);
    }

    public function testMergeMethod(): void
    {
        $entity = new User();
        $expectedEntity = new User();
        
        $this->stateManager->method('merge')
            ->with($entity)
            ->willReturn($expectedEntity);
        
        $result = $this->stateManager->merge($entity);
        
        $this->assertSame($expectedEntity, $result);
    }

    public function testIsNewMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isNew')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isNew($entity));
    }

    public function testIsManagedMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isManaged')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isManaged($entity));
    }

    public function testIsRemovedMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isRemoved')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isRemoved($entity));
    }

    public function testIsDetachedMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isDetached')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isDetached($entity));
    }

    public function testManageMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('manage')
            ->with($entity);
        
        $this->stateManager->manage($entity);
    }

    public function testScheduleForInsertionMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('scheduleForInsertion')
            ->with($entity);
        
        $this->stateManager->scheduleForInsertion($entity);
    }

    public function testScheduleForUpdateMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('scheduleForUpdate')
            ->with($entity);
        
        $this->stateManager->scheduleForUpdate($entity);
    }

    public function testScheduleForDeletionMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('scheduleForDeletion')
            ->with($entity);
        
        $this->stateManager->scheduleForDeletion($entity);
    }

    public function testDetachMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('detach')
            ->with($entity);
        
        $this->stateManager->detach($entity);
    }

    public function testGetEntityStateMethod(): void
    {
        $entity = new User();
        $expectedState = EntityLifecycleState::MANAGED;
        
        $this->stateManager->method('getEntityState')
            ->with($entity)
            ->willReturn($expectedState);
        
        $result = $this->stateManager->getEntityState($entity);
        
        $this->assertSame($expectedState, $result);
    }

    public function testAddInsertionDependencyMethod(): void
    {
        $dependent = new User();
        $dependency = new User();
        
        $this->stateManager->expects($this->once())
            ->method('addInsertionDependency')
            ->with($dependent, $dependency);
        
        $this->stateManager->addInsertionDependency($dependent, $dependency);
    }

    public function testGetScheduledInsertionsMethod(): void
    {
        $entity1 = new User();
        $entity2 = new User();
        $expectedEntities = [
            spl_object_id($entity1) => $entity1,
            spl_object_id($entity2) => $entity2
        ];
        
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn($expectedEntities);
        
        $result = $this->stateManager->getScheduledInsertions();
        
        $this->assertSame($expectedEntities, $result);
    }

    public function testGetScheduledUpdatesMethod(): void
    {
        $entity = new User();
        $expectedEntities = [spl_object_id($entity) => $entity];
        
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn($expectedEntities);
        
        $result = $this->stateManager->getScheduledUpdates();
        
        $this->assertSame($expectedEntities, $result);
    }

    public function testGetScheduledDeletionsMethod(): void
    {
        $entity = new User();
        $expectedEntities = [spl_object_id($entity) => $entity];
        
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn($expectedEntities);
        
        $result = $this->stateManager->getScheduledDeletions();
        
        $this->assertSame($expectedEntities, $result);
    }

    public function testGetManagedEntitiesMethod(): void
    {
        $entity1 = new User();
        $entity2 = new User();
        $expectedEntities = [
            spl_object_id($entity1) => $entity1,
            spl_object_id($entity2) => $entity2
        ];
        
        $this->stateManager->method('getManagedEntities')
            ->willReturn($expectedEntities);
        
        $result = $this->stateManager->getManagedEntities();
        
        $this->assertSame($expectedEntities, $result);
    }

    public function testIsScheduledForInsertionMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isScheduledForInsertion')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isScheduledForInsertion($entity));
    }

    public function testIsScheduledForUpdateMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isScheduledForUpdate')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isScheduledForUpdate($entity));
    }

    public function testIsScheduledForDeletionMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->method('isScheduledForDeletion')
            ->with($entity)
            ->willReturn(true);
        
        $this->assertTrue($this->stateManager->isScheduledForDeletion($entity));
    }

    public function testMarkAsPersistedMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('markAsPersisted')
            ->with($entity);
        
        $this->stateManager->markAsPersisted($entity);
    }

    public function testMarkAsRemovedMethod(): void
    {
        $entity = new User();
        
        $this->stateManager->expects($this->once())
            ->method('markAsRemoved')
            ->with($entity);
        
        $this->stateManager->markAsRemoved($entity);
    }

    public function testClearMethod(): void
    {
        $this->stateManager->expects($this->once())
            ->method('clear');
        
        $this->stateManager->clear();
    }

    public function testInterfaceContractsAreSatisfied(): void
    {
        $entity = new User();
        
        // Test that all methods can be called without errors
        $this->stateManager->method('merge')->willReturn($entity);
        $this->stateManager->method('isNew')->willReturn(false);
        $this->stateManager->method('isManaged')->willReturn(false);
        $this->stateManager->method('isRemoved')->willReturn(false);
        $this->stateManager->method('isDetached')->willReturn(true);
        $this->stateManager->method('getEntityState')->willReturn(EntityLifecycleState::DETACHED);
        $this->stateManager->method('getScheduledInsertions')->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')->willReturn([]);
        $this->stateManager->method('getManagedEntities')->willReturn([]);
        $this->stateManager->method('isScheduledForInsertion')->willReturn(false);
        $this->stateManager->method('isScheduledForUpdate')->willReturn(false);
        $this->stateManager->method('isScheduledForDeletion')->willReturn(false);
        
        // Verify interface contracts
        $this->assertSame($entity, $this->stateManager->merge($entity));
        $this->assertFalse($this->stateManager->isNew($entity));
        $this->assertFalse($this->stateManager->isManaged($entity));
        $this->assertFalse($this->stateManager->isRemoved($entity));
        $this->assertTrue($this->stateManager->isDetached($entity));
        $this->assertSame(EntityLifecycleState::DETACHED, $this->stateManager->getEntityState($entity));
        $this->assertIsArray($this->stateManager->getScheduledInsertions());
        $this->assertIsArray($this->stateManager->getScheduledUpdates());
        $this->assertIsArray($this->stateManager->getScheduledDeletions());
        $this->assertIsArray($this->stateManager->getManagedEntities());
        $this->assertFalse($this->stateManager->isScheduledForInsertion($entity));
        $this->assertFalse($this->stateManager->isScheduledForUpdate($entity));
        $this->assertFalse($this->stateManager->isScheduledForDeletion($entity));
    }
}