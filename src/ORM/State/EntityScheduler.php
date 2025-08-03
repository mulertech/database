<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use InvalidArgumentException;
use MulerTech\Database\ORM\ChangeSetManager;
use MulerTech\Database\ORM\IdentityMap;

/**
 * Handles entity scheduling operations
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class EntityScheduler
{
    public function __construct(
        private IdentityMap $identityMap,
        private StateValidator $stateValidator,
        private ?ChangeSetManager $changeSetManager = null
    ) {
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return void
     */
    public function scheduleForInsertion(object $entity, EntityState $currentState): void
    {
        if (!$this->stateValidator->validateOperation($entity, $currentState, 'persist')) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for insertion',
                    $currentState->value
                )
            );
        }

        $this->changeSetManager?->scheduleInsert($entity);
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return void
     */
    public function scheduleForUpdate(object $entity, EntityState $currentState): void
    {
        if (!$this->stateValidator->validateOperation($entity, $currentState, 'update')) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for update',
                    $currentState->value
                )
            );
        }

        $this->changeSetManager?->scheduleUpdate($entity);
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return void
     */
    public function scheduleForDeletion(object $entity, EntityState $currentState): void
    {
        if (!$this->stateValidator->validateOperation($entity, $currentState, 'remove')) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot schedule entity in %s state for deletion',
                    $currentState->value
                )
            );
        }

        $this->changeSetManager?->scheduleDelete($entity);
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledInsertions(): array
    {
        if ($this->changeSetManager !== null) {
            return $this->changeSetManager->getScheduledInsertions();
        }

        $insertions = [];
        foreach ($this->identityMap->getEntitiesByState(EntityState::NEW) as $entity) {
            $insertions[spl_object_id($entity)] = $entity;
        }

        return $insertions;
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledUpdates(): array
    {
        if ($this->changeSetManager !== null) {
            return $this->changeSetManager->getScheduledUpdates();
        }

        return [];
    }

    /**
     * @return array<int, object>
     */
    public function getScheduledDeletions(): array
    {
        if ($this->changeSetManager !== null) {
            return $this->changeSetManager->getScheduledDeletions();
        }

        $deletions = [];
        foreach ($this->identityMap->getEntitiesByState(EntityState::REMOVED) as $entity) {
            $deletions[spl_object_id($entity)] = $entity;
        }

        return $deletions;
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    public function isScheduledForInsertion(object $entity, EntityState $currentState): bool
    {
        if ($this->changeSetManager !== null) {
            $scheduled = $this->changeSetManager->getScheduledInsertions();
            return in_array($entity, $scheduled, true);
        }

        return $currentState === EntityState::NEW;
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
     * @param EntityState $currentState
     * @return bool
     */
    public function isScheduledForDeletion(object $entity, EntityState $currentState): bool
    {
        if ($this->changeSetManager !== null) {
            $scheduled = $this->changeSetManager->getScheduledDeletions();
            return in_array($entity, $scheduled, true);
        }

        return $currentState === EntityState::REMOVED;
    }
}
