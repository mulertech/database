<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * Class ChangeSet
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class ChangeSet
{
    /**
     * @param class-string $entityClass
     * @param array<string, PropertyChange> $changes
     */
    public function __construct(
        public string $entityClass,
        public array $changes
    ) {
    }

    /**
     * @return array<string, PropertyChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->changes);
    }

    /**
     * @param string $field
     * @return bool
     */
    public function hasChangedField(string $field): bool
    {
        return isset($this->changes[$field]);
    }

    /**
     * @param string $field
     * @return PropertyChange|null
     */
    public function getFieldChange(string $field): ?PropertyChange
    {
        return $this->changes[$field] ?? null;
    }

    /**
     * @return int
     */
    public function getChangeCount(): int
    {
        return count($this->changes);
    }

    /**
     * @param callable(PropertyChange): bool $callback
     * @return ChangeSet
     */
    public function filter(callable $callback): self
    {
        $filteredChanges = array_filter($this->changes, $callback);
        return new self($this->entityClass, $filteredChanges);
    }
}
