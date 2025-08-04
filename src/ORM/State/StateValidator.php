<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * State validator for entity operations
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class StateValidator
{
    /** @var array<class-string, array<string, callable>> */
    private array $validators = [];

    /** @var bool */
    private bool $strictMode = true;

    /** @var array<string, mixed> */
    private array $validationContext = [];

    /**
     * @param object $entity
     * @param EntityLifecycleState $currentState
     * @param string $operation
     * @return bool
     */
    public function validateOperation(object $entity, EntityLifecycleState $currentState, string $operation): bool
    {
        return match ($operation) {
            'persist' => $this->validatePersist($entity, $currentState),
            'update' => $this->validateUpdate($entity, $currentState),
            'remove' => $this->validateRemove($currentState),
            'merge' => $this->validateMerge($entity, $currentState),
            'detach' => $this->validateDetach($currentState),
            'refresh' => $this->validateRefresh($currentState),
            default => $this->validateCustomOperation($entity, $currentState, $operation)
        };
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $currentState
     * @return bool
     */
    private function validatePersist(object $entity, EntityLifecycleState $currentState): bool
    {
        if ($currentState !== EntityLifecycleState::NEW && $currentState !== EntityLifecycleState::DETACHED) {
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $currentState
     * @return bool
     */
    private function validateUpdate(object $entity, EntityLifecycleState $currentState): bool
    {
        if ($currentState !== EntityLifecycleState::MANAGED) {
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param EntityLifecycleState $currentState
     * @return bool
     */
    private function validateRemove(EntityLifecycleState $currentState): bool
    {
        return $currentState !== EntityLifecycleState::REMOVED;
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $currentState
     * @return bool
     */
    private function validateMerge(object $entity, EntityLifecycleState $currentState): bool
    {
        if ($currentState !== EntityLifecycleState::DETACHED) {
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param EntityLifecycleState $currentState
     * @return bool
     */
    private function validateDetach(EntityLifecycleState $currentState): bool
    {
        // Allow detaching entities that are NEW or MANAGED
        return $currentState === EntityLifecycleState::MANAGED || $currentState === EntityLifecycleState::NEW;
    }

    /**
     * @param EntityLifecycleState $currentState
     * @return bool
     */
    private function validateRefresh(EntityLifecycleState $currentState): bool
    {
        return $currentState === EntityLifecycleState::MANAGED;
    }

    /**
     * @param object $entity
     * @param EntityLifecycleState $currentState
     * @param string $operation
     * @return bool
     */
    private function validateCustomOperation(object $entity, EntityLifecycleState $currentState, string $operation): bool
    {
        // Check if there's a custom validator for this operation
        $entityClass = $entity::class;
        $validatorKey = sprintf('%s:operation:%s', $entityClass, $operation);

        if (isset($this->validators[$entityClass][$validatorKey])) {
            $validator = $this->validators[$entityClass][$validatorKey];
            return $validator($entity, $currentState, $this->validationContext);
        }

        // In strict mode, unknown operations are not allowed
        if ($this->strictMode) {
            return false;
        }

        return true;
    }

    /**
     * @param object $entity
     * @return bool
     */
    private function validateEntityIntegrity(object $entity): bool
    {
        // Check if entity has an ID method
        return !($this->strictMode && !method_exists($entity, 'getId') &&
            !method_exists($entity, 'getIdentifier') &&
            !method_exists($entity, 'getUuid'));
    }
}
