<?php

declare(strict_types=1);

namespace MulerTech\Database\Event;

use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\EventManager\Event;

/**
 * Event dispatched during entity state transitions.
 *
 * @author Sébastien Muler
 */
final class StateTransitionEvent extends Event
{
    public function __construct(
        private readonly object $entity,
        private readonly EntityLifecycleState $fromState,
        private readonly EntityLifecycleState $toState,
        private readonly string $phase,
    ) {
        if (!in_array($phase, ['pre', 'post'], true)) {
            throw new \InvalidArgumentException('Phase must be either "pre" or "post"');
        }
        $this->setName($phase.'StateTransition');
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getFromState(): EntityLifecycleState
    {
        return $this->fromState;
    }

    public function getToState(): EntityLifecycleState
    {
        return $this->toState;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function isPreTransition(): bool
    {
        return 'pre' === $this->phase;
    }

    public function isPostTransition(): bool
    {
        return 'post' === $this->phase;
    }

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
