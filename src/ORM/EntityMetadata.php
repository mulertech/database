<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\State\EntityState;

/**
 * Métadonnées immutables d'une entité gérée par l'ORM
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
     * @param DateTimeImmutable|null $lastModifiedAt
     */
    public function __construct(
        public string $className,
        public int|string $identifier,
        public EntityState $state,
        public array $originalData,
        public DateTimeImmutable $loadedAt,
        public ?DateTimeImmutable $lastModifiedAt = null
    ) {
    }

    /**
     * @param EntityState $newState
     * @return EntityMetadata
     */
    public function withState(EntityState $newState): self
    {
        if (!$this->state->canTransitionTo($newState)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid state transition from %s to %s for entity %s',
                    $this->state->value,
                    $newState->value,
                    $this->className
                )
            );
        }

        return new self(
            $this->className,
            $this->identifier,
            $newState,
            $this->originalData,
            $this->loadedAt,
            new DateTimeImmutable()
        );
    }

    /**
     * @param array<string, mixed> $newData
     * @return EntityMetadata
     */
    public function withOriginalData(array $newData): self
    {
        return new self(
            $this->className,
            $this->identifier,
            $this->state,
            $newData,
            $this->loadedAt,
            $this->lastModifiedAt
        );
    }

    /**
     * @param int|string $newIdentifier
     * @return EntityMetadata
     */
    public function withIdentifier(int|string $newIdentifier): self
    {
        return new self(
            $this->className,
            $newIdentifier,
            $this->state,
            $this->originalData,
            $this->loadedAt,
            new DateTimeImmutable()
        );
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
    public function isManaged(): bool
    {
        return $this->state === EntityState::MANAGED;
    }

    /**
     * @return bool
     */
    public function isDetached(): bool
    {
        return $this->state === EntityState::DETACHED;
    }

    /**
     * @return bool
     */
    public function isRemoved(): bool
    {
        return $this->state === EntityState::REMOVED;
    }

    /**
     * @return int
     */
    public function getAge(): int
    {
        $now = new DateTimeImmutable();
        return $now->getTimestamp() - $this->loadedAt->getTimestamp();
    }

    /**
     * @return int|null
     */
    public function getTimeSinceLastModification(): ?int
    {
        if ($this->lastModifiedAt === null) {
            return null;
        }

        $now = new DateTimeImmutable();
        return $now->getTimestamp() - $this->lastModifiedAt->getTimestamp();
    }

    /**
     * @param string $property
     * @return mixed
     */
    public function getOriginalValue(string $property): mixed
    {
        return $this->originalData[$property] ?? null;
    }

    /**
     * @param string $property
     * @return bool
     */
    public function hasOriginalValue(string $property): bool
    {
        return array_key_exists($property, $this->originalData);
    }

    /**
     * @return array{className: class-string, identifier: int|string, state: string, age: int, propertyCount: int}
     */
    public function getSummary(): array
    {
        return [
            'className' => $this->className,
            'identifier' => $this->identifier,
            'state' => $this->state->value,
            'age' => $this->getAge(),
            'propertyCount' => count($this->originalData),
        ];
    }
}
