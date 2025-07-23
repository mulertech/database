<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use DateTimeImmutable;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreRemoveEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EntityMetadata;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\State\EntityState;
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
    private readonly FlushHandler $flushHandler;
    /** @var array<string> */
    private array $processedEvents = [];

    /**
     * @param EntityManagerInterface $entityManager
     * @param StateManagerInterface $stateManager
     * @param ChangeDetector $changeDetector
     * @param RelationManager $relationManager
     * @param InsertionProcessor $insertionProcessor
     * @param UpdateProcessor $updateProcessor
     * @param DeletionProcessor $deletionProcessor
     * @param EventManager|null $eventManager
     * @param ChangeSetManager $changeSetManager
     * @param IdentityMap $identityMap
     * @param EntityProcessor $entityProcessor
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager,
        private readonly ChangeDetector $changeDetector,
        private readonly RelationManager $relationManager,
        private readonly InsertionProcessor $insertionProcessor,
        private readonly UpdateProcessor $updateProcessor,
        private readonly DeletionProcessor $deletionProcessor,
        private readonly ?EventManager $eventManager,
        private readonly ChangeSetManager $changeSetManager,
        private readonly IdentityMap $identityMap,
        private readonly EntityProcessor $entityProcessor
    ) {
        $this->flushHandler = new FlushHandler(
            $this->stateManager,
            $this->changeSetManager,
            $this->relationManager,
        );
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function persist(object $entity): void
    {
        $this->stateManager->scheduleForInsertion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->stateManager->scheduleForDeletion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->changeSetManager->detach($entity);
    }

    /**
     * @return void
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
            $this->performFlush();
        } finally {
            $this->isFlushInProgress = false;
            $this->flushHandler->reset();
        }
    }

    private function performFlush(): void
    {
        // Reset processed events at the start of the top-level flush
        $this->processedEvents = [];

        $maxIterations = 5; // Prevent infinite loops
        $iteration = 0;

        do {
            $iteration++;

            $this->flushHandler->doFlush(
                fn ($entity) => $this->processInsertion($entity),
                fn ($entity) => $this->processUpdate($entity),
                fn ($entity) => $this->processDeletion($entity)
            );

            // Handle post-flush events directly here instead of in FlushHandler
            $this->handlePostFlushEvents();

            // Check if we have new operations to process
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
            // Mark that we have post-event changes instead of recursive flush
            $this->flushHandler->markPostEventChanges();
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processInsertion(object $entity): void
    {
        $entityId = $this->extractEntityId($entity);
        if ($entityId !== null) {
            $this->stateManager->manage($entity);
            return;
        }

        $this->callEntityEvent($entity, 'prePersist');
        $this->insertionProcessor->process($entity);
        $this->stateManager->manage($entity);
        $this->updateEntityMetadata($entity);
        $this->callEntityEvent($entity, 'postPersist');
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processUpdate(object $entity): void
    {
        $changeSet = $this->changeSetManager->getChangeSet($entity);

        if ($changeSet === null || $changeSet->isEmpty()) {
            return;
        }

        // Call pre-update event
        $this->callEntityEvent($entity, 'preUpdate');
        $this->updateProcessor->process($entity, $changeSet->getChanges());
        $this->updateEntityMetadata($entity);
        $this->callEntityEvent($entity, 'postUpdate');
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processDeletion(object $entity): void
    {
        $this->callEntityEvent($entity, 'preRemove');
        $this->deletionProcessor->process($entity);

        // Only detach if entity is not already in removed state
        $currentState = $this->stateManager->getEntityState($entity);
        if ($currentState !== \MulerTech\Database\ORM\State\EntityState::REMOVED) {
            $this->stateManager->detach($entity);
        }

        $this->callEntityEvent($entity, 'postRemove');
    }

    private function callEntityEvent(object $entity, string $eventName): void
    {
        $entityId = spl_object_id($entity);
        $eventKey = $entityId . '_' . $eventName . '_' . $this->flushHandler->getFlushDepth();

        // Prevent infinite loops for the same event on the same entity at the same depth
        if (in_array($eventKey, $this->processedEvents, true)) {
            return;
        }

        $this->processedEvents[] = $eventKey;

        // Call the event method if it exists on the entity
        if (method_exists($entity, $eventName)) {
            $entity->$eventName();
        }

        // Dispatch global events if event manager is available
        if ($this->eventManager !== null) {
            $this->dispatchGlobalEvent($entity, $eventName);
        }
    }

    private function dispatchGlobalEvent(object $entity, string $eventName): void
    {
        if ($this->eventManager === null) {
            return;
        }

        match ($eventName) {
            'prePersist' => $this->eventManager->dispatch(new PrePersistEvent($entity, $this->entityManager)),
            'postPersist' => $this->eventManager->dispatch(new PostPersistEvent($entity, $this->entityManager)),
            'preUpdate' => $this->dispatchPreUpdateEvent($entity),
            'postUpdate' => $this->dispatchPostUpdateEvent($entity),
            'preRemove' => $this->dispatchPreRemoveEvent($entity),
            'postRemove' => $this->dispatchPostRemoveEvent($entity),
            default => null
        };
    }

    private function dispatchPreUpdateEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $changeSet = $this->changeSetManager->getChangeSet($entity);
        $this->eventManager->dispatch(new PreUpdateEvent($entity, $this->entityManager, $changeSet));
    }

    private function dispatchPostUpdateEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $this->eventManager->dispatch(new PostUpdateEvent($entity, $this->entityManager));

        // After PostUpdate event, check if any entities need to be processed
        $this->changeSetManager->computeChangeSets();

        // Process any new insertions that might have been scheduled
        $pendingInsertions = $this->changeSetManager->getScheduledInsertions();
        if (!empty($pendingInsertions)) {
            foreach ($pendingInsertions as $insertEntity) {
                $this->processInsertion($insertEntity);
            }
            $this->changeSetManager->clearProcessedChanges();
        }

        // Process any pending updates
        $pendingUpdates = $this->changeSetManager->getScheduledUpdates();
        if (!empty($pendingUpdates)) {
            foreach ($pendingUpdates as $updateEntity) {
                $this->processUpdate($updateEntity);
            }
            $this->changeSetManager->clearProcessedChanges();
        }
    }

    private function dispatchPreRemoveEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $this->eventManager->dispatch(new PreRemoveEvent($entity, $this->entityManager));

        // After PreRemove event, check if any entities need to be updated and process them immediately
        $this->changeSetManager->computeChangeSets();
        $pendingUpdates = $this->changeSetManager->getScheduledUpdates();
        if (!empty($pendingUpdates)) {
            foreach ($pendingUpdates as $updateEntity) {
                $this->processUpdate($updateEntity);
            }
            $this->changeSetManager->clearProcessedChanges();
        }
    }

    private function dispatchPostRemoveEvent(object $entity): void
    {
        if ($this->eventManager === null) {
            return;
        }
        $this->eventManager->dispatch(new PostRemoveEvent($entity, $this->entityManager));

        // After PostRemove event, check if any entities need to be updated and process them immediately
        $this->changeSetManager->computeChangeSets();
        $pendingUpdates = $this->changeSetManager->getScheduledUpdates();
        if (!empty($pendingUpdates)) {
            foreach ($pendingUpdates as $updateEntity) {
                $this->processUpdate($updateEntity);
            }
            $this->changeSetManager->clearProcessedChanges();
        }

        // Also check for any new insertions that might have been scheduled
        $pendingInsertions = $this->changeSetManager->getScheduledInsertions();
        if (!empty($pendingInsertions)) {
            foreach ($pendingInsertions as $insertEntity) {
                $this->processInsertion($insertEntity);
            }
            $this->changeSetManager->clearProcessedChanges();
        }
    }

    private function updateEntityMetadata(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            return;
        }

        $currentData = $this->changeDetector->extractCurrentData($entity);
        $entityId = $this->extractEntityId($entity);
        $identifier = $entityId ?? $metadata->identifier;

        // Ensure identifier is int|string as expected by EntityMetadata
        if (!is_int($identifier) && !is_string($identifier)) {
            throw new \InvalidArgumentException('Entity identifier must be int or string');
        }

        $newMetadata = new EntityMetadata(
            $metadata->className,
            $identifier,
            EntityState::MANAGED,
            $currentData,
            $metadata->loadedAt,
            new DateTimeImmutable()
        );
        $this->identityMap->updateMetadata($entity, $newMetadata);
    }

    /**
     * @param object $entity
     * @return mixed
     */
    private function extractEntityId(object $entity): mixed
    {
        return $this->entityProcessor->extractEntityId($entity);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->changeSetManager->clear();
        $this->stateManager->clear();
        $this->relationManager->clear();
        $this->processedEvents = [];
        $this->flushHandler->reset();
    }
}
