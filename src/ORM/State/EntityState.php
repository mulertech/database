<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * Entity states in the ORM lifecycle
 * @package MulerTech\Database\ORM\State
 * @author Sébastien Muler
 */
enum EntityState: string
{
    case NEW = 'new';
    case MANAGED = 'managed';
    case DETACHED = 'detached';
    case REMOVED = 'removed';

    /**
     * @param self $targetState
     * @return bool
     */
    public function canTransitionTo(self $targetState): bool
    {
        return match ($this) {
            self::NEW => in_array($targetState, [self::MANAGED, self::DETACHED], true),
            self::MANAGED => in_array($targetState, [self::DETACHED, self::REMOVED], true),
            self::DETACHED => $targetState === self::MANAGED,
            self::REMOVED => false, // No transitions from removed state
        };
    }

    /**
     * @return bool
     */
    public function isTransient(): bool
    {
        return $this === self::NEW || $this === self::DETACHED;
    }

    /**
     * @return bool
     */
    public function isPersistent(): bool
    {
        return $this === self::MANAGED || $this === self::REMOVED;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::NEW => 'Entity is new and not yet persisted',
            self::MANAGED => 'Entity is managed by the ORM',
            self::DETACHED => 'Entity is detached from the ORM',
            self::REMOVED => 'Entity is marked for deletion',
        };
    }
}
