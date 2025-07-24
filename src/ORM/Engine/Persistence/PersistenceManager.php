<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\EventManager\EventManager;
use ReflectionException;

/**
 * Class PersistenceManager
 *
 * Main manager for entity persistence
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PersistenceManager
{
    private bool $isFlushInProgress = false;
    private readonly StateManagerInterface $stateManager;
    private readonly ChangeSetManager $changeSetManager;
    private readonly RelationManager $relationManager;
    private readonly EventDispatcher $eventDispatcher;
    private readonly FlushOrchestrator $flushOrchestrator;
    private readonly FlushHandler $flushHandler;

    public function __construct(
        EntityManagerInterface $entityManager,
        StateManagerInterface $stateManager,
        ChangeDetector $changeDetector,
        RelationManager $relationManager,
        InsertionProcessor $insertionProcessor,
        UpdateProcessor $updateProcessor,
        DeletionProcessor $deletionProcessor,
        ?EventManager $eventManager,
        ChangeSetManager $changeSetManager,
        IdentityMap $identityMap,
    ) {
        $this->stateManager = $stateManager;
        $this->changeSetManager = $changeSetManager;
        $this->relationManager = $relationManager;

        $this->flushHandler = new FlushHandler(
            $stateManager,
            $changeSetManager,
            $relationManager,
        );

        $this->eventDispatcher = new EventDispatcher(
            $eventManager,
            $entityManager,
            $changeSetManager
        );

        $operationProcessor = new EntityOperationProcessor(
            $stateManager,
            $changeSetManager,
            $changeDetector,
            $identityMap,
            $this->eventDispatcher,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor
        );

        $this->flushOrchestrator = new FlushOrchestrator(
            $entityManager,
            $stateManager,
            $changeSetManager,
            $eventManager,
            $this->eventDispatcher,
            $this->flushHandler,
            $operationProcessor
        );

        // Configure the event dispatcher to handle post-event operations
        $this->eventDispatcher->setPostEventProcessor(
            fn () => $operationProcessor->processPostEventOperations($this->flushHandler->getFlushDepth())
        );
    }

    public function persist(object $entity): void
    {
        $this->stateManager->scheduleForInsertion($entity);
    }

    public function remove(object $entity): void
    {
        $this->stateManager->scheduleForDeletion($entity);
    }

    public function detach(object $entity): void
    {
        $this->changeSetManager->detach($entity);
    }

    /**
     * @throws ReflectionException
     */
    public function flush(): void
    {
        if ($this->isFlushInProgress) {
            return;
        }

        $this->isFlushInProgress = true;

        try {
            $this->relationManager->startFlushCycle();
            $this->flushOrchestrator->performFlush();
        } finally {
            $this->isFlushInProgress = false;
            $this->flushHandler->reset();
        }
    }

    public function clear(): void
    {
        $this->changeSetManager->clear();
        $this->stateManager->clear();
        $this->relationManager->clear();
        $this->eventDispatcher->resetProcessedEvents();
        $this->flushHandler->reset();
    }
}
