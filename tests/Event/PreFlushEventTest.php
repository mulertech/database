<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PreFlushEvent;
use MulerTech\Database\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreFlushEvent::class)]
class PreFlushEventTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testConstructorSetsEventName(): void
    {
        $event = new PreFlushEvent($this->entityManager);
        
        $this->assertEquals(DbEvents::preFlush->value, $event->getName());
        $this->assertEquals('preFlush', $event->getName());
    }

    public function testGetEntityManagerReturnsEntityManager(): void
    {
        $event = new PreFlushEvent($this->entityManager);
        
        $this->assertSame($this->entityManager, $event->getEntityManager());
    }

    public function testPreFlushEventExtendsEventDirectly(): void
    {
        $event = new PreFlushEvent($this->entityManager);
        
        $this->assertInstanceOf(\MulerTech\EventManager\Event::class, $event);
    }

    public function testConstructorWithDifferentEntityManagers(): void
    {
        $entityManager1 = $this->createMock(EntityManagerInterface::class);
        $entityManager2 = $this->createMock(EntityManagerInterface::class);
        
        $event1 = new PreFlushEvent($entityManager1);
        $event2 = new PreFlushEvent($entityManager2);
        
        $this->assertSame($entityManager1, $event1->getEntityManager());
        $this->assertSame($entityManager2, $event2->getEntityManager());
        $this->assertNotSame($event1->getEntityManager(), $event2->getEntityManager());
    }

    public function testEventNameIsAlwaysPreFlush(): void
    {
        $event1 = new PreFlushEvent($this->entityManager);
        $event2 = new PreFlushEvent($this->createMock(EntityManagerInterface::class));
        
        $this->assertEquals('preFlush', $event1->getName());
        $this->assertEquals('preFlush', $event2->getName());
        $this->assertEquals($event1->getName(), $event2->getName());
    }
}