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
    public function transitionToManaged(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null) {
            $this->updateEntityMetadata($entity, $metadata, EntityLifecycleState::MANAGED, $metadata->originalData);
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
     * @param array<string, mixed> $fallbackData
     * @return void
     */
    public function tryTransitionToNew(object $entity, array $fallbackData): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null) {
            return;
        }

        try {
            $this->updateEntityMetadata($entity, $metadata, EntityLifecycleState::NEW, $metadata->originalData);
        } catch (InvalidArgumentException) {
            $this->updateEntityMetadata($entity, $metadata, EntityLifecycleState::NEW, $fallbackData);
        }
    }

    /**
     * @param object $entity
     * @param EntityState $metadata
     * @param EntityLifecycleState $newState
     * @param array<string, mixed> $originalData
     * @return void
     */
    private function updateEntityMetadata(object $entity, EntityState $metadata, EntityLifecycleState $newState, array $originalData): void
    {
        $newMetadata = new EntityState(
            $metadata->className,
            $metadata->identifier,
            $newState,
            $originalData,
            $metadata->loadedAt,
            new DateTimeImmutable()
        );

        $this->identityMap->updateMetadata($entity, $newMetadata);
    }
}
