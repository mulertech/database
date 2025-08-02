<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use DateTimeImmutable;
use InvalidArgumentException;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityMetadata;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Direct state manager using enum-based state system with ChangeSetManager integration
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class DirectStateManager implements StateManagerInterface
{
    private EntityScheduler $entityScheduler;
    private DependencyManager $dependencyManager;
    private StateResolver $stateResolver;

    /**
     * @param IdentityMap $identityMap
     * @param StateTransitionManager $transitionManager
     * @param StateValidator $stateValidator
     * @param ChangeSetManager|null $changeSetManager
     */
    public function __construct(
        private IdentityMap $identityMap,
        private StateTransitionManager $transitionManager,
        private StateValidator $stateValidator,
        private ?ChangeSetManager $changeSetManager = null
    ) {
        $this->entityScheduler = new EntityScheduler($identityMap, $stateValidator, $changeSetManager);
        $this->dependencyManager = new DependencyManager();
        $this->stateResolver = new StateResolver($identityMap, $changeSetManager);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityState::MANAGED;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function manage(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if ($currentState !== EntityState::MANAGED) {
            $this->transitionToState($entity, EntityState::MANAGED);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForInsertion(object $entity): void
    {
        $currentState = $this->getEntityState($entity);
        $this->entityScheduler->scheduleForInsertion($entity, $currentState);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForUpdate(object $entity): void
    {
        $currentState = $this->getEntityState($entity);
        $this->entityScheduler->scheduleForUpdate($entity, $currentState);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForDeletion(object $entity): void
    {
        $currentState = $this->getEntityState($entity);
        $this->entityScheduler->scheduleForDeletion($entity, $currentState);

        // Only transition to REMOVED state if not already in that state
        if ($currentState !== EntityState::REMOVED) {
            $this->transitionToState($entity, EntityState::REMOVED);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if (!$this->stateValidator->validateOperation($entity, $currentState, 'detach')) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot detach entity in %s state',
                    $currentState->value
                )
            );
        }

        $this->transitionToState($entity, EntityState::DETACHED);
        $this->changeSetManager?->detach($entity);
        $this->dependencyManager->removeDependencies($entity);
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addInsertionDependency(object $dependent, object $dependency): void
    {
        $this->dependencyManager->addInsertionDependency($dependent, $dependency);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledInsertions(): array
    {
        $insertions = $this->entityScheduler->getScheduledInsertions();
        return $this->dependencyManager->orderByDependencies($insertions);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->entityScheduler->getScheduledUpdates();
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->entityScheduler->getScheduledDeletions();
    }

    /**
     * @return array<int, object>
     */
    public function getManagedEntities(): array
    {
        $managedEntities = [];

        foreach ($this->identityMap->getAllEntities() as $entity) {
            if ($this->isManaged($entity)) {
                $managedEntities[spl_object_id($entity)] = $entity;
            }
        }

        return $managedEntities;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForInsertion(object $entity): bool
    {
        $currentState = $this->getEntityState($entity);
        return $this->entityScheduler->isScheduledForInsertion($entity, $currentState);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForUpdate(object $entity): bool
    {
        return $this->entityScheduler->isScheduledForUpdate($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDeletion(object $entity): bool
    {
        $currentState = $this->getEntityState($entity);
        return $this->entityScheduler->isScheduledForDeletion($entity, $currentState);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsPersisted(object $entity): void
    {
        $this->transitionToState($entity, EntityState::MANAGED);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsRemoved(object $entity): void
    {
        $this->identityMap->remove($entity);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->dependencyManager->clear();
        $this->changeSetManager?->clear();
    }

    /**
     * @param object $entity
     * @return EntityState
     */
    public function getEntityState(object $entity): EntityState
    {
        return $this->stateResolver->resolveEntityState($entity);
    }

    /**
     * @param object $entity
     * @param EntityState $newState
     * @return void
     */
    private function transitionToState(object $entity, EntityState $newState): void
    {
        $currentState = $this->getEntityState($entity);

        // Skip transition if already in the target state
        if ($currentState === $newState) {
            return;
        }

        // Validate and execute transition
        $this->transitionManager->transition($entity, $currentState, $newState);

        // Update state in identity map
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null && $newState === EntityState::MANAGED) {
            $this->identityMap->add($entity);
            $metadata = $this->identityMap->getMetadata($entity);
        }

        if ($metadata !== null) {
            $newMetadata = new EntityMetadata(
                className: $metadata->className,
                identifier: $metadata->identifier,
                state: $newState,
                originalData: $metadata->originalData,
                loadedAt: $metadata->loadedAt,
                lastModified: new DateTimeImmutable()
            );

            $this->identityMap->updateMetadata($entity, $newMetadata);
        }
    }
}
