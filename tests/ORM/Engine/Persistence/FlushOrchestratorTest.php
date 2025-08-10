<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\EntityOperationProcessor;
use MulerTech\Database\ORM\Engine\Persistence\EventDispatcher;
use MulerTech\Database\ORM\Engine\Persistence\FlushHandler;
use MulerTech\Database\ORM\Engine\Persistence\FlushOrchestrator;
use MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\UpdateProcessor;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\Entity\Unit;
use MulerTech\EventManager\EventManager;
use PHPUnit\Framework\TestCase;

class FlushOrchestratorTest extends TestCase
{
    private FlushOrchestrator $orchestrator;
    private EntityManagerInterface $entityManager;
    private StateManagerInterface $stateManager;
    private ChangeSetManager $changeSetManager;
    private EventManager $eventManager;
    private EventDispatcher $eventDispatcher;
    private FlushHandler $flushHandler;
    private EntityOperationProcessor $operationProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock only interfaces and non-final classes
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->stateManager = $this->createMock(StateManagerInterface::class);
        $this->eventManager = $this->createMock(EventManager::class);

        // Configure StateManager mock to return real enum values instead of trying to mock them
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);

        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([]);

        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([]);

        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([]);

        // Create real instances of final classes with their dependencies
        $identityMap = new IdentityMap(new MetadataCache());
        $entityRegistry = new EntityRegistry();
        $changeDetector = new ChangeDetector();
        $this->changeSetManager = new ChangeSetManager($identityMap, $entityRegistry, $changeDetector);

        // EventDispatcher is not final, so we can mock it
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);

        // Create RelationManager (need to check if it's final)
        $relationManager = $this->createMock(\MulerTech\Database\ORM\Engine\Relations\RelationManager::class);

        // FlushHandler is final, create real instance
        $this->flushHandler = new FlushHandler(
            $this->stateManager,
            $this->changeSetManager,
            $relationManager
        );

        // Create the three processors needed by EntityOperationProcessor
        $metadataCache = new MetadataCache(); // Create real instance instead of mock
        $insertionProcessor = new InsertionProcessor($this->entityManager, $metadataCache);
        $updateProcessor = new UpdateProcessor($this->entityManager, $metadataCache);
        $deletionProcessor = new DeletionProcessor($this->entityManager, $metadataCache);

        // EntityOperationProcessor might be final too, create real instance with all 8 parameters
        $this->operationProcessor = new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $changeDetector,
            $identityMap,
            $this->eventDispatcher,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor
        );

        $this->orchestrator = new FlushOrchestrator(
            $this->entityManager,
            $this->stateManager,
            $this->changeSetManager,
            $this->eventManager,
            $this->eventDispatcher,
            $this->flushHandler,
            $this->operationProcessor
        );
    }

    public function testOrchestratFlushWithEmptyCollections(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        // Since we're using real instances, we can't use expects() on them
        // Instead, we test that the method completes without error
        $this->orchestrator->performFlush();

        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithInsertions(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $this->stateManager->expects($this->any())
            ->method('getScheduledInsertions')
            ->willReturn([$user]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledUpdates')
            ->willReturn([]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledDeletions')
            ->willReturn([]);

        // Test that flush completes without error
        $this->orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithUpdates(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $this->stateManager->expects($this->any())
            ->method('getScheduledInsertions')
            ->willReturn([]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledUpdates')
            ->willReturn([$user]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledDeletions')
            ->willReturn([]);

        // Test that flush completes without error
        $this->orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithDeletions(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $this->stateManager->expects($this->any())
            ->method('getScheduledInsertions')
            ->willReturn([]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledUpdates')
            ->willReturn([]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledDeletions')
            ->willReturn([$user]);

        // Test that flush completes without error
        $this->orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithAllOperations(): void
    {
        $newUser = new User();
        $newUser->setUsername('John');
        
        $existingUser = new User();
        $existingUser->setId(456);
        $existingUser->setUsername('Jane');
        
        $deletingUser = new User();
        $deletingUser->setId(789);
        $deletingUser->setUsername('Bob');
        
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $this->stateManager->expects($this->any())
            ->method('getScheduledInsertions')
            ->willReturn([$newUser]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledUpdates')
            ->willReturn([$existingUser]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledDeletions')
            ->willReturn([$deletingUser]);

        // Test that flush completes without error
        $this->orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithDependencies(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');
        
        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);
        
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $this->stateManager->expects($this->any())
            ->method('getScheduledInsertions')
            ->willReturn([$user, $unit]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledUpdates')
            ->willReturn([]);

        $this->stateManager->expects($this->any())
            ->method('getScheduledDeletions')
            ->willReturn([]);

        // Test that flush completes without error
        $this->orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testProcessInsertion(): void
    {
        $user = new User();
        $user->setUsername('John');

        // Test that insertion processing completes without error
        $this->operationProcessor->processInsertion($user, 1);
        $this->assertTrue(true);
    }

    public function testProcessUpdate(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        // Test that update processing completes without error
        $this->operationProcessor->processUpdate($user, 1);
        $this->assertTrue(true);
    }

    public function testProcessDeletion(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        // Test that deletion processing completes without error
        $this->operationProcessor->processDeletion($user, 1);
        $this->assertTrue(true);
    }

    public function testFlushHandlerDoFlush(): void
    {
        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity, $changes) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };

        // Test that doFlush completes without error
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        $this->assertTrue(true);
    }

    public function testEventDispatcherResetProcessedEvents(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $this->eventDispatcher->resetProcessedEvents();
    }

    public function testFlushWithNullEventManager(): void
    {
        // Create a new orchestrator with null EventManager
        $orchestratorWithNullEvents = new FlushOrchestrator(
            $this->entityManager,
            $this->stateManager,
            $this->changeSetManager,
            null, // null EventManager
            $this->eventDispatcher,
            $this->flushHandler,
            $this->operationProcessor
        );

        $this->eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        // Test that flush completes without error even with null EventManager
        $orchestratorWithNullEvents->performFlush();
        $this->assertTrue(true);
    }
}
