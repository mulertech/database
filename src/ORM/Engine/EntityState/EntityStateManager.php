<?php

namespace MulerTech\Database\ORM\Engine\EntityState;

use MulerTech\Database\ORM\State\StateManagerInterface;

/**
 * Entity state manager (managed, insertions, updates, deletions)
 *
 * @package MulerTech\Database\ORM\Engine\EntityState
 * @author Sébastien Muler
 */
class EntityStateManager implements StateManagerInterface
{
    /**
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $managedEntities = [];

    /**
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $entityInsertions = [];

    /**
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $entityUpdates = [];

    /**
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $entityDeletions = [];

    /**
     * @var array<int, array<int>>
     */
    private array $entityInsertionOrder = [];

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return isset($this->managedEntities[spl_object_id($entity)]);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function manage(object $entity): void
    {
        $objectId = spl_object_id($entity);
        $this->managedEntities[$objectId] = $entity;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForInsertion(object $entity): void
    {
        $objectId = spl_object_id($entity);

        if (!isset($this->entityInsertions[$objectId])) {
            $this->entityInsertions[$objectId] = $entity;

            // Remove from other schedules if present
            unset($this->entityUpdates[$objectId]);
            unset($this->entityDeletions[$objectId]);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForUpdate(object $entity): void
    {
        $objectId = spl_object_id($entity);
        if (!isset($this->entityUpdates[$objectId]) && !isset($this->entityDeletions[$objectId])) {
            $this->entityUpdates[$objectId] = $entity;
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleForDeletion(object $entity): void
    {
        $objectId = spl_object_id($entity);

        // Remove from updates if present
        unset($this->entityUpdates[$objectId]);

        // If the entity was pending insertion, cancel it
        if (isset($this->entityInsertions[$objectId])) {
            unset($this->entityInsertions[$objectId]);
            return;
        }

        $this->entityDeletions[$objectId] = $entity;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $objectId = spl_object_id($entity);

        unset(
            $this->managedEntities[$objectId],
            $this->entityInsertions[$objectId],
            $this->entityUpdates[$objectId],
            $this->entityDeletions[$objectId]
        );
    }

    /**
     * @param object $dependent
     * @param object $dependency
     * @return void
     */
    public function addInsertionDependency(object $dependent, object $dependency): void
    {
        $this->entityInsertionOrder[spl_object_id($dependent)][] = spl_object_id($dependency);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledInsertions(): array
    {
        if (empty($this->entityInsertionOrder)) {
            return $this->entityInsertions;
        }

        return $this->getOrderedInsertions();
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->entityUpdates;
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->entityDeletions;
    }

    /**
     * @return array<int, object>
     */
    public function getManagedEntities(): array
    {
        return $this->managedEntities;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForInsertion(object $entity): bool
    {
        return isset($this->entityInsertions[spl_object_id($entity)]);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForUpdate(object $entity): bool
    {
        return isset($this->entityUpdates[spl_object_id($entity)]);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isScheduledForDeletion(object $entity): bool
    {
        return isset($this->entityDeletions[spl_object_id($entity)]);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsProcessed(object $entity): void
    {
        $objectId = spl_object_id($entity);

        unset(
            $this->entityInsertions[$objectId],
            $this->entityUpdates[$objectId],
            $this->entityDeletions[$objectId]
        );
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsPersisted(object $entity): void
    {
        $oid = spl_object_id($entity);

        // Remove from entity insertions
        if (isset($this->entityInsertions[$oid])) {
            unset($this->entityInsertions[$oid]);
        }

        // Add to managed entities
        $this->managedEntities[$oid] = $entity;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function markAsRemoved(object $entity): void
    {
        $oid = spl_object_id($entity);

        // Remove from all states
        unset($this->managedEntities[$oid]);
        unset($this->entityInsertions[$oid]);
        unset($this->entityUpdates[$oid]);
        unset($this->entityDeletions[$oid]);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->entityInsertions = [];
        $this->entityUpdates = [];
        $this->entityDeletions = [];
        $this->entityInsertionOrder = [];
    }

    /**
     * @return bool
     */
    public function hasPendingChanges(): bool
    {
        return !empty($this->entityInsertions)
            || !empty($this->entityUpdates)
            || !empty($this->entityDeletions);
    }

    /**
     * Remove any null entities from the collections
     * @return void
     */
    public function cleanupNullEntities(): void
    {
        // Since we've fixed the null entity issues upstream,
        // this method can be simplified or removed entirely
        // For now, we'll keep it as a no-op for backward compatibility
    }

    /**
     * @return array<int, object>
     */
    private function getOrderedInsertions(): array
    {
        $first = [];
        $second = [];

        foreach ($this->entityInsertionOrder as $childEntities) {
            foreach ($childEntities as $childEntityId) {
                // Vérifier que l'entité existe dans entityInsertions
                if (!isset($this->entityInsertions[$childEntityId])) {
                    continue;
                }

                if (!isset($this->entityInsertionOrder[$childEntityId])) {
                    $first[$childEntityId] = $this->entityInsertions[$childEntityId];
                } else {
                    $second[$childEntityId] = $this->entityInsertions[$childEntityId];
                }
            }
        }

        if (empty($first)) {
            return $second + $this->entityInsertions;
        }

        if (empty($second)) {
            return $first + $this->entityInsertions;
        }

        return $first + $second + $this->entityInsertions;
    }
}
