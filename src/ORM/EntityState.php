<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\State\EntityLifecycleState;

/**
 * Runtime state for entities in the ORM.
 *
 * @author Sébastien Muler
 */
final readonly class EntityState
{
    /**
     * @param class-string         $className
     * @param array<string, mixed> $originalData
     */
    public function __construct(
        public string $className,
        public EntityLifecycleState $state,
        public array $originalData,
        public \DateTimeImmutable $lastModified,
    ) {
    }

    public function isManaged(): bool
    {
        return EntityLifecycleState::MANAGED === $this->state;
    }

    public function isNew(): bool
    {
        return EntityLifecycleState::NEW === $this->state;
    }

    public function isRemoved(): bool
    {
        return EntityLifecycleState::REMOVED === $this->state;
    }

    public function isDetached(): bool
    {
        return EntityLifecycleState::DETACHED === $this->state;
    }
}
