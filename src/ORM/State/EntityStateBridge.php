<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use MulerTech\Database\ORM\Engine\EntityState\EntityStateManager;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\EntityMetadata;

/**
 * Bridge between legacy EntityStateManager and new enum-based state system
 * @package MulerTech\Database\ORM\State
 * @author Sébastien Muler
 */
final class EntityStateBridge implements StateManagerInterface
{
    /**
     * @param EntityStateManager $legacyStateManager
     * @param IdentityMap $identityMap
     * @param StateTransitionManager $transitionManager
     * @param StateValidator $stateValidator
     */
    public function __construct(
        private readonly EntityStateManager $legacyStateManager,
        private readonly IdentityMap $identityMap,
        private readonly StateTransitionManager $transitionManager,
        private readonly StateValidator $stateValidator
    ) {
    }

    /**
     * @param object $entity
     * @return EntityState
     */
    public function getEntityState(object $entity): EntityState
    {
        // Check new system first
        $state = $this->identityMap->getEntityState($entity);
        if ($state !== null) {
            return $state;
        }

        // Fallback to legacy system
        if ($this->legacyStateManager->isScheduledForInsertion($entity)) {
            return EntityState::NEW;
        }

        if ($this->legacyStateManager->isScheduledForDeletion($entity)) {
            return EntityState::REMOVED;
        }

        if ($this->legacyStateManager->isManaged($entity)) {
            return EntityState::MANAGED;
        }

        return EntityState::DETACHED;
    }

    /**
     * @param object $entity
     * @param EntityState $newState
     * @return void
     */
    public function setEntityState(object $entity, EntityState $newState): void
    {
        $currentState = $this->getEntityState($entity);

        // Validate transition
        if (!$this->stateValidator->validateTransition($entity, $currentState, $newState)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid state transition from %s to %s for entity %s',
                    $currentState->value,
                    $newState->value,
                    $entity::class
                )
            );
        }

        // Execute transition
        $this->transitionManager->transition($entity, $currentState, $newState);

        // Update both systems
        $this->updateLegacyState($entity, $newState);
        $this->updateNewState($entity, $newState);
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
        $this->setEntityState($entity, EntityState::MANAGED);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForInsertion(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if ($currentState === EntityState::NEW) {
            $this->legacyStateManager->scheduleForInsertion($entity);
        } else {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for insertion',
                    $currentState->value
                )
            );
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForUpdate(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if ($currentState === EntityState::MANAGED) {
            $this->legacyStateManager->scheduleForUpdate($entity);
        } else {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for update',
                    $currentState->value
                )
            );
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForDeletion(object $entity): void
    {
        $this->setEntityState($entity, EntityState::REMOVED);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->setEntityState($entity, EntityState::DETACHED);
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addInsertionDependency(object $dependent, object $dependency): void
    {
        $this->legacyStateManager->addInsertionDependency($dependent, $dependency);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->legacyStateManager->getScheduledInsertions();
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->legacyStateManager->getScheduledUpdates();
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->legacyStateManager->getScheduledDeletions();
    }

    /**
     * @return array<int, object>
     */
    public function getManagedEntities(): array
    {
        return $this->legacyStateManager->getManagedEntities();
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForInsertion(object $entity): bool
    {
        return $this->legacyStateManager->isScheduledForInsertion($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForUpdate(object $entity): bool
    {
        return $this->legacyStateManager->isScheduledForUpdate($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDeletion(object $entity): bool
    {
        return $this->legacyStateManager->isScheduledForDeletion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsProcessed(object $entity): void
    {
        $this->legacyStateManager->markAsProcessed($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsPersisted(object $entity): void
    {
        $this->legacyStateManager->markAsPersisted($entity);
        $this->setEntityState($entity, EntityState::MANAGED);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsRemoved(object $entity): void
    {
        $this->legacyStateManager->markAsRemoved($entity);
        $this->identityMap->remove($entity);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->legacyStateManager->clear();
    }

    /**
     * @param object $entity
     * @param EntityState $newState
     * @return void
     */
    private function updateLegacyState(object $entity, EntityState $newState): void
    {
        match ($newState) {
            EntityState::NEW => $this->legacyStateManager->scheduleForInsertion($entity),
            EntityState::MANAGED => $this->legacyStateManager->manage($entity),
            EntityState::REMOVED => $this->legacyStateManager->scheduleForDeletion($entity),
            EntityState::DETACHED => null, // Legacy system doesn't track detached state explicitly
        };
    }

    /**
     * @param object $entity
     * @param EntityState $newState
     * @return void
     */
    private function updateNewState(object $entity, EntityState $newState): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null) {
            $newMetadata = new EntityMetadata(
                className: $metadata->className,
                identifier: $metadata->identifier,
                state: $newState,
                originalData: $metadata->originalData,
                loadedAt: $metadata->loadedAt,
                lastModified: new \DateTimeImmutable()
            );

            $this->identityMap->updateMetadata($entity, $newMetadata);
        }
    }
}
