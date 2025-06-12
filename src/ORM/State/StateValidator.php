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

    /** @var array<string, string> */
    private array $errorMessages = [];

    /** @var bool */
    private bool $strictMode = true;

    /** @var array<string, mixed> */
    private array $validationContext = [];

    public function __construct()
    {
        $this->initializeDefaultMessages();
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
            'remove' => $this->validateRemove($entity, $currentState),
            'merge' => $this->validateMerge($entity, $currentState),
            'detach' => $this->validateDetach($entity, $currentState),
            'refresh' => $this->validateRefresh($entity, $currentState),
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
            $this->setError(
                'invalid_transition',
                sprintf(
                    'Cannot transition from %s to %s',
                    $from->value,
                    $to->value
                )
            );
            return false;
        }

        // Entity-specific validation
        $entityClass = $entity::class;
        $validatorKey = sprintf('%s:%s:%s', $entityClass, $from->value, $to->value);

        if (isset($this->validators[$entityClass][$validatorKey])) {
            $validator = $this->validators[$entityClass][$validatorKey];
            $result = $validator($entity, $from, $to, $this->validationContext);

            if (!$result) {
                $this->setError(
                    'custom_validation_failed',
                    sprintf(
                        'Custom validation failed for %s transition from %s to %s',
                        $entityClass,
                        $from->value,
                        $to->value
                    )
                );
            }

            return $result;
        }

        return true;
    }

    /**
     * @param class-string $entityClass
     * @param EntityState $from
     * @param EntityState $to
     * @param callable $validator
     * @return void
     */
    public function registerTransitionValidator(
        string $entityClass,
        EntityState $from,
        EntityState $to,
        callable $validator
    ): void {
        $key = sprintf('%s:%s:%s', $entityClass, $from->value, $to->value);
        $this->validators[$entityClass][$key] = $validator;
    }

    /**
     * @param string $operation
     * @param EntityState $requiredState
     * @param string $errorMessage
     * @return void
     */
    public function registerOperationRule(
        string $operation,
        EntityState $requiredState,
        string $errorMessage
    ): void {
        $this->errorMessages["operation_$operation"] = $errorMessage;
    }

    /**
     * @param bool $strict
     * @return void
     */
    public function setStrictMode(bool $strict): void
    {
        $this->strictMode = $strict;
    }

    /**
     * @param array<string, mixed> $context
     * @return void
     */
    public function setValidationContext(array $context): void
    {
        $this->validationContext = $context;
    }

    /**
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->errorMessages['last_error'] ?? null;
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validatePersist(object $entity, EntityState $currentState): bool
    {
        if ($currentState !== EntityState::NEW && $currentState !== EntityState::DETACHED) {
            $this->setError(
                'persist_invalid_state',
                sprintf(
                    'Cannot persist entity in %s state. Entity must be NEW or DETACHED.',
                    $currentState->value
                )
            );
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
            $this->setError(
                'update_invalid_state',
                sprintf(
                    'Cannot update entity in %s state. Entity must be MANAGED.',
                    $currentState->value
                )
            );
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validateRemove(object $entity, EntityState $currentState): bool
    {
        if ($currentState === EntityState::NEW) {
            $this->setError(
                'remove_invalid_state',
                'Cannot remove entity in NEW state. Entity must be persisted first.'
            );
            return false;
        }

        if ($currentState === EntityState::REMOVED) {
            $this->setError(
                'already_removed',
                'Entity is already scheduled for removal.'
            );
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
            $this->setError(
                'merge_invalid_state',
                sprintf(
                    'Cannot merge entity in %s state. Entity must be DETACHED.',
                    $currentState->value
                )
            );
            return false;
        }

        return $this->validateEntityIntegrity($entity);
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validateDetach(object $entity, EntityState $currentState): bool
    {
        if ($currentState !== EntityState::MANAGED) {
            $this->setError(
                'detach_invalid_state',
                sprintf(
                    'Cannot detach entity in %s state. Entity must be MANAGED.',
                    $currentState->value
                )
            );
            return false;
        }

        return true;
    }

    /**
     * @param object $entity
     * @param EntityState $currentState
     * @return bool
     */
    private function validateRefresh(object $entity, EntityState $currentState): bool
    {
        if ($currentState !== EntityState::MANAGED) {
            $this->setError(
                'refresh_invalid_state',
                sprintf(
                    'Cannot refresh entity in %s state. Entity must be MANAGED.',
                    $currentState->value
                )
            );
            return false;
        }

        return true;
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
            $this->setError(
                'unknown_operation',
                sprintf('Unknown operation "%s" in strict mode', $operation)
            );
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
        if (!method_exists($entity, 'getId') &&
            !method_exists($entity, 'getIdentifier') &&
            !method_exists($entity, 'getUuid')) {
            if ($this->strictMode) {
                $this->setError(
                    'missing_identifier',
                    'Entity must have an identifier method (getId, getIdentifier, or getUuid)'
                );
                return false;
            }
        }

        // Additional integrity checks can be added here
        return true;
    }

    /**
     * @param string $code
     * @param string $message
     * @return void
     */
    private function setError(string $code, string $message): void
    {
        $this->errorMessages[$code] = $message;
        $this->errorMessages['last_error'] = $message;
    }

    /**
     * @return void
     */
    private function initializeDefaultMessages(): void
    {
        $this->errorMessages = [
            'invalid_transition' => 'Invalid state transition',
            'persist_invalid_state' => 'Cannot persist entity in current state',
            'update_invalid_state' => 'Cannot update entity in current state',
            'remove_invalid_state' => 'Cannot remove entity in current state',
            'merge_invalid_state' => 'Cannot merge entity in current state',
            'detach_invalid_state' => 'Cannot detach entity in current state',
            'refresh_invalid_state' => 'Cannot refresh entity in current state',
            'already_removed' => 'Entity is already scheduled for removal',
            'missing_identifier' => 'Entity must have an identifier',
            'unknown_operation' => 'Unknown operation',
            'custom_validation_failed' => 'Custom validation failed'
        ];
    }
}
