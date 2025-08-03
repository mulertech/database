<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\ORM\ChangeSet;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\PropertyChange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PreUpdateEvent::class)]
class PreUpdateEventTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private object $entity;
    private ChangeSet $changeSet;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entity = new stdClass();
        $this->changeSet = new ChangeSet('TestEntity', [
            'name' => new PropertyChange('name', 'oldValue', 'newValue')
        ]);
    }

    public function testConstructorSetsEventName(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $this->assertEquals(DbEvents::preUpdate->value, $event->getName());
        $this->assertEquals('preUpdate', $event->getName());
    }

    public function testGetEntityReturnsEntity(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $this->assertSame($this->entity, $event->getEntity());
    }

    public function testGetEntityManagerReturnsEntityManager(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $this->assertSame($this->entityManager, $event->getEntityManager());
    }

    public function testGetEntityChangesReturnsChangeSet(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $this->assertSame($this->changeSet, $event->getEntityChanges());
    }

    public function testConstructorWithNullChangeSet(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, null);
        
        $this->assertNull($event->getEntityChanges());
        $this->assertEquals('preUpdate', $event->getName());
    }

    public function testPreUpdateEventExtendsAbstractEntityEvent(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $this->assertInstanceOf(\MulerTech\Database\Event\AbstractEntityEvent::class, $event);
    }

    public function testConstructorWithDifferentEntities(): void
    {
        $entity1 = new stdClass();
        $entity2 = new class {};
        
        $event1 = new PreUpdateEvent($entity1, $this->entityManager, $this->changeSet);
        $event2 = new PreUpdateEvent($entity2, $this->entityManager, $this->changeSet);
        
        $this->assertSame($entity1, $event1->getEntity());
        $this->assertSame($entity2, $event2->getEntity());
        $this->assertNotSame($event1->getEntity(), $event2->getEntity());
    }

    public function testConstructorWithDifferentEntityManagers(): void
    {
        $entityManager1 = $this->createMock(EntityManagerInterface::class);
        $entityManager2 = $this->createMock(EntityManagerInterface::class);
        
        $event1 = new PreUpdateEvent($this->entity, $entityManager1, $this->changeSet);
        $event2 = new PreUpdateEvent($this->entity, $entityManager2, $this->changeSet);
        
        $this->assertSame($entityManager1, $event1->getEntityManager());
        $this->assertSame($entityManager2, $event2->getEntityManager());
        $this->assertNotSame($event1->getEntityManager(), $event2->getEntityManager());
    }

    public function testConstructorWithDifferentChangeSets(): void
    {
        $changeSet1 = new ChangeSet('Entity1', [
            'field1' => new PropertyChange('field1', 'old1', 'new1')
        ]);
        $changeSet2 = new ChangeSet('Entity2', [
            'field2' => new PropertyChange('field2', 'old2', 'new2')
        ]);
        
        $event1 = new PreUpdateEvent($this->entity, $this->entityManager, $changeSet1);
        $event2 = new PreUpdateEvent($this->entity, $this->entityManager, $changeSet2);
        
        $this->assertSame($changeSet1, $event1->getEntityChanges());
        $this->assertSame($changeSet2, $event2->getEntityChanges());
        $this->assertNotSame($event1->getEntityChanges(), $event2->getEntityChanges());
    }

    public function testEventNameIsAlwaysPreUpdate(): void
    {
        $event1 = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        $event2 = new PreUpdateEvent(new stdClass(), $this->createMock(EntityManagerInterface::class), null);
        
        $this->assertEquals('preUpdate', $event1->getName());
        $this->assertEquals('preUpdate', $event2->getName());
        $this->assertEquals($event1->getName(), $event2->getName());
    }

    public function testConstructorCallsParentWithCorrectDbEvent(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $this->assertEquals(DbEvents::preUpdate->value, $event->getName());
    }

    public function testConstructorUsesReadonlyPropertyForChangeSet(): void
    {
        $event = new PreUpdateEvent($this->entity, $this->entityManager, $this->changeSet);
        
        $changeSet = $event->getEntityChanges();
        $this->assertSame($this->changeSet, $changeSet);
        
        $secondCall = $event->getEntityChanges();
        $this->assertSame($changeSet, $secondCall);
    }
}