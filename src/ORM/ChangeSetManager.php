<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\State\EntityState;
use SplObjectStorage;

/**
 * Optimised manager for tracking changes in entities
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final class ChangeSetManager
{
    /** @var SplObjectStorage<object, ChangeSet> */
    private SplObjectStorage $changeSets;

    /** @var array<object> */
    private array $scheduledInsertions = [];

    /** @var array<object> */
    private array $scheduledUpdates = [];

    /** @var array<object> */
    private array $scheduledDeletions = [];

    /** @var array<object> */
    private array $visitedEntities = [];

    /**
     * @param IdentityMap $identityMap
     * @param ChangeDetector $changeDetector
     */
    public function __construct(
        private readonly IdentityMap $identityMap,
        private readonly ChangeDetector $changeDetector
    ) {
        $this->changeSets = new SplObjectStorage();
    }

    /**
     * @return void
     */
    public function computeChangeSets(): void
    {
        $this->changeSets = new SplObjectStorage();
        $this->scheduledUpdates = [];
        $this->visitedEntities = [];

        // Process all managed entities
        $managedEntities = $this->identityMap->getEntitiesByState(EntityState::MANAGED);

        foreach ($managedEntities as $entity) {
            $this->computeEntityChangeSet($entity);
        }

        // Process entities scheduled for insertion (they might have relations)
        foreach ($this->scheduledInsertions as $entity) {
            $this->computeEntityChangeSet($entity);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleInsert(object $entity): void
    {
        if (in_array($entity, $this->scheduledInsertions, true)) {
            return;
        }

        $this->scheduledInsertions[] = $entity;

        // L'état des entités dans scheduleInsert doit être NEW
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            // Si l'entité n'est pas encore dans l'identity map, l'ajouter avec l'état NEW
            $this->identityMap->add($entity);

            // Forcer l'état à NEW
            $metadata = $this->identityMap->getMetadata($entity);
            if ($metadata !== null && $metadata->state !== EntityState::NEW) {
                $newMetadata = $metadata->withState(EntityState::NEW);
                $this->identityMap->updateMetadata($entity, $newMetadata);
            }
            return;
        }

        // Si l'entité est déjà dans l'identity map, forcer son état à NEW
        if ($metadata->state !== EntityState::NEW) {
            try {
                $newMetadata = $metadata->withState(EntityState::NEW);
                $this->identityMap->updateMetadata($entity, $newMetadata);
            } catch (\InvalidArgumentException $e) {
                // Créer une nouvelle métadonnée avec l'état NEW si la transition échoue
                $newData = $this->changeDetector->extractCurrentData($entity);
                $newMetadata = new EntityMetadata(
                    $metadata->className,
                    $metadata->identifier,
                    EntityState::NEW,
                    $newData,
                    $metadata->loadedAt,
                    new \DateTimeImmutable()
                );
                $this->identityMap->updateMetadata($entity, $newMetadata);
            }
        }

        // Remove from other schedules if present
        $this->removeFromSchedule($entity, 'updates');
        $this->removeFromSchedule($entity, 'deletions');
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleUpdate(object $entity): void
    {
        // Don't schedule if already in insertions or deletions
        if (in_array($entity, $this->scheduledInsertions, true) ||
            in_array($entity, $this->scheduledDeletions, true)) {
            return;
        }

        if (!in_array($entity, $this->scheduledUpdates, true)) {
            $this->scheduledUpdates[] = $entity;
        }

        // Ensure entity is managed
        if (!$this->identityMap->isManaged($entity)) {
            $this->identityMap->add($entity);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function scheduleDelete(object $entity): void
    {
        if (in_array($entity, $this->scheduledDeletions, true)) {
            return;
        }

        $this->scheduledDeletions[] = $entity;

        // Update metadata state
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null) {
            $newMetadata = $metadata->withState(EntityState::REMOVED);
            $this->identityMap->updateMetadata($entity, $newMetadata);
        }

        // Remove from other schedules
        $this->removeFromSchedule($entity, 'insertions');
        $this->removeFromSchedule($entity, 'updates');
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        // Remove from all schedules
        $this->removeFromSchedule($entity, 'insertions');
        $this->removeFromSchedule($entity, 'updates');
        $this->removeFromSchedule($entity, 'deletions');

        // Update state to detached
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata !== null) {
            $newMetadata = $metadata->withState(EntityState::DETACHED);
            $this->identityMap->updateMetadata($entity, $newMetadata);
        }

        // Remove from changeset
        unset($this->changeSets[$entity]);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function merge(object $entity): void
    {
        $entityClass = $entity::class;
        $id = $this->extractEntityId($entity);

        if ($id === null) {
            throw new \InvalidArgumentException('Cannot merge entity without identifier');
        }

        // Check if entity is already managed
        $managedEntity = $this->identityMap->get($entityClass, $id);

        if ($managedEntity !== null) {
            // Entity already managed, copy data from detached entity
            $this->copyEntityData($entity, $managedEntity);
            $this->scheduleUpdate($managedEntity);
        } else {
            // Entity not managed, add it as managed
            $this->identityMap->add($entity);
            $metadata = $this->identityMap->getMetadata($entity);
            if ($metadata !== null) {
                $newMetadata = $metadata->withState(EntityState::MANAGED);
                $this->identityMap->updateMetadata($entity, $newMetadata);
            }
        }
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->scheduledInsertions;
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->scheduledUpdates;
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->scheduledDeletions;
    }

    /**
     * @param object $entity
     * @return ChangeSet|null
     */
    public function getChangeSet(object $entity): ?ChangeSet
    {
        return $this->changeSets[$entity] ?? null;
    }

    /**
     * @return array<object>
     */
    public function getEntitiesWithChanges(): array
    {
        $entities = [];
        foreach ($this->changeSets as $entity) {
            $entities[] = $entity;
        }
        return $entities;
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
     * @return bool
     */
    public function hasPendingChanges(): bool
    {
        return !empty($this->scheduledInsertions) ||
            !empty($this->scheduledUpdates) ||
            !empty($this->scheduledDeletions) ||
            count($this->changeSets) > 0;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->changeSets = new SplObjectStorage();
        $this->scheduledInsertions = [];
        $this->scheduledUpdates = [];
        $this->scheduledDeletions = [];
        $this->visitedEntities = [];
    }

    /**
     * @return array{insertions: int, updates: int, deletions: int, changeSets: int, hasChanges: bool}
     */
    public function getStatistics(): array
    {
        return [
            'insertions' => count($this->scheduledInsertions),
            'updates' => count($this->scheduledUpdates),
            'deletions' => count($this->scheduledDeletions),
            'changeSets' => $this->changeSets->count(),
            'hasChanges' => $this->hasPendingChanges(),
        ];
    }

    /**
     * @param object $entity
     * @return void
     */
    private function computeEntityChangeSet(object $entity): void
    {
        // Avoid circular processing
        if (in_array($entity, $this->visitedEntities, true)) {
            return;
        }

        $this->visitedEntities[] = $entity;

        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            return;
        }

        // Only compute changesets for managed entities or new entities with data
        if (!$metadata->isManaged() && !$metadata->isNew()) {
            return;
        }

        $changeSet = $this->changeDetector->computeChangeSet($entity, $metadata->originalData);

        if (!$changeSet->isEmpty()) {
            $this->changeSets[$entity] = $changeSet;

            // Schedule update if not already scheduled for insertion or update
            if (!in_array($entity, $this->scheduledInsertions, true) &&
                !in_array($entity, $this->scheduledUpdates, true)) {
                $this->scheduledUpdates[] = $entity;
            }
        }
    }

    /**
     * @param object $entity
     * @param string $scheduleType
     * @return void
     */
    private function removeFromSchedule(object $entity, string $scheduleType): void
    {
        $property = 'scheduled' . ucfirst($scheduleType);

        if (!property_exists($this, $property)) {
            return;
        }

        $schedule = &$this->$property;
        $key = array_search($entity, $schedule, true);

        if ($key !== false) {
            unset($schedule[$key]);
            $schedule = array_values($schedule); // Reindex array
        }
    }

    /**
     * @param object $entity
     * @return int|string|null
     */
    private function extractEntityId(object $entity): int|string|null
    {
        // Try common ID methods
        foreach (['getId', 'getIdentifier', 'getUuid'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->$method();
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // Try ID properties
        foreach (['id', 'identifier', 'uuid'] as $property) {
            if (property_exists($entity, $property)) {
                $value = $entity->$property;
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param object $source
     * @param object $target
     * @return void
     */
    private function copyEntityData(object $source, object $target): void
    {
        if ($source::class !== $target::class) {
            throw new \InvalidArgumentException('Cannot copy data between different entity types');
        }

        $reflection = new \ReflectionClass($source);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic() || $property->getName() === 'id') {
                continue; // Don't copy static properties or ID
            }

            $property->setAccessible(true);

            try {
                $value = $property->getValue($source);
                $property->setValue($target, $value);
            } catch (\Error $e) {
                // Handle readonly properties or other restrictions
                continue;
            }
        }

        // Update original data in metadata
        $metadata = $this->identityMap->getMetadata($target);
        if ($metadata !== null) {
            $newData = $this->changeDetector->extractCurrentData($target);
            $newMetadata = $metadata->withOriginalData($newData);
            $this->identityMap->updateMetadata($target, $newMetadata);
        }
    }
}
