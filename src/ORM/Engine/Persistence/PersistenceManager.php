<?php

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\ORM\Engine\EntityState\EntityChangeTracker;
use MulerTech\Database\ORM\Engine\EntityState\EntityStateManager;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\EventManager\EventManager;
use ReflectionException;

/**
 * Main manager for entity persistence
 *
 * @package MulerTech\Database\ORM\Engine\Persistence
 * @author Sébastien Muler
 */
class PersistenceManager
{
    /**
     * @var bool
     */
    private bool $isFlushInProgress = false;

    /**
     * @var array<int, array<string>>
     */
    private array $eventCalled = [];

    /**
     * @var int
     */
    private int $flushDepth = 0;

    /**
     * @var int
     */
    private const MAX_FLUSH_DEPTH = 10;

    /**
     * @param EntityManagerInterface $entityManager
     * @param EntityStateManager $stateManager
     * @param EntityChangeTracker $changeTracker
     * @param RelationManager $relationManager
     * @param InsertionProcessor $insertionProcessor
     * @param UpdateProcessor $updateProcessor
     * @param DeletionProcessor $deletionProcessor
     * @param EventManager|null $eventManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityStateManager $stateManager,
        private readonly EntityChangeTracker $changeTracker,
        private readonly RelationManager $relationManager,
        private readonly InsertionProcessor $insertionProcessor,
        private readonly UpdateProcessor $updateProcessor,
        private readonly DeletionProcessor $deletionProcessor,
        private readonly ?EventManager $eventManager = null
    ) {
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function persist(object $entity): void
    {
        if (!$this->stateManager->isManaged($entity)) {
            $this->stateManager->manage($entity);
        }

        if ($this->getId($entity) === null && !$this->stateManager->isScheduledForInsertion($entity)) {
            $this->dispatchPrePersistEvent($entity);
            $this->stateManager->scheduleForInsertion($entity);
        }
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
        $this->stateManager->detach($entity);
        $this->changeTracker->detach($entity);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        // Force compute changes for managed entities before checking pending changes
        $this->computeAllChanges();

        if (!$this->stateManager->hasPendingChanges()) {
            return;
        }

        // Prevent infinite recursion
        if ($this->flushDepth >= self::MAX_FLUSH_DEPTH) {
            throw new \RuntimeException('Maximum flush depth exceeded. This usually indicates a circular dependency in event listeners.');
        }

        $this->flushDepth++;
        $wasFlushInProgress = $this->isFlushInProgress;
        $this->isFlushInProgress = true;

        try {
            $this->prepareForFlush();

            if ($this->flushDepth === 1) {
                $this->entityManager->getPdm()->beginTransaction();
            }

            try {
                $this->executeInsertions();
                $this->executeUpdates();
                $this->executeDeletions();
                $this->executeRelationChanges();

                if ($this->flushDepth === 1) {
                    $this->entityManager->getPdm()->commit();
                    $this->dispatchPostFlushEvent();
                }
            } catch (\Exception $e) {
                if ($this->flushDepth === 1 && $this->entityManager->getPdm()->inTransaction()) {
                    $this->entityManager->getPdm()->rollBack();
                }
                throw $e;
            }
        } finally {
            $this->flushDepth--;
            $this->isFlushInProgress = $wasFlushInProgress;
        }
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->stateManager->clear();
        $this->changeTracker->clear();
        $this->relationManager->clear();
        $this->eventCalled = [];
    }

    /**
     * @param object $entity
     * @param array<string, mixed>|null $originalData
     * @return void
     */
    public function manageNewEntity(object $entity, ?array $originalData): void
    {
        $this->stateManager->manage($entity);
        $this->changeTracker->trackOriginalData($entity, $originalData);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function prepareForFlush(): void
    {
        $this->computeAllChanges();
        $this->relationManager->processRelationChanges();
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function computeAllChanges(): void
    {
        // Clean up any null entities first
        $this->stateManager->cleanupNullEntities();

        // Compute changes for insertions
        foreach ($this->stateManager->getScheduledInsertions() as $entity) {
            $this->changeTracker->computeChanges($entity);
        }

        // Compute changes for managed entities
        foreach ($this->stateManager->getManagedEntities() as $entity) {
            if (!$this->stateManager->isScheduledForDeletion($entity)) {
                $this->changeTracker->computeChanges($entity);

                if ($this->changeTracker->hasNonIdChanges($entity)) {
                    $this->stateManager->scheduleForUpdate($entity);
                }
            }
        }
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function executeInsertions(): void
    {
        $insertions = $this->stateManager->getScheduledInsertions();

        if (empty($insertions)) {
            return;
        }

        $processedEntities = [];

        foreach ($insertions as $entity) {
            $this->insertionProcessor->execute($entity, $this->changeTracker->getChanges($entity));
            $this->stateManager->markAsProcessed($entity);
            $this->changeTracker->refreshOriginalData($entity);

            $processedEntities[] = $entity;
        }

        $this->dispatchPostPersistEvents($processedEntities);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function executeUpdates(): void
    {
        $updates = $this->stateManager->getScheduledUpdates();

        if (empty($updates)) {
            return;
        }

        foreach ($updates as $entity) {
            $changes = $this->changeTracker->getChanges($entity);

            // Ensure we have changes to process
            if (empty($changes)) {
                $this->stateManager->markAsProcessed($entity);
                continue;
            }

            $this->dispatchPreUpdateEvent($entity, $changes);

            // Recompute changes after PreUpdate event as it might have modified the entity
            $this->changeTracker->computeChanges($entity);
            $updatedChanges = $this->changeTracker->getChanges($entity);

            // If no changes after PreUpdate event, skip the database update
            if (!empty($updatedChanges) && $this->hasValidUpdateChanges($entity, $updatedChanges)) {
                $this->updateProcessor->execute($entity, $updatedChanges);
            }

            $this->stateManager->markAsProcessed($entity);
            $this->changeTracker->refreshOriginalData($entity);

            $this->dispatchPostUpdateEvent($entity);
        }

        // After processing all updates, check if there are new pending changes
        // and flush them in a new cycle
        if ($this->stateManager->hasPendingChanges()) {
            $this->flush();
        }
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function executeDeletions(): void
    {
        $deletions = $this->stateManager->getScheduledDeletions();

        if (empty($deletions)) {
            return;
        }

        $processedEntities = [];

        foreach ($deletions as $entity) {
            $this->dispatchPreRemoveEvent($entity);
            $this->deletionProcessor->execute($entity);
            $this->stateManager->markAsProcessed($entity);

            $processedEntities[] = $entity;
        }

        $this->dispatchPostRemoveEvents($processedEntities);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function executeRelationChanges(): void
    {
        $this->relationManager->flush();
    }

    /**
     * @param object $entity
     * @return int|null
     */
    private function getId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            throw new \RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
    }

    /**
     * Event dispatching methods
     */

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPrePersistEvent(object $entity): void
    {
        if ($this->eventManager) {
            $this->eventManager->dispatch(
                new \MulerTech\Database\Event\PrePersistEvent($entity, $this->entityManager)
            );
        }
    }

    /**
     * @param array<object> $entities
     * @return void
     */
    private function dispatchPostPersistEvents(array $entities): void
    {
        if ($this->eventManager) {
            foreach ($entities as $entity) {
                $this->eventManager->dispatch(
                    new \MulerTech\Database\Event\PostPersistEvent($entity, $this->entityManager)
                );
            }
        }
    }

    /**
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return void
     */
    private function dispatchPreUpdateEvent(object $entity, array $changes): void
    {
        if ($this->eventManager && !$this->isEventCalled($entity, DbEvents::preUpdate->value)) {
            $this->markEventCalled($entity, DbEvents::preUpdate->value);
            $this->eventManager->dispatch(
                new \MulerTech\Database\Event\PreUpdateEvent($entity, $this->entityManager, $changes)
            );
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPostUpdateEvent(object $entity): void
    {
        if ($this->eventManager && !$this->isEventCalled($entity, DbEvents::postUpdate->value)) {
            $this->markEventCalled($entity, DbEvents::postUpdate->value);
            $this->eventManager->dispatch(
                new \MulerTech\Database\Event\PostUpdateEvent($entity, $this->entityManager)
            );
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPreRemoveEvent(object $entity): void
    {
        if ($this->eventManager && !$this->isEventCalled($entity, DbEvents::preRemove->value)) {
            $this->markEventCalled($entity, DbEvents::preRemove->value);
            $this->eventManager->dispatch(
                new \MulerTech\Database\Event\PreRemoveEvent($entity, $this->entityManager)
            );
        }
    }

    /**
     * @param array<object> $entities
     * @return void
     */
    private function dispatchPostRemoveEvents(array $entities): void
    {
        if ($this->eventManager) {
            foreach ($entities as $entity) {
                $this->eventManager->dispatch(
                    new \MulerTech\Database\Event\PostRemoveEvent($entity, $this->entityManager)
                );
            }
        }
    }

    /**
     * @return void
     */
    private function dispatchPostFlushEvent(): void
    {
        if ($this->eventManager && $this->flushDepth === 1) {
            $this->eventManager->dispatch(new PostFlushEvent($this->entityManager));
            $this->eventCalled = [];

            // Check if PostFlush event created new changes
            if ($this->stateManager->hasPendingChanges()) {
                $this->flush();
            }
        }
    }

    /**
     * @param object $entity
     * @param string $event
     * @return void
     */
    private function markEventCalled(object $entity, string $event): void
    {
        $objectId = spl_object_id($entity);
        if (isset($this->eventCalled[$objectId])) {
            $this->eventCalled[$objectId][] = $event;
        } else {
            $this->eventCalled[$objectId] = [$event];
        }
    }

    /**
     * @param object $entity
     * @param string $event
     * @return bool
     */
    private function isEventCalled(object $entity, string $event): bool
    {
        $objectId = spl_object_id($entity);
        return isset($this->eventCalled[$objectId])
            && in_array($event, $this->eventCalled[$objectId], true);
    }

    /**
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return bool
     * @throws ReflectionException
     */
    private function hasValidUpdateChanges(object $entity, array $changes): bool
    {
        if (empty($changes)) {
            return false;
        }

        // Check if there are any actual value changes (not just ID changes)
        $propertiesColumns = $this->entityManager->getDbMapping()->getPropertiesColumns($entity::class);

        foreach ($propertiesColumns as $property => $column) {
            if ($property === 'id') {
                continue; // Skip ID property
            }

            if (isset($changes[$property]) && $changes[$property][1] !== null) {
                return true;
            }
        }

        return false;
    }
}
