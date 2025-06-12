<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

use MulerTech\Database\Event\AbstractEntityEvent;
use MulerTech\EventManager\Event;

/**
 * Événement de transition d'état d'entité
 * @package MulerTech\Database\ORM\State
 * @author Sébastien Muler
 */
final class StateTransitionEvent extends Event
{
    public const PRE_TRANSITION = 'orm.state.pre_transition';
    public const POST_TRANSITION = 'orm.state.post_transition';

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
        private readonly string $phase = 'pre'
    ) {
        $this->setName($this->getEventName());
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
     * @return class-string
     */
    public function getEntityClass(): string
    {
        return $this->entity::class;
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

    /**
     * @return array{
     *     entity_class: class-string,
     *     from_state: string,
     *     to_state: string,
     *     phase: string,
     *     transition_key: string
     * }
     */
    public function toArray(): array
    {
        return [
            'entity_class' => $this->getEntityClass(),
            'from_state' => $this->fromState->value,
            'to_state' => $this->toState->value,
            'phase' => $this->phase,
            'transition_key' => $this->getTransitionKey(),
        ];
    }

    /**
     * @return string
     */
    private function getEventName(): string
    {
        return $this->phase === 'pre' ? self::PRE_TRANSITION : self::POST_TRANSITION;
    }
}
