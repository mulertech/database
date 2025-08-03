<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\State\EntityLifecycleState;

/**
 * Runtime state for entities in the ORM
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class EntityState
{
    /**
     * @param class-string $className
     * @param int|string $identifier
     * @param EntityLifecycleState $state
     * @param array<string, mixed> $originalData
     * @param DateTimeImmutable $loadedAt
     * @param DateTimeImmutable|null $lastModified
     */
    public function __construct(
        public string $className,
        public int|string $identifier,
        public EntityLifecycleState $state,
        public array $originalData,
        public DateTimeImmutable $loadedAt,
        public ?DateTimeImmutable $lastModified = null
    ) {
    }

    /**
     * @return bool
     */
    public function isManaged(): bool
    {
        return $this->state === EntityLifecycleState::MANAGED;
    }

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->state === EntityLifecycleState::NEW;
    }
}
