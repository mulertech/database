<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Direct state manager using enum-based state system with ChangeSetManager integration.
 *
 * @author Sébastien Muler
 */
final readonly class DirectStateManager implements StateManagerInterface
{
    private DependencyManager $dependencyManager;

    public function __construct(
        private IdentityMap $identityMap,
        private StateTransitionManager $transitionManager,
        private StateValidator $stateValidator,
        private ChangeSetManager $changeSetManager,
    ) {
        $this->dependencyManager = new DependencyManager();
    }

    public function isManaged(object $entity): bool
    {
        return EntityLifecycleState::MANAGED === $this->getEntityState($entity);
    }

    public function manage(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if (EntityLifecycleState::MANAGED !== $currentState) {
            $this->transitionToState($entity, EntityLifecycleState::MANAGED);
        }
    }

    public function scheduleForInsertion(object $entity): void
    {
        $this->changeSetManager->scheduleInsert($entity);
    }

    public function scheduleForUpdate(object $entity): void
    {
        $this->changeSetManager->scheduleUpdate($entity);
    }

    public function scheduleForDeletion(object $entity): void
    {
        $currentState = $this->getEntityState($entity);
        $this->changeSetManager->scheduleDelete($entity);

        // Only transition to REMOVED state if not already in that state
        if (EntityLifecycleState::REMOVED !== $currentState) {
            $this->transitionToState($entity, EntityLifecycleState::REMOVED);
        }
    }

    public function detach(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if (!$this->stateValidator->validateOperation($entity, $currentState, 'detach')) {
            throw new \InvalidArgumentException(sprintf('Cannot detach entity in %s state', $currentState->value));
        }

        // Remove entity from identity map instead of transitioning to DETACHED state
        $this->identityMap->remove($entity);
        $this->changeSetManager->detach($entity);
        $this->dependencyManager->removeDependencies($entity);
    }

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

    public function isScheduledForInsertion(object $entity): bool
    {
        $scheduled = $this->changeSetManager->getScheduledInsertions();

        return in_array($entity, $scheduled, true);
    }

    public function isScheduledForUpdate(object $entity): bool
    {
        $scheduled = $this->changeSetManager->getScheduledUpdates();

        return in_array($entity, $scheduled, true);
    }

    public function isScheduledForDeletion(object $entity): bool
    {
        $scheduled = $this->changeSetManager->getScheduledDeletions();

        return in_array($entity, $scheduled, true);
    }

    public function markAsPersisted(object $entity): void
    {
        $this->transitionToState($entity, EntityLifecycleState::MANAGED);
    }

    public function markAsRemoved(object $entity): void
    {
        $this->identityMap->remove($entity);
    }

    public function merge(object $entity): object
    {
        // For now, return the entity as-is
        // This is a placeholder implementation
        return $entity;
    }

    public function isNew(object $entity): bool
    {
        return EntityLifecycleState::NEW === $this->getEntityState($entity);
    }

    public function isRemoved(object $entity): bool
    {
        return EntityLifecycleState::REMOVED === $this->getEntityState($entity);
    }

    public function isDetached(object $entity): bool
    {
        return EntityLifecycleState::DETACHED === $this->getEntityState($entity);
    }

    public function clear(): void
    {
        $this->dependencyManager->clear();
        $this->changeSetManager->clear();
    }

    public function getEntityState(object $entity): EntityLifecycleState
    {
        $state = $this->identityMap->getEntityState($entity);

        if (null === $state) {
            return EntityLifecycleState::DETACHED;
        }

        return $state;
    }

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

        if (null === $metadata && EntityLifecycleState::MANAGED === $newState) {
            $this->identityMap->add($entity);
            $metadata = $this->identityMap->getMetadata($entity);
        }

        if (null !== $metadata) {
            $newMetadata = new EntityState(
                className: $metadata->className,
                state: $newState,
                originalData: $metadata->originalData,
                lastModified: new \DateTimeImmutable()
            );

            $this->identityMap->updateMetadata($entity, $newMetadata);
        }
    }
}
