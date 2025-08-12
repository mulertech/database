<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use InvalidArgumentException;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Processor\EntityProcessor;
use MulerTech\Database\ORM\Scheduler\EntityScheduler;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\EntityStateManager;
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
    private ChangeSetValidator $validator;
    private ChangeSetOperationHandler $operationHandler;

    /**
     * @param IdentityMap $identityMap
     * @param EntityRegistry $registry
     * @param ChangeDetector $changeDetector
     * @param MetadataRegistry $metadataRegistry
     */
    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly EntityRegistry $registry,
        private readonly ChangeDetector $changeDetector,
        private readonly MetadataRegistry $metadataRegistry
    ) {
        $this->changeSets = new SplObjectStorage();
    }

    /**
     * @return EntityScheduler
     */
    private function getScheduler(): EntityScheduler
    {
        if (!isset($this->scheduler)) {
            $this->scheduler = new EntityScheduler();
        }
        return $this->scheduler;
    }

    /**
     * @return EntityStateManager
     */
    private function getStateManager(): EntityStateManager
    {
        if (!isset($this->stateManager)) {
            $this->stateManager = new EntityStateManager($this->identityMap);
        }
        return $this->stateManager;
    }

    /**
     * @return EntityProcessor
     */
    private function getEntityProcessor(): EntityProcessor
    {
        if (!isset($this->entityProcessor)) {
            $this->entityProcessor = new EntityProcessor($this->changeDetector, $this->identityMap, $this->metadataRegistry);
        }
        return $this->entityProcessor;
    }

    /**
     * @return ChangeSetValidator
     */
    private function getValidator(): ChangeSetValidator
    {
        if (!isset($this->validator)) {
            $this->validator = new ChangeSetValidator($this->identityMap);
        }
        return $this->validator;
    }

    /**
     * @return ChangeSetOperationHandler
     */
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

    /**
     * @return void
     */
    public function computeChangeSets(): void
    {
        $this->changeSets = new SplObjectStorage();
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

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleInsert(object $entity): void
    {
        $this->getOperationHandler()->handleInsertionScheduling(
            $entity,
            $this->getScheduler(),
            $this->getStateManager(),
            $this->getEntityProcessor()
        );
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleUpdate(object $entity): void
    {
        $this->getOperationHandler()->handleUpdateScheduling($entity, $this->getScheduler());
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleDelete(object $entity): void
    {
        $this->getOperationHandler()->handleDeletionScheduling(
            $entity,
            $this->getScheduler(),
            $this->getStateManager()
        );
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->getOperationHandler()->handleDetachment(
            $entity,
            $this->getScheduler(),
            $this->getStateManager()
        );
        unset($this->changeSets[$entity]);
    }

    /**
     * @param object $entity
     * @return void
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
     * @param object $entity
     * @return void
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
