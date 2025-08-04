<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
enum EntityLifecycleState: string
{
    case NEW = 'new';
    case MANAGED = 'managed';
    case REMOVED = 'removed';
    case DETACHED = 'detached';

    /**
     * Check if transition to target state is allowed
     * @param EntityLifecycleState $to
     * @return bool
     */
    public function canTransitionTo(EntityLifecycleState $to): bool
    {
        return match ($this) {
            self::NEW => $to === self::MANAGED || $to === self::DETACHED || $to === self::REMOVED,
            self::MANAGED => $to === self::REMOVED || $to === self::DETACHED,
            self::REMOVED, self::DETACHED => false,
        };
    }
}
