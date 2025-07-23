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
        $this->scheduler = new EntityScheduler();
        $this->stateManager = new EntityStateManager($identityMap);
        $this->entityProcessor = new EntityProcessor($changeDetector, $identityMap);
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
        foreach ($this->scheduler->getScheduledInsertions() as $entity) {
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
        if ($this->scheduler->isScheduledForInsertion($entity)) {
            return;
        }

        $metadata = $this->identityMap->getMetadata($entity);
        $entityId = $this->entityProcessor->extractEntityId($entity);

        if ($this->shouldSkipInsertion($entityId, $metadata)) {
            return;
        }

        $this->scheduler->scheduleForInsertion($entity);
        $this->registry->register($entity);

        $this->handleEntityStateForInsertion($entity, $metadata);
        $this->scheduler->removeFromSchedule($entity, 'updates');
        $this->scheduler->removeFromSchedule($entity, 'deletions');
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

        $this->scheduler->scheduleForUpdate($entity);
        $this->registry->register($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleDelete(object $entity): void
    {
        if ($this->scheduler->isScheduledForDeletion($entity)) {
            return;
        }

        $this->scheduler->scheduleForDeletion($entity);

        // Ne pas forcer la transition d'état ici - laisser le système de validation s'en charger
        // La transition d'état sera gérée par DirectStateManager ou EmEngine
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null && $metadata->state !== EntityState::REMOVED) {
            // Seulement mettre à jour les métadonnées si l'entité n'est pas en état NEW
            if ($metadata->state !== EntityState::NEW) {
                $this->stateManager->transitionToRemoved($entity);
            }
        }

        $this->scheduler->removeFromSchedule($entity, 'insertions');
        $this->scheduler->removeFromSchedule($entity, 'updates');
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->scheduler->removeFromAllSchedules($entity);
        $this->stateManager->transitionToDetached($entity);
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
        $id = $this->entityProcessor->extractEntityId($entity);

        if ($id === null) {
            throw new InvalidArgumentException('Cannot merge entity without identifier');
        }

        $managedEntity = $this->identityMap->get($entityClass, $id);

        if ($managedEntity !== null) {
            $this->entityProcessor->copyEntityData($entity, $managedEntity);
            $this->scheduleUpdate($managedEntity);
            return;
        }

        $this->identityMap->add($entity);
        $this->stateManager->transitionToManaged($entity);
        $this->registry->register($entity);
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->scheduler->getScheduledInsertions();
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->scheduler->getScheduledUpdates();
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->scheduler->getScheduledDeletions();
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
        return $this->scheduler->hasPendingSchedules() || count($this->changeSets) > 0;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->changeSets = new SplObjectStorage();
        $this->scheduler->clear();
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
        $this->scheduler->clear();
        $this->visitedEntities = [];
    }

    /**
     * @return array{insertions: int, updates: int, deletions: int, changeSets: int, hasChanges: bool}
     */
    public function getStatistics(): array
    {
        $schedulerStats = $this->scheduler->getStatistics();

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
               !$this->scheduler->isScheduledForInsertion($entity) &&
               !$this->scheduler->isScheduledForDeletion($entity) &&
               !$this->scheduler->isScheduledForUpdate($entity);
    }

    private function handleEntityStateForInsertion(object $entity, ?EntityMetadata $metadata): void
    {
        if ($metadata === null) {
            $this->identityMap->add($entity);
            return;
        }

        if ($metadata->state !== EntityState::NEW) {
            $newData = $this->changeDetector->extractCurrentData($entity);
            $this->stateManager->tryTransitionToNew($entity, $newData);
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

            if (!$this->scheduler->isScheduledForInsertion($entity) &&
                !$this->scheduler->isScheduledForUpdate($entity)) {
                $this->scheduler->scheduleForUpdate($entity);
            }
        }
    }
}
