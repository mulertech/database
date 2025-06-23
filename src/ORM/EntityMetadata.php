<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\State\EntityState;

/**
 * Metadata for entities in the ORM
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final readonly class EntityMetadata
{
    /**
     * @param class-string $className
     * @param int|string $identifier
     * @param EntityState $state
     * @param array<string, mixed> $originalData
     * @param DateTimeImmutable $loadedAt
     * @param DateTimeImmutable|null $lastModified
     */
    public function __construct(
        public string $className,
        public int|string $identifier,
        public EntityState $state,
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
        return $this->state === EntityState::MANAGED;
    }

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->state === EntityState::NEW;
    }
}
