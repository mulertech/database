<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * Validateur d'état pour les opérations sur les entités
 * @package MulerTech\Database\ORM\State
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

    public function __construct()
    {
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @param string $operation
     * @return bool
     */
    public function validateOperation(object $entity, EntityState $currentState, string $operation): bool
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
     * @param EntityState $from
     * @param EntityState $to
     * @return bool
     */
    public function validateTransition(object $entity, EntityState $from, EntityState $to): bool
    {
        // Basic state transition validation
        if (!$from->canTransitionTo($to)) {
            return false;
        }

        // Entity-specific validation
        $entityClass = $entity::class;
        $validatorKey = sprintf('%s:%s:%s', $entityClass, $from->value, $to->value);

        if (isset($this->validators[$entityClass][$validatorKey])) {
            $validator = $this->validators[$entityClass][$validatorKey];
            $result = $validator($entity, $from, $to, $this->validationContext);

            if (!$result) {
                return false;
            }

            return $result;
        }

        return true;
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validatePersist(object $entity, EntityState $currentState): bool
    {
        if ($currentState !== EntityState::NEW && $currentState !== EntityState::DETACHED) {
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validateUpdate(object $entity, EntityState $currentState): bool
    {
        if ($currentState !== EntityState::MANAGED) {
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param EntityState $currentState
     * @return bool
     */
    private function validateRemove(EntityState $currentState): bool
    {
        if ($currentState === EntityState::NEW) {
            return false;
        }

        if ($currentState === EntityState::REMOVED) {
            return false;
        }

        return true;
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validateMerge(object $entity, EntityState $currentState): bool
    {
        if ($currentState !== EntityState::DETACHED) {
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param EntityState $currentState
     * @return bool
     */
    private function validateDetach(EntityState $currentState): bool
    {
        return $currentState === EntityState::MANAGED;
    }

    /**
     * @param EntityState $currentState
     * @return bool
     */
    private function validateRefresh(EntityState $currentState): bool
    {
        return $currentState === EntityState::MANAGED;
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @param string $operation
     * @return bool
     */
    private function validateCustomOperation(object $entity, EntityState $currentState, string $operation): bool
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
