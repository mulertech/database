<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\EntityStateManager;

/**
 * Class ChangeSetManager.
 *
 * Optimised manager for tracking changes in entities
 *
 * @author Sébastien Muler
 */
final class ChangeSetManager
{
    /** @var \SplObjectStorage<object, ChangeSet> */
    private \SplObjectStorage $changeSets;

    /** @var array<object> */
    private array $visitedEntities = [];

    private EntityScheduler $scheduler;
    private EntityStateManager $stateManager;
    private EntityProcessor $entityProcessor;
    private ChangeSetValidator $validator;
    private ChangeSetOperationHandler $operationHandler;

    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly EntityRegistry $registry,
        private readonly ChangeDetector $changeDetector,
        private readonly MetadataRegistry $metadataRegistry,
    ) {
        $this->changeSets = new \SplObjectStorage();
    }

    private function getScheduler(): EntityScheduler
    {
        if (!isset($this->scheduler)) {
            $this->scheduler = new EntityScheduler();
        }

        return $this->scheduler;
    }

    private function getStateManager(): EntityStateManager
    {
        if (!isset($this->stateManager)) {
            $this->stateManager = new EntityStateManager($this->identityMap);
        }

        return $this->stateManager;
    }

    private function getEntityProcessor(): EntityProcessor
    {
        if (!isset($this->entityProcessor)) {
            $this->entityProcessor = new EntityProcessor($this->changeDetector, $this->identityMap, $this->metadataRegistry);
        }

        return $this->entityProcessor;
    }

    private function getValidator(): ChangeSetValidator
    {
        if (!isset($this->validator)) {
            $this->validator = new ChangeSetValidator($this->identityMap);
        }

        return $this->validator;
    }

    private function getOperationHandler(): ChangeSetOperationHandler
    {
        if (!isset($this->operationHandler)) {
            $this->operationHandler = new ChangeSetOperationHandler(
                $this->identityMap,
                $this->registry,
                $this->changeDetector,
                $this->getValidator()
            );
        }

        return $this->operationHandler;
    }

    public function computeChangeSets(): void
    {
        $this->changeSets = new \SplObjectStorage();
        // Ne pas effacer le scheduler ici - on a besoin des planifications existantes
        $this->visitedEntities = [];

        // Process all managed entities
        $managedEntities = $this->identityMap->getEntitiesByState(EntityLifecycleState::MANAGED);

        foreach ($managedEntities as $entity) {
            $this->computeEntityChangeSet($entity);
        }

        // Process entities scheduled for insertion (they might have relations)
        foreach ($this->getScheduler()->getScheduledInsertions() as $entity) {
            $this->computeEntityChangeSet($entity);
        }
    }

    public function scheduleInsert(object $entity): void
    {
        $this->getOperationHandler()->handleInsertionScheduling(
            $entity,
            $this->getScheduler(),
            $this->getStateManager(),
            $this->getEntityProcessor()
        );
    }

    public function scheduleUpdate(object $entity): void
    {
        $this->getOperationHandler()->handleUpdateScheduling($entity, $this->getScheduler());
    }

    public function scheduleDelete(object $entity): void
    {
        $this->getOperationHandler()->handleDeletionScheduling(
            $entity,
            $this->getScheduler(),
            $this->getStateManager()
        );
    }

    public function detach(object $entity): void
    {
        $this->getOperationHandler()->handleDetachment(
            $entity,
            $this->getScheduler(),
            $this->getStateManager()
        );
        unset($this->changeSets[$entity]);
    }

    public function merge(object $entity): void
    {
        $entityClass = $entity::class;
        $id = $this->getEntityProcessor()->extractEntityId($entity);

        if (null === $id) {
            throw new \InvalidArgumentException('Cannot merge entity without identifier');
        }

        $managedEntity = $this->identityMap->get($entityClass, $id);

        if (null !== $managedEntity) {
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
     * Get the ChangeSet for a specific entity.
     */
    public function getChangeSet(object $entity): ?ChangeSet
    {
        return $this->changeSets[$entity] ?? null;
    }

    public function hasChanges(object $entity): bool
    {
        $changeSet = $this->getChangeSet($entity);

        return null !== $changeSet && !$changeSet->isEmpty();
    }

    public function clear(): void
    {
        $this->changeSets = new \SplObjectStorage();
        $this->getScheduler()->clear();
        $this->visitedEntities = [];
        $this->registry->clear();
    }

    /**
     * Clears changes that have been processed (e.g., after a flush).
     * This is typically called by PersistenceManager.
     */
    public function clearProcessedChanges(): void
    {
        $this->changeSets = new \SplObjectStorage();
        $this->getScheduler()->clear();
        $this->visitedEntities = [];
    }

    private function computeEntityChangeSet(object $entity): void
    {
        if (in_array($entity, $this->visitedEntities, true)) {
            return;
        }

        $this->visitedEntities[] = $entity;

        $metadata = $this->identityMap->getMetadata($entity);
        if (null === $metadata || (!$metadata->isManaged() && !$metadata->isNew())) {
            return;
        }

        $changeSet = $this->changeDetector->computeChangeSet($entity, $metadata->originalData);

        if (!$changeSet->isEmpty()) {
            $this->changeSets[$entity] = $changeSet;
            $scheduler = $this->getScheduler();

            if (!$scheduler->isScheduledForInsertion($entity)
                && !$scheduler->isScheduledForUpdate($entity)) {
                $scheduler->scheduleForUpdate($entity);
            }
        }
    }
}
