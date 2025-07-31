<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostFlushEvent::class)]
class PostFlushEventTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testConstructorSetsEventName(): void
    {
        $event = new PostFlushEvent($this->entityManager);
        
        $this->assertEquals(DbEvents::postFlush->value, $event->getName());
        $this->assertEquals('postFlush', $event->getName());
    }

    public function testGetEntityManagerReturnsEntityManager(): void
    {
        $event = new PostFlushEvent($this->entityManager);
        
        $this->assertSame($this->entityManager, $event->getEntityManager());
    }

    public function testPostFlushEventExtendsEventDirectly(): void
    {
        $event = new PostFlushEvent($this->entityManager);
        
        $this->assertInstanceOf(\MulerTech\EventManager\Event::class, $event);
    }

    public function testConstructorWithDifferentEntityManagers(): void
    {
        $entityManager1 = $this->createMock(EntityManagerInterface::class);
        $entityManager2 = $this->createMock(EntityManagerInterface::class);
        
        $event1 = new PostFlushEvent($entityManager1);
        $event2 = new PostFlushEvent($entityManager2);
        
        $this->assertSame($entityManager1, $event1->getEntityManager());
        $this->assertSame($entityManager2, $event2->getEntityManager());
        $this->assertNotSame($event1->getEntityManager(), $event2->getEntityManager());
    }

    public function testEventNameIsAlwaysPostFlush(): void
    {
        $event1 = new PostFlushEvent($this->entityManager);
        $event2 = new PostFlushEvent($this->createMock(EntityManagerInterface::class));
        
        $this->assertEquals('postFlush', $event1->getName());
        $this->assertEquals('postFlush', $event2->getName());
        $this->assertEquals($event1->getName(), $event2->getName());
    }
}