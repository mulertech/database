<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use DateTimeImmutable;
use InvalidArgumentException;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Manages entity state transitions and metadata updates
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class EntityStateManager
{
    public function __construct(
        private IdentityMap $identityMap
    ) {
    }

    /**
     * @param object $entity
     * @return void
     */
    public function transitionToNew(object $entity): void
    {
        $this->identityMap->add($entity);

        // Update metadata to NEW state
        $entityClassName = $entity::class;
        $entityState = new EntityState(
            $entityClassName,
            EntityLifecycleState::NEW,
            [],
            new DateTimeImmutable()
        );
        $this->identityMap->updateMetadata($entity, $entityState);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function transitionToManaged(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null) {
            $this->updateEntityMetadata($entity, $metadata, EntityLifecycleState::MANAGED, $metadata->originalData);
        } else {
            $this->identityMap->add($entity);
            $entityClassName = $entity::class;
            $entityState = new EntityState(
                $entityClassName,
                EntityLifecycleState::MANAGED,
                [],
                new DateTimeImmutable()
            );
            $this->identityMap->updateMetadata($entity, $entityState);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function transitionToRemoved(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null && $metadata->state !== EntityLifecycleState::REMOVED) {
            $this->updateEntityMetadata($entity, $metadata, EntityLifecycleState::REMOVED, $metadata->originalData);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function transitionToDetached(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null && $metadata->state !== EntityLifecycleState::DETACHED) {
            $this->updateEntityMetadata($entity, $metadata, EntityLifecycleState::DETACHED, $metadata->originalData);
        }
    }

    /**
     * @param object $entity
     * @return EntityLifecycleState|null
     */
    public function getCurrentState(object $entity): ?EntityLifecycleState
    {
        return $this->identityMap->getEntityState($entity);
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $state
     * @return bool
     */
    public function isInState(object $entity, EntityLifecycleState $state): bool
    {
        $currentState = $this->getCurrentState($entity);
        return $currentState === $state;
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $targetState
     * @return bool
     */
    public function canTransitionTo(object $entity, EntityLifecycleState $targetState): bool
    {
        $currentState = $this->getCurrentState($entity);

        if ($currentState === null) {
            return $targetState === EntityLifecycleState::NEW || $targetState === EntityLifecycleState::MANAGED;
        }

        return match ($currentState) {
            EntityLifecycleState::NEW => $targetState === EntityLifecycleState::MANAGED || $targetState === EntityLifecycleState::DETACHED,
            EntityLifecycleState::MANAGED => $targetState === EntityLifecycleState::REMOVED || $targetState === EntityLifecycleState::DETACHED,
            EntityLifecycleState::REMOVED => false,
            EntityLifecycleState::DETACHED => false,
        };
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $originalData
     * @return void
     * @throws InvalidArgumentException
     */
    public function updateOriginalData(object $entity, array $originalData): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null) {
            throw new InvalidArgumentException('Entity is not managed');
        }

        $this->updateEntityMetadata($entity, $metadata, $metadata->state, $originalData);
    }

    /**
     * @param object $entity
     * @return array<string, mixed>|null
     */
    public function getOriginalData(object $entity): ?array
    {
        $metadata = $this->identityMap->getMetadata($entity);
        return $metadata?->originalData;
    }

    /**
     * @param object $entity
     * @param int|string $id
     * @return void
     * @throws InvalidArgumentException
     */
    public function markAsPersisted(object $entity, int|string $id): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null) {
            throw new InvalidArgumentException('Entity is not managed');
        }

        // Check if entity is already in MANAGED state with an ID
        $currentEntityState = $this->identityMap->getEntityState($entity);
        if ($currentEntityState === EntityLifecycleState::MANAGED) {
            // Check if entity already has an ID (is already persisted)
            $entityClass = $entity::class;

            // Try to extract current ID from entity
            $currentId = null;
            if (method_exists($entity, 'getId')) {
                $currentId = $entity->getId();
            }

            // If entity already has an ID, it's already persisted
            if ($currentId !== null) {
                throw new InvalidArgumentException('Entity is already persisted');
            }
        }

        // Remove current entry and re-add with MANAGED state
        $this->identityMap->remove($entity);

        // Set the ID on the entity if it has a setter
        if (method_exists($entity, 'setId')) {
            $entity->setId($id);
        }

        $this->identityMap->add($entity);

        // Update to MANAGED state
        $entityClassName = $entity::class;
        $entityState = new EntityState(
            $entityClassName,
            EntityLifecycleState::MANAGED,
            $metadata->originalData,
            $metadata->lastModified
        );
        $this->identityMap->updateMetadata($entity, $entityState);
    }

    /**
     * @param object $entity
     * @param EntityState $metadata
     * @param EntityLifecycleState $newState
     * @param array<string, mixed> $originalData
     * @return void
     */
    private function updateEntityMetadata(
        object $entity,
        EntityState $metadata,
        EntityLifecycleState $newState,
        array $originalData
    ): void {
        $entityClassName = $entity::class;
        $newEntityState = new EntityState(
            $entityClassName,
            $newState,
            $originalData,
            new DateTimeImmutable()
        );

        $this->identityMap->updateMetadata($entity, $newEntityState);
    }
}
