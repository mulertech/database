<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * @author Sébastien Muler
 */
enum EntityLifecycleState: string
{
    case NEW = 'new';
    case MANAGED = 'managed';
    case REMOVED = 'removed';
    case DETACHED = 'detached';

    /**
     * Check if transition to target state is allowed.
     */
    public function canTransitionTo(EntityLifecycleState $to): bool
    {
        return match ($this) {
            self::NEW => self::MANAGED === $to || self::DETACHED === $to || self::REMOVED === $to,
            self::MANAGED => self::REMOVED === $to || self::DETACHED === $to,
            self::REMOVED, self::DETACHED => false,
        };
    }
}
