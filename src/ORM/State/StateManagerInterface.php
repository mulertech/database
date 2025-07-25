<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * Interface for state management systems
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface StateManagerInterface
{
    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool;

    /**
     * @param object $entity
     * @return void
     */
    public function manage(object $entity): void;

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForInsertion(object $entity): void;

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForUpdate(object $entity): void;

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForDeletion(object $entity): void;

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void;

    /**
     * @param object $entity
     * @return EntityState
     */
    public function getEntityState(object $entity): EntityState;

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addInsertionDependency(object $dependent, object $dependency): void;

    /**
     * @return array<int, object>
     */
    public function getScheduledInsertions(): array;

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array;

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array;

    /**
     * @return array<int, object>
     */
    public function getManagedEntities(): array;

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForInsertion(object $entity): bool;

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForUpdate(object $entity): bool;

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDeletion(object $entity): bool;

    /**
     * @param object $entity
     * @return void
     */
    public function markAsPersisted(object $entity): void;

    /**
     * @param object $entity
     * @return void
     */
    public function markAsRemoved(object $entity): void;

    /**
     * @return void
     */
    public function clear(): void;
}
