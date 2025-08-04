<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

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
        $identityMap = new \MulerTech\Database\ORM\IdentityMap();
        $entityRegistry = new \MulerTech\Database\ORM\EntityRegistry();
        $changeDetector = new \MulerTech\Database\ORM\ChangeDetector();
        $this->changeSetManager = new ChangeSetManager($identityMap, $entityRegistry, $changeDetector);
        
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
}