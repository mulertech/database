<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * Représente un ensemble de changements pour une entité
 * @package MulerTech\Database\ORM
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
     * @return array<string>
     */
    public function getChangedFields(): array
    {
        return array_keys($this->changes);
    }

    /**
     * @return int
     */
    public function getChangeCount(): int
    {
        return count($this->changes);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNewValues(): array
    {
        $values = [];
        foreach ($this->changes as $field => $change) {
            $values[$field] = $change->newValue;
        }
        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOldValues(): array
    {
        $values = [];
        foreach ($this->changes as $field => $change) {
            $values[$field] = $change->oldValue;
        }
        return $values;
    }

    /**
     * @param array<string> $fields
     * @return ChangeSet
     */
    public function filterByFields(array $fields): self
    {
        $filteredChanges = array_intersect_key($this->changes, array_flip($fields));
        return new self($this->entityClass, $filteredChanges);
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

    /**
     * @return array{entityClass: class-string, changeCount: int, fields: array<string>}
     */
    public function getSummary(): array
    {
        return [
            'entityClass' => $this->entityClass,
            'changeCount' => $this->getChangeCount(),
            'fields' => $this->getChangedFields(),
        ];
    }

    /**
     * @return array<string, array{property: string, old: mixed, new: mixed, type: string}>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->changes as $field => $change) {
            $result[$field] = $change->toArray();
        }
        return $result;
    }
}
