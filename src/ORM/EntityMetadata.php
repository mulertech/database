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
     * @return array{
     *     className: class-string,
     *     identifier: int|string,
     *     state: string,
     *     originalDataCount: int,
     *     loadedAt: string,
     *     lastModifiedAt: string|null,
     *     age: int
     * }
     */
    public function toArray(): array
    {
        return [
            'className' => $this->className,
            'identifier' => $this->identifier,
            'state' => $this->state->value,
            'originalDataCount' => count($this->originalData),
            'loadedAt' => $this->loadedAt->format('Y-m-d H:i:s'),
            'lastModifiedAt' => $this->lastModifiedAt?->format('Y-m-d H:i:s'),
            'age' => (new DateTimeImmutable())->getTimestamp() - $this->loadedAt->getTimestamp(),
        ];
    }
}
