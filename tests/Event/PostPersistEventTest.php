<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(PostPersistEvent::class)]
class PostPersistEventTest extends TestCase
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
        $event = new PostPersistEvent($this->entity, $this->entityManager);
        
        $this->assertEquals(DbEvents::postPersist->value, $event->getName());
        $this->assertEquals('postPersist', $event->getName());
    }

    public function testGetEntityReturnsEntity(): void
    {
        $event = new PostPersistEvent($this->entity, $this->entityManager);
        
        $this->assertSame($this->entity, $event->getEntity());
    }

    public function testGetEntityManagerReturnsEntityManager(): void
    {
        $event = new PostPersistEvent($this->entity, $this->entityManager);
        
        $this->assertSame($this->entityManager, $event->getEntityManager());
    }

    public function testPostPersistEventExtendsAbstractEntityEvent(): void
    {
        $event = new PostPersistEvent($this->entity, $this->entityManager);
        
        $this->assertInstanceOf(\MulerTech\Database\Event\AbstractEntityEvent::class, $event);
    }

    public function testConstructorWithDifferentEntities(): void
    {
        $entity1 = new stdClass();
        $entity2 = new class {};
        
        $event1 = new PostPersistEvent($entity1, $this->entityManager);
        $event2 = new PostPersistEvent($entity2, $this->entityManager);
        
        $this->assertSame($entity1, $event1->getEntity());
        $this->assertSame($entity2, $event2->getEntity());
        $this->assertNotSame($event1->getEntity(), $event2->getEntity());
    }

    public function testConstructorWithDifferentEntityManagers(): void
    {
        $entityManager1 = $this->createMock(EntityManagerInterface::class);
        $entityManager2 = $this->createMock(EntityManagerInterface::class);
        
        $event1 = new PostPersistEvent($this->entity, $entityManager1);
        $event2 = new PostPersistEvent($this->entity, $entityManager2);
        
        $this->assertSame($entityManager1, $event1->getEntityManager());
        $this->assertSame($entityManager2, $event2->getEntityManager());
        $this->assertNotSame($event1->getEntityManager(), $event2->getEntityManager());
    }

    public function testEventNameIsAlwaysPostPersist(): void
    {
        $event1 = new PostPersistEvent($this->entity, $this->entityManager);
        $event2 = new PostPersistEvent(new stdClass(), $this->createMock(EntityManagerInterface::class));
        
        $this->assertEquals('postPersist', $event1->getName());
        $this->assertEquals('postPersist', $event2->getName());
        $this->assertEquals($event1->getName(), $event2->getName());
    }

    public function testConstructorCallsParentWithCorrectDbEvent(): void
    {
        $event = new PostPersistEvent($this->entity, $this->entityManager);
        
        $this->assertEquals(DbEvents::postPersist->value, $event->getName());
    }
}