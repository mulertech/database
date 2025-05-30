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
     * @var array<class-string, array<string>>
     */
    private array $eventCalled = [];

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
        if (!$this->stateManager->hasPendingChanges()) {
            return;
        }

        $this->prepareForFlush();

        $this->entityManager->getPdm()->beginTransaction();

        try {
            $this->executeInsertions();
            $this->executeUpdates();
            $this->executeDeletions();
            $this->executeRelationChanges();

            $this->entityManager->getPdm()->commit();
            $this->dispatchPostFlushEvent();
        } catch (\Exception $e) {
            if ($this->entityManager->getPdm()->inTransaction()) {
                $this->entityManager->getPdm()->rollBack();
            }
            throw $e;
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
     * @param array<string, mixed> $originalData
     * @return void
     */
    public function manageNewEntity(object $entity, array $originalData): void
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

            $this->dispatchPreUpdateEvent($entity, $changes);
            $this->updateProcessor->execute($entity, $changes);
            $this->stateManager->markAsProcessed($entity);
            $this->changeTracker->refreshOriginalData($entity);
            $this->dispatchPostUpdateEvent($entity);
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
        if ($this->eventManager && !$this->isFlushInProgress) {
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
        if ($this->eventManager && !$this->isFlushInProgress) {
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
        if ($this->eventManager && !$this->isFlushInProgress) {
            $this->eventManager->dispatch(
                new \MulerTech\Database\Event\PreUpdateEvent($entity, $this->entityManager, $changes)
            );
            $this->markEventCalled($entity::class, DbEvents::preUpdate->value);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPostUpdateEvent(object $entity): void
    {
        if ($this->eventManager && !$this->isFlushInProgress) {
            $this->eventManager->dispatch(
                new \MulerTech\Database\Event\PostUpdateEvent($entity, $this->entityManager)
            );
            $this->markEventCalled($entity::class, DbEvents::postUpdate->value);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    private function dispatchPreRemoveEvent(object $entity): void
    {
        if ($this->eventManager && !$this->isEventCalled($entity::class, DbEvents::preRemove->value)) {
            $this->markEventCalled($entity::class, DbEvents::preRemove->value);
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
        if ($this->eventManager && !$this->isFlushInProgress) {
            $this->isFlushInProgress = true;
            $this->eventManager->dispatch(new PostFlushEvent($this->entityManager));
            $this->isFlushInProgress = false;

            $this->eventCalled = [];
        }
    }

    /**
     * @param class-string $entityName
     * @param string $event
     * @return void
     */
    private function markEventCalled(string $entityName, string $event): void
    {
        if (isset($this->eventCalled[$entityName])) {
            $this->eventCalled[$entityName][] = $event;
        } else {
            $this->eventCalled[$entityName] = [$event];
        }
    }

    /**
     * @param class-string $entityName
     * @param string $event
     * @return bool
     */
    private function isEventCalled(string $entityName, string $event): bool
    {
        return isset($this->eventCalled[$entityName])
            && in_array($event, $this->eventCalled[$entityName], true);
    }
}
