<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use DateTimeImmutable;
use InvalidArgumentException;
use MulerTech\Database\ORM\EntityMetadata;
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
            $this->updateEntityMetadata($entity, $metadata, EntityState::MANAGED, $metadata->originalData);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function transitionToRemoved(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null && $metadata->state !== EntityState::REMOVED) {
            $this->updateEntityMetadata($entity, $metadata, EntityState::REMOVED, $metadata->originalData);
        }
    }

    /**
     * @param object $entity
     * @return void
     */
    public function transitionToDetached(object $entity): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata !== null && $metadata->state !== EntityState::DETACHED) {
            $this->updateEntityMetadata($entity, $metadata, EntityState::DETACHED, $metadata->originalData);
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
            $this->updateEntityMetadata($entity, $metadata, EntityState::NEW, $metadata->originalData);
        } catch (InvalidArgumentException) {
            $this->updateEntityMetadata($entity, $metadata, EntityState::NEW, $fallbackData);
        }
    }

    /**
     * @param object $entity
     * @param EntityMetadata $metadata
     * @param EntityState $newState
     * @param array<string, mixed> $originalData
     * @return void
     */
    private function updateEntityMetadata(object $entity, EntityMetadata $metadata, EntityState $newState, array $originalData): void
    {
        $newMetadata = new EntityMetadata(
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
