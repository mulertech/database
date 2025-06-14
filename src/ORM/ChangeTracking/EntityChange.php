<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ChangeTracking;

/**
 * Represents changes for a single entity
 * @package MulerTech\Database\ORM\ChangeTracking
 * @author Sébastien Muler
 */
final class EntityChange
{
    /**
     * @param object $entity
     * @param array<string, \MulerTech\Database\ORM\PropertyChange> $changes
     */
    public function __construct(
        private readonly object $entity,
        private readonly array $changes
    ) {
    }

    /**
     * @return object
     */
    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return array<string, \MulerTech\Database\ORM\PropertyChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @return array<string>
     */
    public function getChangedProperties(): array
    {
        return array_keys($this->changes);
    }

    /**
     * @param string $property
     * @return bool
     */
    public function hasChange(string $property): bool
    {
        return isset($this->changes[$property]);
    }

    /**
     * @param string $property
     * @return \MulerTech\Database\ORM\PropertyChange|null
     */
    public function getChange(string $property): ?\MulerTech\Database\ORM\PropertyChange
    {
        return $this->changes[$property] ?? null;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->changes);
    }
}
