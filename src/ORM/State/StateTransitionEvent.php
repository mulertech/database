<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use InvalidArgumentException;
use MulerTech\EventManager\Event;

/**
 * Event dispatched during entity state transitions
 * @package MulerTech\Database\ORM\State
 * @author Sébastien Muler
 */
final class StateTransitionEvent extends Event
{
    /**
     * @param object $entity
     * @param EntityState $fromState
     * @param EntityState $toState
     * @param string $phase
     */
    public function __construct(
        private readonly object $entity,
        private readonly EntityState $fromState,
        private readonly EntityState $toState,
        private readonly string $phase
    ) {
        if (!in_array($phase, ['pre', 'post'], true)) {
            throw new InvalidArgumentException('Phase must be either "pre" or "post"');
        }
        $this->setName($phase . 'StateTransition');
    }

    /**
     * @return object
     */
    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return EntityState
     */
    public function getFromState(): EntityState
    {
        return $this->fromState;
    }

    /**
     * @return EntityState
     */
    public function getToState(): EntityState
    {
        return $this->toState;
    }

    /**
     * @return string
     */
    public function getPhase(): string
    {
        return $this->phase;
    }

    /**
     * @return bool
     */
    public function isPreTransition(): bool
    {
        return $this->phase === 'pre';
    }

    /**
     * @return bool
     */
    public function isPostTransition(): bool
    {
        return $this->phase === 'post';
    }

    /**
     * @return string
     */
    public function getTransitionKey(): string
    {
        return sprintf(
            '%s:%s->%s',
            $this->entity::class,
            $this->fromState->value,
            $this->toState->value
        );
    }
}
