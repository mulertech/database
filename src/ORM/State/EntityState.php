<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\State;

/**
 * États possibles d'une entité dans le contexte de persistance
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
     * @param EntityState $state
     * @return bool
     */
    public function canTransitionTo(self $state): bool
    {
        // Toujours permettre les transitions vers le même état
        if ($this === $state) {
            return true;
        }

        return match ($this) {
            // Permettre NEW → MANAGED, NEW → REMOVED, et aussi MANAGED → NEW (pour scheduleInsert)
            self::NEW => in_array($state, [self::MANAGED, self::REMOVED], true),
            self::MANAGED => in_array($state, [self::DETACHED, self::REMOVED, self::NEW], true),
            self::DETACHED => in_array($state, [self::MANAGED, self::NEW], true),
            self::REMOVED => $state === self::DETACHED, // Permettre de restaurer une entité supprimée
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
        return $this === self::MANAGED;
    }

    /**
     * @return bool
     */
    public function isScheduledForRemoval(): bool
    {
        return $this === self::REMOVED;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::NEW => 'Entity is new and not yet persisted',
            self::MANAGED => 'Entity is managed by the EntityManager',
            self::DETACHED => 'Entity was managed but is now detached',
            self::REMOVED => 'Entity is scheduled for removal',
        };
    }
}
