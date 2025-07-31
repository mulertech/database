<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PrePersistEvent::class)]
class PrePersistEventTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private object $entity;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entity = new stdClass();
    }

    public function testConstructorSetsEventName(): void
    {
        $event = new PrePersistEvent($this->entity, $this->entityManager);
        
        $this->assertEquals(DbEvents::prePersist->value, $event->getName());
        $this->assertEquals('prePersist', $event->getName());
    }

    public function testGetEntityReturnsEntity(): void
    {
        $event = new PrePersistEvent($this->entity, $this->entityManager);
        
        $this->assertSame($this->entity, $event->getEntity());
    }

    public function testGetEntityManagerReturnsEntityManager(): void
    {
        $event = new PrePersistEvent($this->entity, $this->entityManager);
        
        $this->assertSame($this->entityManager, $event->getEntityManager());
    }

    public function testPrePersistEventExtendsAbstractEntityEvent(): void
    {
        $event = new PrePersistEvent($this->entity, $this->entityManager);
        
        $this->assertInstanceOf(\MulerTech\Database\Event\AbstractEntityEvent::class, $event);
    }

    public function testConstructorWithDifferentEntities(): void
    {
        $entity1 = new stdClass();
        $entity2 = new class {};
        
        $event1 = new PrePersistEvent($entity1, $this->entityManager);
        $event2 = new PrePersistEvent($entity2, $this->entityManager);
        
        $this->assertSame($entity1, $event1->getEntity());
        $this->assertSame($entity2, $event2->getEntity());
        $this->assertNotSame($event1->getEntity(), $event2->getEntity());
    }

    public function testConstructorWithDifferentEntityManagers(): void
    {
        $entityManager1 = $this->createMock(EntityManagerInterface::class);
        $entityManager2 = $this->createMock(EntityManagerInterface::class);
        
        $event1 = new PrePersistEvent($this->entity, $entityManager1);
        $event2 = new PrePersistEvent($this->entity, $entityManager2);
        
        $this->assertSame($entityManager1, $event1->getEntityManager());
        $this->assertSame($entityManager2, $event2->getEntityManager());
        $this->assertNotSame($event1->getEntityManager(), $event2->getEntityManager());
    }

    public function testEventNameIsAlwaysPrePersist(): void
    {
        $event1 = new PrePersistEvent($this->entity, $this->entityManager);
        $event2 = new PrePersistEvent(new stdClass(), $this->createMock(EntityManagerInterface::class));
        
        $this->assertEquals('prePersist', $event1->getName());
        $this->assertEquals('prePersist', $event2->getName());
        $this->assertEquals($event1->getName(), $event2->getName());
    }

    public function testConstructorCallsParentWithCorrectDbEvent(): void
    {
        $event = new PrePersistEvent($this->entity, $this->entityManager);
        
        $this->assertEquals(DbEvents::prePersist->value, $event->getName());
    }
}