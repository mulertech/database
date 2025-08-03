<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use InvalidArgumentException;
use MulerTech\Database\ORM\IdentityMap;
use MulerTech\Database\ORM\EntityState;
use DateTimeImmutable;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class StateTransitionManager
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $transitionHistory = [];

    /**
     * @param IdentityMap $identityMap
     */
    public function __construct(
        private readonly IdentityMap $identityMap
    ) {
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $targetState
     * @return void
     * @throws InvalidArgumentException
     */
    public function transition(object $entity, EntityLifecycleState $targetState): void
    {
        $metadata = $this->identityMap->getMetadata($entity);

        if ($metadata === null) {
            throw new InvalidArgumentException('Entity is not managed');
        }

        $currentState = $metadata->state;

        if (!$this->canTransition($currentState, $targetState)) {
            throw new InvalidArgumentException(
                sprintf('Invalid state transition from %s to %s', $currentState->name, $targetState->name)
            );
        }

        // Record transition
        $this->recordTransition($entity, $currentState, $targetState);

        // Update entity state
        $this->updateEntityState($entity, $targetState, $metadata);
    }

    /**
     * @param EntityLifecycleState $from
     * @param EntityLifecycleState $to
     * @return bool
     */
    public function canTransition(EntityLifecycleState $from, EntityLifecycleState $to): bool
    {
        return match ($from) {
            EntityLifecycleState::NEW => $to === EntityLifecycleState::MANAGED || $to === EntityLifecycleState::DETACHED,
            EntityLifecycleState::MANAGED => $to === EntityLifecycleState::REMOVED || $to === EntityLifecycleState::DETACHED,
            EntityLifecycleState::REMOVED => false,
            EntityLifecycleState::DETACHED => false,
        };
    }

    /**
     * @param EntityLifecycleState $state
     * @return array<EntityLifecycleState>
     */
    public function getValidTransitions(EntityLifecycleState $state): array
    {
        return match ($state) {
            EntityLifecycleState::NEW => [EntityLifecycleState::MANAGED, EntityLifecycleState::DETACHED],
            EntityLifecycleState::MANAGED => [EntityLifecycleState::REMOVED, EntityLifecycleState::DETACHED],
            EntityLifecycleState::REMOVED => [],
            EntityLifecycleState::DETACHED => [],
        };
    }

    /**
     * @param object $entity
     * @return array<array<string, mixed>>
     */
    public function getTransitionHistory(object $entity): array
    {
        $entityId = spl_object_id($entity);
        return array_filter(
            $this->transitionHistory,
            fn($transition) => $transition['entity_id'] === $entityId
        );
    }

    /**
     * @param object $entity
     * @return void
     */
    public function clearTransitionHistory(object $entity): void
    {
        $entityId = spl_object_id($entity);
        $this->transitionHistory = array_filter(
            $this->transitionHistory,
            fn($transition) => $transition['entity_id'] !== $entityId
        );
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $from
     * @param EntityLifecycleState $to
     * @return void
     */
    private function recordTransition(object $entity, EntityLifecycleState $from, EntityLifecycleState $to): void
    {
        $this->transitionHistory[] = [
            'entity_id' => spl_object_id($entity),
            'from' => $from,
            'to' => $to,
            'timestamp' => new DateTimeImmutable(),
        ];
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $newState
     * @param EntityState $currentMetadata
     * @return void
     */
    private function updateEntityState(object $entity, EntityLifecycleState $newState, EntityState $currentMetadata): void
    {
        $entityClassName = $entity::class;
        $newEntityState = new EntityState(
            $entityClassName,
            $newState,
            $currentMetadata->originalData,
            new DateTimeImmutable()
        );

        $this->identityMap->updateMetadata($entity, $newEntityState);
    }
}
