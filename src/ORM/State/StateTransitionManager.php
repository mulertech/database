<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use InvalidArgumentException;
use MulerTech\Database\Event\StateTransitionEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class StateTransitionManager
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class StateTransitionManager
{
    /** @var array<string, callable> */
    private array $preTransitionHooks = [];

    /** @var array<string, callable> */
    private array $postTransitionHooks = [];

    /** @var array<string, bool> */
    private array $customTransitions = [];

    /** @var array<int, array{from: EntityState, to: EntityState, entity: object, timestamp: int}> */
    private array $transitionHistory = [];

    /** @var bool */
    private bool $enableHistory = true;

    /** @var int */
    private int $maxHistorySize = 1000;
    /** @var StateValidator|null */
    private ?StateValidator $stateValidator = null;

    /**
     * @param EventDispatcherInterface|null $eventDispatcher
     * @param StateValidator|null $stateValidator
     */
    public function __construct(
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?StateValidator $stateValidator = null
    ) {
        // StateValidator sera créé lazily si pas fourni
        if ($stateValidator !== null) {
            $this->stateValidator = $stateValidator;
        }
    }

    /**
     * @return StateValidator
     */
    private function getStateValidator(): StateValidator
    {
        if (!isset($this->stateValidator)) {
            $this->stateValidator = new StateValidator();
        }
        return $this->stateValidator;
    }

    /**
     * @param object $entity
     * @param EntityState $from
     * @param EntityState $to
     * @return void
     * @throws InvalidArgumentException
     */
    public function transition(object $entity, EntityState $from, EntityState $to): void
    {
        // Validate transition
        if (!$this->canTransition($entity, $from, $to)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid state transition from %s to %s for entity %s',
                    $from->value,
                    $to->value,
                    $entity::class
                )
            );
        }

        // Execute pre-transition hooks
        $this->executePreTransitionHooks($entity, $from, $to);

        // Dispatch pre-transition event
        if ($this->eventDispatcher !== null) {
            $event = new StateTransitionEvent($entity, $from, $to, 'pre');
            $this->eventDispatcher->dispatch($event);
        }

        // The actual state change is handled by the caller

        // Execute post-transition hooks
        $this->executePostTransitionHooks($entity, $from, $to);

        // Dispatch post-transition event
        if ($this->eventDispatcher !== null) {
            $event = new StateTransitionEvent($entity, $from, $to, 'post');
            $this->eventDispatcher->dispatch($event);
        }

        // Record transition in history
        if ($this->enableHistory) {
            $this->recordTransition($entity, $from, $to);
        }
    }

    /**
     * @param object $entity
     * @param EntityState $from
     * @param EntityState $to
     * @return bool
     */
    public function canTransition(object $entity, EntityState $from, EntityState $to): bool
    {
        // Check custom transitions first
        $customKey = $this->getCustomTransitionKey($entity::class, $from, $to);
        if (isset($this->customTransitions[$customKey])) {
            return $this->customTransitions[$customKey];
        }

        // Check default transitions
        if (!$from->canTransitionTo($to)) {
            return false;
        }

        // Additional validation
        return $this->getStateValidator()->validateTransition($entity, $from, $to);
    }

    /**
     * @param object $entity
     * @param EntityState $from
     * @param EntityState $to
     * @return void
     */
    private function executePreTransitionHooks(object $entity, EntityState $from, EntityState $to): void
    {
        $hooks = $this->findMatchingHooks($this->preTransitionHooks, $from, $to);

        foreach ($hooks as $hook) {
            $hook($entity, $from, $to);
        }
    }

    /**
     * @param object $entity
     * @param EntityState $from
     * @param EntityState $to
     * @return void
     */
    private function executePostTransitionHooks(object $entity, EntityState $from, EntityState $to): void
    {
        $hooks = $this->findMatchingHooks($this->postTransitionHooks, $from, $to);

        foreach ($hooks as $hook) {
            $hook($entity, $from, $to);
        }
    }

    /**
     * @param array<string, callable> $hooks
     * @param EntityState $from
     * @param EntityState $to
     * @return array<callable>
     */
    private function findMatchingHooks(array $hooks, EntityState $from, EntityState $to): array
    {
        $matchingHooks = [];

        foreach ($hooks as $key => $hook) {
            [$fromValue, $toValue] = explode(':', $key . '::');

            $fromMatch = $fromValue === '*' || $fromValue === $from->value;
            $toMatch = $toValue === '*' || $toValue === $to->value;

            if ($fromMatch && $toMatch) {
                $matchingHooks[] = $hook;
            }
        }

        return $matchingHooks;
    }

    /**
     * @param object $entity
     * @param EntityState $from
     * @param EntityState $to
     * @return void
     */
    private function recordTransition(object $entity, EntityState $from, EntityState $to): void
    {
        $this->transitionHistory[] = [
            'from' => $from,
            'to' => $to,
            'entity' => $entity,
            'timestamp' => time(),
        ];

        $this->pruneHistory();
    }

    /**
     * @return void
     */
    private function pruneHistory(): void
    {
        if (count($this->transitionHistory) > $this->maxHistorySize) {
            $this->transitionHistory = array_slice(
                $this->transitionHistory,
                -$this->maxHistorySize,
                null,
                true
            );
        }
    }

    /**
     * @param class-string $entityClass
     * @param EntityState $from
     * @param EntityState $to
     * @return string
     */
    private function getCustomTransitionKey(string $entityClass, EntityState $from, EntityState $to): string
    {
        return sprintf('%s:%s:%s', $entityClass, $from->value, $to->value);
    }
}
