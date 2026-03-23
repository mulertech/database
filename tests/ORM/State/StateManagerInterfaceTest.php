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

    private function getStateManager(): StateManagerInterface
    {
        if (!isset($this->stateManager)) {
            $this->stateManager = $this->createMock(StateManagerInterface::class);
        }
        return $this->stateManager;
    }

    public function testMergeMethod(): void
    {
        $entity = new User();
        $expectedEntity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('merge')
            ->with($entity)
            ->willReturn($expectedEntity);

        $result = $this->getStateManager()->merge($entity);

        $this->assertSame($expectedEntity, $result);
    }

    public function testIsNewMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isNew')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isNew($entity));
    }

    public function testIsManagedMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isManaged')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isManaged($entity));
    }

    public function testIsRemovedMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isRemoved')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isRemoved($entity));
    }

    public function testIsDetachedMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isDetached')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isDetached($entity));
    }

    public function testManageMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('manage')
            ->with($entity);
        
        $this->getStateManager()->manage($entity);
    }

    public function testScheduleForInsertionMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('scheduleForInsertion')
            ->with($entity);
        
        $this->getStateManager()->scheduleForInsertion($entity);
    }

    public function testScheduleForUpdateMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('scheduleForUpdate')
            ->with($entity);
        
        $this->getStateManager()->scheduleForUpdate($entity);
    }

    public function testScheduleForDeletionMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('scheduleForDeletion')
            ->with($entity);
        
        $this->getStateManager()->scheduleForDeletion($entity);
    }

    public function testDetachMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('detach')
            ->with($entity);
        
        $this->getStateManager()->detach($entity);
    }

    public function testGetEntityStateMethod(): void
    {
        $entity = new User();
        $expectedState = EntityLifecycleState::MANAGED;

        $this->getStateManager()->expects($this->once())
            ->method('getEntityState')
            ->with($entity)
            ->willReturn($expectedState);

        $result = $this->getStateManager()->getEntityState($entity);

        $this->assertSame($expectedState, $result);
    }

    public function testAddInsertionDependencyMethod(): void
    {
        $dependent = new User();
        $dependency = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('addInsertionDependency')
            ->with($dependent, $dependency);
        
        $this->getStateManager()->addInsertionDependency($dependent, $dependency);
    }

    public function testGetScheduledInsertionsMethod(): void
    {
        $entity1 = new User();
        $entity2 = new User();
        $expectedEntities = [
            spl_object_id($entity1) => $entity1,
            spl_object_id($entity2) => $entity2
        ];

        $stateManager = $this->createStub(StateManagerInterface::class);
        $stateManager->method('getScheduledInsertions')
            ->willReturn($expectedEntities);

        $result = $stateManager->getScheduledInsertions();

        $this->assertSame($expectedEntities, $result);
    }

    public function testGetScheduledUpdatesMethod(): void
    {
        $entity = new User();
        $expectedEntities = [spl_object_id($entity) => $entity];

        $stateManager = $this->createStub(StateManagerInterface::class);
        $stateManager->method('getScheduledUpdates')
            ->willReturn($expectedEntities);

        $result = $stateManager->getScheduledUpdates();

        $this->assertSame($expectedEntities, $result);
    }

    public function testGetScheduledDeletionsMethod(): void
    {
        $entity = new User();
        $expectedEntities = [spl_object_id($entity) => $entity];

        $stateManager = $this->createStub(StateManagerInterface::class);
        $stateManager->method('getScheduledDeletions')
            ->willReturn($expectedEntities);

        $result = $stateManager->getScheduledDeletions();

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

        $stateManager = $this->createStub(StateManagerInterface::class);
        $stateManager->method('getManagedEntities')
            ->willReturn($expectedEntities);

        $result = $stateManager->getManagedEntities();

        $this->assertSame($expectedEntities, $result);
    }

    public function testIsScheduledForInsertionMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isScheduledForInsertion')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isScheduledForInsertion($entity));
    }

    public function testIsScheduledForUpdateMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isScheduledForUpdate')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isScheduledForUpdate($entity));
    }

    public function testIsScheduledForDeletionMethod(): void
    {
        $entity = new User();

        $this->getStateManager()->expects($this->once())
            ->method('isScheduledForDeletion')
            ->with($entity)
            ->willReturn(true);

        $this->assertTrue($this->getStateManager()->isScheduledForDeletion($entity));
    }

    public function testMarkAsPersistedMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('markAsPersisted')
            ->with($entity);
        
        $this->getStateManager()->markAsPersisted($entity);
    }

    public function testMarkAsRemovedMethod(): void
    {
        $entity = new User();
        
        $this->getStateManager()->expects($this->once())
            ->method('markAsRemoved')
            ->with($entity);
        
        $this->getStateManager()->markAsRemoved($entity);
    }

    public function testClearMethod(): void
    {
        $this->getStateManager()->expects($this->once())
            ->method('clear');
        
        $this->getStateManager()->clear();
    }

    public function testInterfaceContractsAreSatisfied(): void
    {
        $entity = new User();

        $stateManager = $this->createStub(StateManagerInterface::class);

        // Test that all methods can be called without errors
        $stateManager->method('merge')->willReturn($entity);
        $stateManager->method('isNew')->willReturn(false);
        $stateManager->method('isManaged')->willReturn(false);
        $stateManager->method('isRemoved')->willReturn(false);
        $stateManager->method('isDetached')->willReturn(true);
        $stateManager->method('getEntityState')->willReturn(EntityLifecycleState::DETACHED);
        $stateManager->method('getScheduledInsertions')->willReturn([]);
        $stateManager->method('getScheduledUpdates')->willReturn([]);
        $stateManager->method('getScheduledDeletions')->willReturn([]);
        $stateManager->method('getManagedEntities')->willReturn([]);
        $stateManager->method('isScheduledForInsertion')->willReturn(false);
        $stateManager->method('isScheduledForUpdate')->willReturn(false);
        $stateManager->method('isScheduledForDeletion')->willReturn(false);

        // Verify interface contracts
        $this->assertSame($entity, $stateManager->merge($entity));
        $this->assertFalse($stateManager->isNew($entity));
        $this->assertFalse($stateManager->isManaged($entity));
        $this->assertFalse($stateManager->isRemoved($entity));
        $this->assertTrue($stateManager->isDetached($entity));
        $this->assertSame(EntityLifecycleState::DETACHED, $stateManager->getEntityState($entity));
        $this->assertIsArray($stateManager->getScheduledInsertions());
        $this->assertIsArray($stateManager->getScheduledUpdates());
        $this->assertIsArray($stateManager->getScheduledDeletions());
        $this->assertIsArray($stateManager->getManagedEntities());
        $this->assertFalse($stateManager->isScheduledForInsertion($entity));
        $this->assertFalse($stateManager->isScheduledForUpdate($entity));
        $this->assertFalse($stateManager->isScheduledForDeletion($entity));
    }
}