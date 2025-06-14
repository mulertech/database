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
final class EntityMetadata
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
        public readonly string $className,
        public readonly int|string $identifier,
        public readonly EntityState $state,
        public readonly array $originalData,
        public readonly DateTimeImmutable $loadedAt,
        public readonly ?DateTimeImmutable $lastModified = null
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

    /**
     * @return bool
     */
    public function isRemoved(): bool
    {
        return $this->state === EntityState::REMOVED;
    }

    /**
     * @return bool
     */
    public function isDetached(): bool
    {
        return $this->state === EntityState::DETACHED;
    }

    /**
     * Create a new instance with updated original data
     *
     * @param array<string, mixed> $originalData
     * @return self
     */
    public function withOriginalData(array $originalData): self
    {
        return new self(
            $this->className,
            $this->identifier,
            $this->state,
            $originalData,
            $this->loadedAt,
            $this->lastModified
        );
    }
}
