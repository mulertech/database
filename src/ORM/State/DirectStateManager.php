<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\EntityMetadata;
use MulerTech\Database\ORM\ChangeSetManager;

/**
 * Direct state manager using enum-based state system with ChangeSetManager integration
 * @package MulerTech\Database\ORM\State
 * @author Sébastien Muler
 */
final class DirectStateManager implements StateManagerInterface
{
    /**
     * @var array<int, array<int>> Insertion dependencies
     */
    private array $insertionDependencies = [];

    /**
     * @param IdentityMap $identityMap
     * @param StateTransitionManager $transitionManager
     * @param StateValidator $stateValidator
     * @param ChangeSetManager|null $changeSetManager
     */
    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly StateTransitionManager $transitionManager,
        private readonly StateValidator $stateValidator,
        private ?ChangeSetManager $changeSetManager = null
    ) {
    }

    /**
     * @param ChangeSetManager $changeSetManager
     * @return void
     */
    public function setChangeSetManager(ChangeSetManager $changeSetManager): void
    {
        $this->changeSetManager = $changeSetManager;
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

        if (!$this->stateValidator->validateOperation($entity, $currentState, 'persist')) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for insertion',
                    $currentState->value
                )
            );
        }

        // Also schedule in ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            $this->changeSetManager->scheduleInsert($entity);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForUpdate(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if (!$this->stateValidator->validateOperation($entity, $currentState, 'update')) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for update',
                    $currentState->value
                )
            );
        }

        // Also schedule in ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            $this->changeSetManager->scheduleUpdate($entity);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForDeletion(object $entity): void
    {
        $currentState = $this->getEntityState($entity);

        if (!$this->stateValidator->validateOperation($entity, $currentState, 'remove')) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for deletion',
                    $currentState->value
                )
            );
        }

        // Transition to REMOVED state
        $this->transitionToState($entity, EntityState::REMOVED);

        // Also schedule in ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            $this->changeSetManager->scheduleDelete($entity);
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
            throw new \InvalidArgumentException(
                sprintf(
                    'Cannot detach entity in %s state',
                    $currentState->value
                )
            );
        }

        $this->transitionToState($entity, EntityState::DETACHED);

        // Also detach in ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            $this->changeSetManager->detach($entity);
        }

        // Remove from dependencies
        $oid = spl_object_id($entity);
        unset($this->insertionDependencies[$oid]);
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addInsertionDependency(object $dependent, object $dependency): void
    {
        $dependentId = spl_object_id($dependent);
        $dependencyId = spl_object_id($dependency);

        $this->insertionDependencies[$dependentId][] = $dependencyId;
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledInsertions(): array
    {
        // Get from ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            return $this->changeSetManager->getScheduledInsertions();
        }

        // Fallback to entities in NEW state
        $insertions = [];
        foreach ($this->identityMap->getEntitiesByState(EntityState::NEW) as $entity) {
            $insertions[spl_object_id($entity)] = $entity;
        }

        return $this->orderByDependencies($insertions);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        // Get from ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            return $this->changeSetManager->getScheduledUpdates();
        }

        // Fallback to empty array
        return [];
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array
    {
        // Get from ChangeSetManager if available
        if ($this->changeSetManager !== null) {
            return $this->changeSetManager->getScheduledDeletions();
        }

        // Fallback to entities in REMOVED state
        $deletions = [];
        foreach ($this->identityMap->getEntitiesByState(EntityState::REMOVED) as $entity) {
            $deletions[spl_object_id($entity)] = $entity;
        }

        return $deletions;
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
        if ($this->changeSetManager !== null) {
            $scheduled = $this->changeSetManager->getScheduledInsertions();
            return in_array($entity, $scheduled, true);
        }

        return $this->getEntityState($entity) === EntityState::NEW;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForUpdate(object $entity): bool
    {
        if ($this->changeSetManager !== null) {
            $scheduled = $this->changeSetManager->getScheduledUpdates();
            return in_array($entity, $scheduled, true);
        }

        return false;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDeletion(object $entity): bool
    {
        if ($this->changeSetManager !== null) {
            $scheduled = $this->changeSetManager->getScheduledDeletions();
            return in_array($entity, $scheduled, true);
        }

        return $this->getEntityState($entity) === EntityState::REMOVED;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsProcessed(object $entity): void
    {
        // Nothing to do here, ChangeSetManager handles this
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsPersisted(object $entity): void
    {
        // Transition to MANAGED state
        $this->transitionToState($entity, EntityState::MANAGED);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsRemoved(object $entity): void
    {
        // Remove from identity map
        $this->identityMap->remove($entity);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->insertionDependencies = [];

        if ($this->changeSetManager !== null) {
            $this->changeSetManager->clear();
        }
    }

    /**
     * @param object $entity
     * @return EntityState
     */
    private function getEntityState(object $entity): EntityState
    {
        $state = $this->identityMap->getEntityState($entity);

        if ($state === null) {
            // Entity not in identity map, check if scheduled
            if ($this->changeSetManager !== null) {
                $scheduled = $this->changeSetManager->getScheduledInsertions();
                if (in_array($entity, $scheduled, true)) {
                    return EntityState::NEW;
                }

                $scheduled = $this->changeSetManager->getScheduledDeletions();
                if (in_array($entity, $scheduled, true)) {
                    return EntityState::REMOVED;
                }
            }

            // Default to DETACHED for unknown entities
            return EntityState::DETACHED;
        }

        return $state;
    }

    /**
     * @param object $entity
     * @param EntityState $newState
     * @return void
     */
    private function transitionToState(object $entity, EntityState $newState): void
    {
        $currentState = $this->getEntityState($entity);

        // Validate and execute transition
        $this->transitionManager->transition($entity, $currentState, $newState);

        // Update state in identity map
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null && $newState === EntityState::MANAGED) {
            // Add to identity map if not already there
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
                lastModified: new \DateTimeImmutable()
            );

            $this->identityMap->updateMetadata($entity, $newMetadata);
        }
    }

    /**
     * @param array<int, object> $entities
     * @return array<int, object>
     */
    private function orderByDependencies(array $entities): array
    {
        if (empty($this->insertionDependencies)) {
            return $entities;
        }

        // Simple topological sort for dependencies
        $ordered = [];
        $visited = [];

        foreach ($entities as $oid => $entity) {
            if (!isset($visited[$oid])) {
                $this->visitDependencies($oid, $entities, $visited, $ordered);
            }
        }

        return $ordered;
    }

    /**
     * @param int $oid
     * @param array<int, object> $entities
     * @param array<int, bool> $visited
     * @param array<int, object> $ordered
     * @return void
     */
    private function visitDependencies(int $oid, array $entities, array &$visited, array &$ordered): void
    {
        $visited[$oid] = true;

        // Visit dependencies first
        if (isset($this->insertionDependencies[$oid])) {
            foreach ($this->insertionDependencies[$oid] as $dependencyId) {
                if (!isset($visited[$dependencyId]) && isset($entities[$dependencyId])) {
                    $this->visitDependencies($dependencyId, $entities, $visited, $ordered);
                }
            }
        }

        // Add entity after its dependencies
        if (isset($entities[$oid])) {
            $ordered[$oid] = $entities[$oid];
        }
    }
}