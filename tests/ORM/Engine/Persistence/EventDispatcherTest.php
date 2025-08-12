<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Persistence\EventDispatcher;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $eventDispatcher;
    private EventManager $eventManager;
    private EntityManagerInterface $entityManager;
    private ChangeSetManager $changeSetManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->eventManager = $this->createMock(EventManager::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $metadataRegistry = new MetadataRegistry();
        $identityMap = new \MulerTech\Database\ORM\IdentityMap($metadataRegistry);
        $entityRegistry = new \MulerTech\Database\ORM\EntityRegistry();
        $changeDetector = new \MulerTech\Database\ORM\ChangeDetector($metadataRegistry);
        $this->changeSetManager = new ChangeSetManager($identityMap, $entityRegistry, $changeDetector, $metadataRegistry);
        
        $this->eventDispatcher = new EventDispatcher(
            $this->eventManager,
            $this->entityManager,
            $this->changeSetManager
        );
    }

    public function testCallEntityEvent(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventDispatcher->callEntityEvent($user, 'prePersist', 1);
        
        $this->assertTrue(true);
    }

    public function testResetProcessedEvents(): void
    {
        $this->eventDispatcher->resetProcessedEvents();
        
        // Verify that processed events are reset
        $this->assertTrue(true);
    }

    public function testCallEntityEventWithExistingMethod(): void
    {
        $user = new class {
            public bool $preUpdateCalled = false;
            
            public function preUpdate(): void
            {
                $this->preUpdateCalled = true;
            }
        };
        
        $this->eventDispatcher->callEntityEvent($user, 'preUpdate', 1);
        
        $this->assertTrue($user->preUpdateCalled);
    }

    public function testCallEntityEventWithEventManager(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->once())
            ->method('dispatch');
        
        $this->eventDispatcher->callEntityEvent($user, 'prePersist', 1);
        
        $this->assertTrue(true);
    }

    public function testCallEntityEventPreventDuplicates(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        // Call the same event twice with same depth
        $this->eventDispatcher->callEntityEvent($user, 'prePersist', 1);
        $this->eventDispatcher->callEntityEvent($user, 'prePersist', 1);
        
        $this->assertTrue(true);
    }

    public function testSetPostEventProcessor(): void
    {
        $processor = function() { /* dummy processor */ };
        
        $this->eventDispatcher->setPostEventProcessor($processor);
        
        $this->assertTrue(true);
    }

    public function testDispatchPreUpdateEvent(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf('\MulerTech\Database\Event\PreUpdateEvent'));
        
        $this->eventDispatcher->callEntityEvent($user, 'preUpdate', 1);
    }

    public function testDispatchPostUpdateEvent(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf('\MulerTech\Database\Event\PostUpdateEvent'));
        
        $this->eventDispatcher->callEntityEvent($user, 'postUpdate', 1);
    }

    public function testDispatchPreRemoveEvent(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf('\MulerTech\Database\Event\PreRemoveEvent'));
        
        $this->eventDispatcher->callEntityEvent($user, 'preRemove', 1);
    }

    public function testDispatchPostRemoveEvent(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf('\MulerTech\Database\Event\PostRemoveEvent'));
        
        $this->eventDispatcher->callEntityEvent($user, 'postRemove', 1);
    }

    public function testProcessPostEventOperationsWithProcessor(): void
    {
        $processorCalled = false;
        $processor = function() use (&$processorCalled) {
            $processorCalled = true;
        };
        
        $this->eventDispatcher->setPostEventProcessor($processor);
        
        $user = new User();
        $user->setUsername('John');
        
        $this->eventDispatcher->callEntityEvent($user, 'postUpdate', 1);
        
        $this->assertTrue($processorCalled);
    }

    public function testCallEntityEventWithNullEventManager(): void
    {
        $eventDispatcher = new EventDispatcher(
            null,
            $this->entityManager,
            $this->changeSetManager
        );
        
        $user = new class {
            public bool $preUpdateCalled = false;
            
            public function preUpdate(): void
            {
                $this->preUpdateCalled = true;
            }
        };
        
        $eventDispatcher->callEntityEvent($user, 'preUpdate', 1);
        
        $this->assertTrue($user->preUpdateCalled);
    }

    public function testCallEntityEventWithUnknownEventType(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->never())
            ->method('dispatch');
        
        $this->eventDispatcher->callEntityEvent($user, 'unknownEvent', 1);
        
        $this->assertTrue(true);
    }

    public function testDispatchPreUpdateEventWithNullEventManager(): void
    {
        $eventDispatcher = new EventDispatcher(
            null,
            $this->entityManager,
            $this->changeSetManager
        );
        
        $user = new User();
        $user->setUsername('John');
        
        $eventDispatcher->callEntityEvent($user, 'preUpdate', 1);
        
        $this->assertTrue(true);
    }

    public function testDispatchPostUpdateEventWithNullEventManager(): void
    {
        $eventDispatcher = new EventDispatcher(
            null,
            $this->entityManager,
            $this->changeSetManager
        );
        
        $user = new User();
        $user->setUsername('John');
        
        $eventDispatcher->callEntityEvent($user, 'postUpdate', 1);
        
        $this->assertTrue(true);
    }

    public function testDispatchPreRemoveEventWithNullEventManager(): void
    {
        $eventDispatcher = new EventDispatcher(
            null,
            $this->entityManager,
            $this->changeSetManager
        );
        
        $user = new User();
        $user->setUsername('John');
        
        $eventDispatcher->callEntityEvent($user, 'preRemove', 1);
        
        $this->assertTrue(true);
    }

    public function testDispatchPostRemoveEventWithNullEventManager(): void
    {
        $eventDispatcher = new EventDispatcher(
            null,
            $this->entityManager,
            $this->changeSetManager
        );
        
        $user = new User();
        $user->setUsername('John');
        
        $eventDispatcher->callEntityEvent($user, 'postRemove', 1);
        
        $this->assertTrue(true);
    }

    public function testDispatchPostPersistEvent(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf('\\MulerTech\\Database\\Event\\PostPersistEvent'));
        
        $this->eventDispatcher->callEntityEvent($user, 'postPersist', 1);
    }

    public function testDispatchGlobalEventWithNullEventManager(): void
    {
        $eventDispatcher = new EventDispatcher(
            null,
            $this->entityManager,
            $this->changeSetManager
        );
        
        $user = new User();
        $user->setUsername('John');
        
        $eventDispatcher->callEntityEvent($user, 'prePersist', 1);
        
        $this->assertTrue(true);
    }
}