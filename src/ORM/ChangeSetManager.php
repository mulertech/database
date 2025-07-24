<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use InvalidArgumentException;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityState;
use MulerTech\Database\ORM\State\EntityStateManager;
use ReflectionException;
use SplObjectStorage;

/**
 * Class ChangeSetManager
 *
 * Optimised manager for tracking changes in entities
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class ChangeSetManager
{
    /** @var SplObjectStorage<object, ChangeSet> */
    private SplObjectStorage $changeSets;

    /** @var array<object> */
    private array $visitedEntities = [];

    private EntityScheduler $scheduler;
    private EntityStateManager $stateManager;
    private EntityProcessor $entityProcessor;

    /**
     * @param IdentityMap $identityMap
     * @param EntityRegistry $registry
     * @param ChangeDetector $changeDetector
     */
    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly EntityRegistry $registry,
        private readonly ChangeDetector $changeDetector
    ) {
        $this->changeSets = new SplObjectStorage();
    }

    /**
     * Get or create EntityScheduler lazily
     */
    private function getScheduler(): EntityScheduler
    {
        if (!isset($this->scheduler)) {
            $this->scheduler = new EntityScheduler();
        }
        return $this->scheduler;
    }

    /**
     * Get or create EntityStateManager lazily
     */
    private function getStateManager(): EntityStateManager
    {
        if (!isset($this->stateManager)) {
            $this->stateManager = new EntityStateManager($this->identityMap);
        }
        return $this->stateManager;
    }

    /**
     * Get or create EntityProcessor lazily
     */
    private function getEntityProcessor(): EntityProcessor
    {
        if (!isset($this->entityProcessor)) {
            $this->entityProcessor = new EntityProcessor($this->changeDetector, $this->identityMap);
        }
        return $this->entityProcessor;
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function computeChangeSets(): void
    {
        $this->changeSets = new SplObjectStorage();
        // Ne pas effacer le scheduler ici - on a besoin des planifications existantes
        $this->visitedEntities = [];

        // Process all managed entities
        $managedEntities = $this->identityMap->getEntitiesByState(EntityState::MANAGED);

        foreach ($managedEntities as $entity) {
            $this->computeEntityChangeSet($entity);
        }

        // Process entities scheduled for insertion (they might have relations)
        foreach ($this->getScheduler()->getScheduledInsertions() as $entity) {
            $this->computeEntityChangeSet($entity);
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function scheduleInsert(object $entity): void
    {
        if ($this->getScheduler()->isScheduledForInsertion($entity)) {
            return;
        }

        $metadata = $this->identityMap->getMetadata($entity);
        $entityId = $this->getEntityProcessor()->extractEntityId($entity);

        if ($this->shouldSkipInsertion($entityId, $metadata)) {
            return;
        }

        $this->getScheduler()->scheduleForInsertion($entity);
        $this->registry->register($entity);

        $this->handleEntityStateForInsertion($entity, $metadata);
        $this->getScheduler()->removeFromSchedule($entity, 'updates');
        $this->getScheduler()->removeFromSchedule($entity, 'deletions');
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleUpdate(object $entity): void
    {
        if (!$this->canScheduleUpdate($entity)) {
            return;
        }

        $this->getScheduler()->scheduleForUpdate($entity);
        $this->registry->register($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleDelete(object $entity): void
    {
        if ($this->getScheduler()->isScheduledForDeletion($entity)) {
            return;
        }

        $this->getScheduler()->scheduleForDeletion($entity);

        // Ne pas forcer la transition d'état ici - laisser le système de validation s'en charger
        // La transition d'état sera gérée par DirectStateManager ou EmEngine
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null && $metadata->state !== EntityState::REMOVED) {
            // Seulement mettre à jour les métadonnées si l'entité n'est pas en état NEW
            if ($metadata->state !== EntityState::NEW) {
                $this->getStateManager()->transitionToRemoved($entity);
            }
        }

        $this->getScheduler()->removeFromSchedule($entity, 'insertions');
        $this->getScheduler()->removeFromSchedule($entity, 'updates');
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->getScheduler()->removeFromAllSchedules($entity);
        $this->getStateManager()->transitionToDetached($entity);
        unset($this->changeSets[$entity]);
        $this->registry->unregister($entity);
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function merge(object $entity): void
    {
        $entityClass = $entity::class;
        $id = $this->getEntityProcessor()->extractEntityId($entity);

        if ($id === null) {
            throw new InvalidArgumentException('Cannot merge entity without identifier');
        }

        $managedEntity = $this->identityMap->get($entityClass, $id);

        if ($managedEntity !== null) {
            $this->getEntityProcessor()->copyEntityData($entity, $managedEntity);
            $this->scheduleUpdate($managedEntity);
            return;
        }

        $this->identityMap->add($entity);
        $this->getStateManager()->transitionToManaged($entity);
        $this->registry->register($entity);
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->getScheduler()->getScheduledInsertions();
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->getScheduler()->getScheduledUpdates();
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->getScheduler()->getScheduledDeletions();
    }

    /**
     * Get the ChangeSet for a specific entity
     *
     * @param object $entity
     * @return ChangeSet|null
     */
    public function getChangeSet(object $entity): ?ChangeSet
    {
        return $this->changeSets[$entity] ?? null;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool
    {
        $changeSet = $this->getChangeSet($entity);
        return $changeSet !== null && !$changeSet->isEmpty();
    }

    /**
     * @return bool
     */
    public function hasPendingChanges(): bool
    {
        return $this->getScheduler()->hasPendingSchedules() || count($this->changeSets) > 0;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->changeSets = new SplObjectStorage();
        $this->getScheduler()->clear();
        $this->visitedEntities = [];
        $this->registry->clear();
    }

    /**
     * Clears changes that have been processed (e.g., after a flush).
     * This is typically called by PersistenceManager.
     * @return void
     */
    public function clearProcessedChanges(): void
    {
        $this->changeSets = new SplObjectStorage();
        $this->getScheduler()->clear();
        $this->visitedEntities = [];
    }

    /**
     * @return array{insertions: int, updates: int, deletions: int, changeSets: int, hasChanges: bool}
     */
    public function getStatistics(): array
    {
        $schedulerStats = $this->getScheduler()->getStatistics();

        return [
            'insertions' => $schedulerStats['insertions'],
            'updates' => $schedulerStats['updates'],
            'deletions' => $schedulerStats['deletions'],
            'changeSets' => $this->changeSets->count(),
            'hasChanges' => $this->hasPendingChanges(),
        ];
    }

    // Private helper methods to reduce complexity
    private function shouldSkipInsertion(int|string|null $entityId, ?EntityMetadata $metadata): bool
    {
        if ($entityId !== null && $metadata !== null && $metadata->isManaged()) {
            return true;
        }

        return $entityId !== null;
    }

    private function canScheduleUpdate(object $entity): bool
    {
        return $this->identityMap->isManaged($entity) &&
               !$this->getScheduler()->isScheduledForInsertion($entity) &&
               !$this->getScheduler()->isScheduledForDeletion($entity) &&
               !$this->getScheduler()->isScheduledForUpdate($entity);
    }

    private function handleEntityStateForInsertion(object $entity, ?EntityMetadata $metadata): void
    {
        if ($metadata === null) {
            $this->identityMap->add($entity);
            return;
        }

        if ($metadata->state !== EntityState::NEW) {
            $newData = $this->changeDetector->extractCurrentData($entity);
            $this->getStateManager()->tryTransitionToNew($entity, $newData);
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function computeEntityChangeSet(object $entity): void
    {
        if (in_array($entity, $this->visitedEntities, true)) {
            return;
        }

        $this->visitedEntities[] = $entity;

        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null || (!$metadata->isManaged() && !$metadata->isNew())) {
            return;
        }

        $changeSet = $this->changeDetector->computeChangeSet($entity, $metadata->originalData);

        if (!$changeSet->isEmpty()) {
            $this->changeSets[$entity] = $changeSet;
            $scheduler = $this->getScheduler();

            if (!$scheduler->isScheduledForInsertion($entity) &&
                !$scheduler->isScheduledForUpdate($entity)) {
                $scheduler->scheduleForUpdate($entity);
            }
        }
    }
}
