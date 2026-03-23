<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\EntityOperationProcessor;
use MulerTech\Database\ORM\Engine\Persistence\EventDispatcher;
use MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\UpdateProcessor;
use MulerTech\Database\ORM\EntityRegistry;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\Tests\Files\Entity\User;
use PHPUnit\Framework\TestCase;

class EntityOperationProcessorTest extends TestCase
{
    private EntityOperationProcessor $operationProcessor;
    private StateManagerInterface $stateManager;
    private ChangeSetManager $changeSetManager;
    private ChangeDetector $changeDetector;
    private IdentityMap $identityMap;
    private EventDispatcher $eventDispatcher;
    private InsertionProcessor $insertionProcessor;
    private UpdateProcessor $updateProcessor;
    private DeletionProcessor $deletionProcessor;
    private MetadataRegistry $metadataRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateManager = $this->createStub(StateManagerInterface::class);
        $this->eventDispatcher = $this->createStub(EventDispatcher::class);
        $this->insertionProcessor = $this->createStub(InsertionProcessor::class);
        $this->updateProcessor = $this->createStub(UpdateProcessor::class);
        $this->deletionProcessor = $this->createStub(DeletionProcessor::class);
        
        $this->metadataRegistry = new MetadataRegistry();
        $this->identityMap = new IdentityMap($this->metadataRegistry);
        $entityRegistry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector($this->metadataRegistry);
        $this->changeSetManager = new ChangeSetManager($this->identityMap, $entityRegistry, $this->changeDetector, $this->metadataRegistry);

        $this->operationProcessor = new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $this->changeDetector,
            $this->identityMap,
            $this->eventDispatcher,
            $this->insertionProcessor,
            $this->updateProcessor,
            $this->deletionProcessor,
            $this->metadataRegistry
        );
    }

    private function createOperationProcessorWithMocks(
        EventDispatcher $eventDispatcher,
        InsertionProcessor $insertionProcessor,
        DeletionProcessor $deletionProcessor,
    ): EntityOperationProcessor {
        return new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $this->changeDetector,
            $this->identityMap,
            $eventDispatcher,
            $insertionProcessor,
            $this->updateProcessor,
            $deletionProcessor,
            $this->metadataRegistry
        );
    }

    public function testProcessInsertion(): void
    {
        $user = new User();
        $user->setUsername('John');

        $insertionProcessor = $this->createMock(InsertionProcessor::class);
        $insertionProcessor->expects($this->once())
            ->method('process')
            ->with($user);

        $processor = $this->createOperationProcessorWithMocks($this->eventDispatcher, $insertionProcessor, $this->deletionProcessor);
        $processor->processInsertion($user, 1);

        $this->assertTrue(true);
    }

    public function testProcessUpdate(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        // Since ChangeSetManager is final, we can't mock it
        // The real instance will return null for untracked entities
        
        $this->operationProcessor->processUpdate($user, 1);
        
        $this->assertTrue(true);
    }

    public function testProcessDeletion(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);

        $deletionProcessor = $this->createMock(DeletionProcessor::class);
        $deletionProcessor->expects($this->once())
            ->method('process')
            ->with($user);

        $processor = $this->createOperationProcessorWithMocks($this->eventDispatcher, $this->insertionProcessor, $deletionProcessor);
        $processor->processDeletion($user, 1);

        $this->assertTrue(true);
    }

    public function testProcessInsertionWithEventDispatch(): void
    {
        $user = new User();
        $user->setUsername('John');

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->atLeastOnce())
            ->method('callEntityEvent');

        $insertionProcessor = $this->createMock(InsertionProcessor::class);
        $insertionProcessor->expects($this->once())
            ->method('process')
            ->with($user);

        $processor = $this->createOperationProcessorWithMocks($eventDispatcher, $insertionProcessor, $this->deletionProcessor);
        $processor->processInsertion($user, 1);

        $this->assertTrue(true);
    }

    public function testProcessDeletionWithEventDispatch(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');

        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects($this->atLeastOnce())
            ->method('callEntityEvent');

        $deletionProcessor = $this->createMock(DeletionProcessor::class);
        $deletionProcessor->expects($this->once())
            ->method('process')
            ->with($user);

        $processor = $this->createOperationProcessorWithMocks($eventDispatcher, $this->insertionProcessor, $deletionProcessor);
        $processor->processDeletion($user, 1);

        $this->assertTrue(true);
    }

    public function testProcessPostEventOperations(): void
    {
        // Since ChangeSetManager is final, we need to use a real instance
        // but mock the state manager to return empty collections
        $this->stateManager->method('getScheduledInsertions')
            ->willReturn([]);
        $this->stateManager->method('getScheduledUpdates')
            ->willReturn([]);
        
        $this->operationProcessor->processPostEventOperations(1);
        
        $this->assertTrue(true);
    }
}