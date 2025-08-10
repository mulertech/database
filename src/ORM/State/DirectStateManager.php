<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use DateTimeImmutable;
use InvalidArgumentException;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Direct state manager using enum-based state system with ChangeSetManager integration
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class DirectStateManager implements StateManagerInterface
{
    private DependencyManager $dependencyManager;

    /**
     * @param IdentityMap $identityMap
     * @param StateTransitionManager $transitionManager
     * @param StateValidator $stateValidator
     * @param ChangeSetManager $changeSetManager
     */
    public function __construct(
        private IdentityMap $identityMap,
        private StateTransitionManager $transitionManager,
        private StateValidator $stateValidator,
        private ChangeSetManager $changeSetManager
    ) {
        $this->dependencyManager = new DependencyManager();
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityLifecycleState::MANAGED;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function manage(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if ($currentState !== EntityLifecycleState::MANAGED) {
            $this->transitionToState($entity, EntityLifecycleState::MANAGED);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForInsertion(object $entity): void
    {
        $this->changeSetManager->scheduleInsert($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForUpdate(object $entity): void
    {
        $this->changeSetManager->scheduleUpdate($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForDeletion(object $entity): void
    {
        $currentState = $this->getEntityState($entity);
        $this->changeSetManager->scheduleDelete($entity);

        // Only transition to REMOVED state if not already in that state
        if ($currentState !== EntityLifecycleState::REMOVED) {
            $this->transitionToState($entity, EntityLifecycleState::REMOVED);
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

        // Remove entity from identity map instead of transitioning to DETACHED state
        $this->identityMap->remove($entity);
        $this->changeSetManager->detach($entity);
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
        $insertions = $this->changeSetManager->getScheduledInsertions();
        return $this->dependencyManager->orderByDependencies($insertions);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->changeSetManager->getScheduledUpdates();
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->changeSetManager->getScheduledDeletions();
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
        $scheduled = $this->changeSetManager->getScheduledInsertions();
        return in_array($entity, $scheduled, true);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForUpdate(object $entity): bool
    {
        $scheduled = $this->changeSetManager->getScheduledUpdates();
        return in_array($entity, $scheduled, true);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDeletion(object $entity): bool
    {
        $scheduled = $this->changeSetManager->getScheduledDeletions();
        return in_array($entity, $scheduled, true);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsPersisted(object $entity): void
    {
        $this->transitionToState($entity, EntityLifecycleState::MANAGED);
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
     * @param object $entity
     * @return object
     */
    public function merge(object $entity): object
    {
        // For now, return the entity as-is
        // This is a placeholder implementation
        return $entity;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isNew(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityLifecycleState::NEW;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isRemoved(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityLifecycleState::REMOVED;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isDetached(object $entity): bool
    {
        return $this->getEntityState($entity) === EntityLifecycleState::DETACHED;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->dependencyManager->clear();
        $this->changeSetManager->clear();
    }

    /**
     * @param object $entity
     * @return EntityLifecycleState
     */
    public function getEntityState(object $entity): EntityLifecycleState
    {
        $state = $this->identityMap->getEntityState($entity);

        if ($state === null) {
            return EntityLifecycleState::DETACHED;
        }

        return $state;
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $newState
     * @return void
     */
    private function transitionToState(object $entity, EntityLifecycleState $newState): void
    {
        $currentState = $this->getEntityState($entity);

        // Skip transition if already in the target state
        if ($currentState === $newState) {
            return;
        }

        // Validate and execute transition
        $this->transitionManager->transition($entity, $newState);

        // Update state in identity map
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null && $newState === EntityLifecycleState::MANAGED) {
            $this->identityMap->add($entity);
            $metadata = $this->identityMap->getMetadata($entity);
        }

        if ($metadata !== null) {
            $newMetadata = new EntityState(
                className: $metadata->className,
                state: $newState,
                originalData: $metadata->originalData,
                lastModified: new DateTimeImmutable()
            );

            $this->identityMap->updateMetadata($entity, $newMetadata);
        }
    }
}
