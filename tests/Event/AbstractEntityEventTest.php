<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Event;

use MulerTech\Database\Event\AbstractEntityEvent;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Event\ConcreteEntityEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(AbstractEntityEvent::class)]
class AbstractEntityEventTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private object $entity;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entity = new stdClass();
    }

    public function testConstructorSetsEventNameFromDbEvents(): void
    {
        $event = new ConcreteEntityEvent($this->entity, $this->entityManager, DbEvents::prePersist);
        
        $this->assertEquals('prePersist', $event->getName());
    }

    public function testGetEntityReturnsEntity(): void
    {
        $event = new ConcreteEntityEvent($this->entity, $this->entityManager, DbEvents::postPersist);
        
        $this->assertSame($this->entity, $event->getEntity());
    }

    public function testGetEntityManagerReturnsEntityManager(): void
    {
        $event = new ConcreteEntityEvent($this->entity, $this->entityManager, DbEvents::preUpdate);
        
        $this->assertSame($this->entityManager, $event->getEntityManager());
    }

    public function testConstructorWithDifferentDbEventTypes(): void
    {
        $testCases = [
            DbEvents::preRemove,
            DbEvents::postRemove,
            DbEvents::prePersist,
            DbEvents::postPersist,
            DbEvents::preUpdate,
            DbEvents::postUpdate,
        ];

        foreach ($testCases as $dbEvent) {
            $event = new ConcreteEntityEvent($this->entity, $this->entityManager, $dbEvent);
            $this->assertEquals($dbEvent->value, $event->getName());
        }
    }

    public function testConstructorWithDifferentEntityTypes(): void
    {
        $entities = [
            new stdClass(),
            new \DateTime(),
            new class {},
        ];

        foreach ($entities as $entity) {
            $event = new ConcreteEntityEvent($entity, $this->entityManager, DbEvents::prePersist);
            $this->assertSame($entity, $event->getEntity());
        }
    }
}
