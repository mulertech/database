<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * Class ChangeSet.
 *
 * @author Sébastien Muler
 */
final readonly class ChangeSet
{
    /**
     * @param class-string                  $entityClass
     * @param array<string, PropertyChange> $changes
     */
    public function __construct(
        public string $entityClass,
        public array $changes,
    ) {
    }

    /**
     * @return array<string, PropertyChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function isEmpty(): bool
    {
        return empty($this->changes);
    }

    public function getFieldChange(string $field): ?PropertyChange
    {
        return $this->changes[$field] ?? null;
    }

    /**
     * @param callable(PropertyChange): bool $callback
     */
    public function filter(callable $callback): self
    {
        $filteredChanges = array_filter($this->changes, $callback);

        return new self($this->entityClass, $filteredChanges);
    }
}
