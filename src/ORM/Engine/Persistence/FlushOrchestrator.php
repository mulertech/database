<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\EventManager\EventManager;
use ReflectionException;

/**
 * Orchestrates the flush process with event handling and iteration management
 */
readonly class FlushOrchestrator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StateManagerInterface $stateManager,
        private ChangeSetManager $changeSetManager,
        private ?EventManager $eventManager,
        private EventDispatcher $eventDispatcher,
        private FlushHandler $flushHandler,
        private EntityOperationProcessor $operationProcessor,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function performFlush(): void
    {
        $this->eventDispatcher->resetProcessedEvents();

        $maxIterations = 5;
        $iteration = 0;

        do {
            $iteration++;

            $this->flushHandler->doFlush(
                fn ($entity) => $this->operationProcessor->processInsertion($entity, $this->flushHandler->getFlushDepth()),
                fn ($entity) => $this->operationProcessor->processUpdate($entity, $this->flushHandler->getFlushDepth()),
                fn ($entity) => $this->operationProcessor->processDeletion($entity, $this->flushHandler->getFlushDepth())
            );

            $this->handlePostFlushEvents();
            $hasMoreWork = $this->hasNewOperationsScheduled();

        } while ($hasMoreWork && $iteration < $maxIterations && $this->flushHandler->getFlushDepth() < 3);
    }

    private function hasNewOperationsScheduled(): bool
    {
        return !empty($this->changeSetManager->getScheduledInsertions()) ||
               !empty($this->changeSetManager->getScheduledUpdates()) ||
               !empty($this->changeSetManager->getScheduledDeletions()) ||
               !empty($this->stateManager->getScheduledDeletions());
    }

    private function handlePostFlushEvents(): void
    {
        if ($this->eventManager === null) {
            return;
        }

        // Store pre-event counts to detect changes
        $preEventInsertions = count($this->changeSetManager->getScheduledInsertions());
        $preEventUpdates = count($this->changeSetManager->getScheduledUpdates());
        $preEventDeletions = count($this->changeSetManager->getScheduledDeletions());

        // Dispatch the post-flush event
        $this->eventManager->dispatch(new PostFlushEvent($this->entityManager));

        // Check if new operations were scheduled during the event
        $postEventInsertions = count($this->changeSetManager->getScheduledInsertions());
        $postEventUpdates = count($this->changeSetManager->getScheduledUpdates());
        $postEventDeletions = count($this->changeSetManager->getScheduledDeletions());

        $hasNewChanges = $postEventInsertions > $preEventInsertions ||
                        $postEventUpdates > $preEventUpdates ||
                        $postEventDeletions > $preEventDeletions;

        if ($hasNewChanges) {
            $this->flushHandler->markPostEventChanges();
        }
    }
}
