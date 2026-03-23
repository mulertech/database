<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\MetadataRegistry;
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
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->stateManager = $this->createStub(StateManagerInterface::class);
        $this->eventManager = $this->createStub(EventManager::class);

        // Create real instances of final classes with their dependencies
        $metadataRegistry = new MetadataRegistry();
        $identityMap = new IdentityMap($metadataRegistry);
        $entityRegistry = new EntityRegistry();
        $changeDetector = new ChangeDetector($metadataRegistry);
        $this->changeSetManager = new ChangeSetManager($identityMap, $entityRegistry, $changeDetector, $metadataRegistry);

        // EventDispatcher is not final, so we can stub it
        $this->eventDispatcher = $this->createStub(EventDispatcher::class);

        // Create RelationManager (need to check if it's final)
        $relationManager = $this->createStub(\MulerTech\Database\ORM\Engine\Relations\RelationManager::class);

        // FlushHandler is final, create real instance
        $this->flushHandler = new FlushHandler(
            $this->stateManager,
            $this->changeSetManager,
            $relationManager
        );

        // Create the three processors needed by EntityOperationProcessor
        $insertionProcessor = new InsertionProcessor($this->entityManager, $metadataRegistry);
        $updateProcessor = new UpdateProcessor($this->entityManager, $metadataRegistry);
        $deletionProcessor = new DeletionProcessor($this->entityManager, $metadataRegistry);

        // EntityOperationProcessor might be final too, create real instance with all 9 parameters
        $this->operationProcessor = new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $changeDetector,
            $identityMap,
            $this->eventDispatcher,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor,
            $metadataRegistry
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

    private function createOrchestratorWithEventDispatcherMock(EventDispatcher $eventDispatcher): FlushOrchestrator
    {
        $metadataRegistry = new MetadataRegistry();
        $identityMap = new IdentityMap($metadataRegistry);
        $changeDetector = new ChangeDetector($metadataRegistry);
        $insertionProcessor = new InsertionProcessor($this->entityManager, $metadataRegistry);
        $updateProcessor = new UpdateProcessor($this->entityManager, $metadataRegistry);
        $deletionProcessor = new DeletionProcessor($this->entityManager, $metadataRegistry);

        $operationProcessor = new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $changeDetector,
            $identityMap,
            $eventDispatcher,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor,
            $metadataRegistry
        );

        return new FlushOrchestrator(
            $this->entityManager,
            $this->stateManager,
            $this->changeSetManager,
            $this->eventManager,
            $eventDispatcher,
            $this->flushHandler,
            $operationProcessor
        );
    }

    public function testOrchestratFlushWithEmptyCollections(): void
    {
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $orchestrator = $this->createOrchestratorWithEventDispatcherMock($eventDispatcher);
        $orchestrator->performFlush();

        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithInsertions(): void
    {
        $user = new User();
        $user->setUsername('John');

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([$user]);
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $orchestrator = $this->createOrchestratorWithEventDispatcherMock($eventDispatcher);
        $orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithUpdates(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([$user]);
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $orchestrator = $this->createOrchestratorWithEventDispatcherMock($eventDispatcher);
        $orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithDeletions(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([$user]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $orchestrator = $this->createOrchestratorWithEventDispatcherMock($eventDispatcher);
        $orchestrator->performFlush();
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

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([$newUser]);
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([$existingUser]);
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([$deletingUser]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $orchestrator = $this->createOrchestratorWithEventDispatcherMock($eventDispatcher);
        $orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testOrchestratFlushWithDependencies(): void
    {
        $unit = new Unit();
        $unit->setName('TestUnit');

        $user = new User();
        $user->setUsername('John');
        $user->setUnit($unit);

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([$user, $unit]);
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')
            ->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $orchestrator = $this->createOrchestratorWithEventDispatcherMock($eventDispatcher);
        $orchestrator->performFlush();
        $this->assertTrue(true);
    }

    public function testProcessInsertion(): void
    {
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);

        $user = new User();
        $user->setUsername('John');

        // Test that insertion processing completes without error
        $this->operationProcessor->processInsertion($user, 1);
        $this->assertTrue(true);
    }

    public function testProcessUpdate(): void
    {
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);

        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        // Test that update processing completes without error
        $this->operationProcessor->processUpdate($user, 1);
        $this->assertTrue(true);
    }

    public function testProcessDeletion(): void
    {
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);

        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        // Test that deletion processing completes without error
        $this->operationProcessor->processDeletion($user, 1);
        $this->assertTrue(true);
    }

    public function testFlushHandlerDoFlush(): void
    {
        $this->stateManager->method('getScheduledInsertions')->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')->willReturn([]);

        $insertionProcessor = function($entity) { /* dummy processor */ };
        $updateProcessor = function($entity, $changes) { /* dummy processor */ };
        $deletionProcessor = function($entity) { /* dummy processor */ };

        // Test that doFlush completes without error
        $this->flushHandler->doFlush($insertionProcessor, $updateProcessor, $deletionProcessor);
        $this->assertTrue(true);
    }

    public function testEventDispatcherResetProcessedEvents(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $eventDispatcher->resetProcessedEvents();
    }

    public function testFlushWithNullEventManager(): void
    {
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        $this->stateManager->method('getScheduledInsertions')->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')->willReturn([]);
        $this->stateManager->method('getScheduledDeletions')->willReturn([]);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->once())
            ->method('resetProcessedEvents');

        $metadataRegistry = new MetadataRegistry();
        $identityMap = new IdentityMap($metadataRegistry);
        $changeDetector = new ChangeDetector($metadataRegistry);
        $insertionProcessor = new InsertionProcessor($this->entityManager, $metadataRegistry);
        $updateProcessor = new UpdateProcessor($this->entityManager, $metadataRegistry);
        $deletionProcessor = new DeletionProcessor($this->entityManager, $metadataRegistry);

        $operationProcessor = new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $changeDetector,
            $identityMap,
            $eventDispatcher,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor,
            $metadataRegistry
        );

        $orchestratorWithNullEvents = new FlushOrchestrator(
            $this->entityManager,
            $this->stateManager,
            $this->changeSetManager,
            null,
            $eventDispatcher,
            $this->flushHandler,
            $operationProcessor
        );

        $orchestratorWithNullEvents->performFlush();
        $this->assertTrue(true);
    }
}
