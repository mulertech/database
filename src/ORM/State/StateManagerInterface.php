<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * Interface for state management systems.
 *
 * @author Sébastien Muler
 */
interface StateManagerInterface
{
    public function merge(object $entity): object;

    public function isNew(object $entity): bool;

    public function isManaged(object $entity): bool;

    public function isRemoved(object $entity): bool;

    public function isDetached(object $entity): bool;

    public function manage(object $entity): void;

    public function scheduleForInsertion(object $entity): void;

    public function scheduleForUpdate(object $entity): void;

    public function scheduleForDeletion(object $entity): void;

    public function detach(object $entity): void;

    public function getEntityState(object $entity): EntityLifecycleState;

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

    public function isScheduledForInsertion(object $entity): bool;

    public function isScheduledForUpdate(object $entity): bool;

    public function isScheduledForDeletion(object $entity): bool;

    public function markAsPersisted(object $entity): void;

    public function markAsRemoved(object $entity): void;

    public function clear(): void;
}
