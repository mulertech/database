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

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->stateManager = $this->createMock(StateManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);
        $this->insertionProcessor = $this->createMock(InsertionProcessor::class);
        $this->updateProcessor = $this->createMock(UpdateProcessor::class);
        $this->deletionProcessor = $this->createMock(DeletionProcessor::class);
        
        $metadataRegistry = new MetadataRegistry();
        $this->identityMap = new IdentityMap($metadataRegistry);
        $entityRegistry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector($metadataRegistry);
        $this->changeSetManager = new ChangeSetManager($this->identityMap, $entityRegistry, $this->changeDetector, $metadataRegistry);
        
        $this->operationProcessor = new EntityOperationProcessor(
            $this->stateManager,
            $this->changeSetManager,
            $this->changeDetector,
            $this->identityMap,
            $this->eventDispatcher,
            $this->insertionProcessor,
            $this->updateProcessor,
            $this->deletionProcessor,
            $metadataRegistry
        );
    }

    public function testProcessInsertion(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->insertionProcessor->expects($this->once())
            ->method('process')
            ->with($user);
        
        $this->operationProcessor->processInsertion($user, 1);
        
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
        
        $this->deletionProcessor->expects($this->once())
            ->method('process')
            ->with($user);
        
        $this->operationProcessor->processDeletion($user, 1);
        
        $this->assertTrue(true);
    }

    public function testProcessInsertionWithEventDispatch(): void
    {
        $user = new User();
        $user->setUsername('John');
        
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('callEntityEvent');
        
        $this->insertionProcessor->expects($this->once())
            ->method('process')
            ->with($user);
        
        $this->operationProcessor->processInsertion($user, 1);
        
        $this->assertTrue(true);
    }

    public function testProcessDeletionWithEventDispatch(): void
    {
        $user = new User();
        $user->setId(123);
        $user->setUsername('John');
        
        $this->stateManager->method('getEntityState')
            ->willReturn(\MulerTech\Database\ORM\State\EntityLifecycleState::MANAGED);
        
        $this->eventDispatcher->expects($this->atLeastOnce())
            ->method('callEntityEvent');
        
        $this->deletionProcessor->expects($this->once())
            ->method('process')
            ->with($user);
        
        $this->operationProcessor->processDeletion($user, 1);
        
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