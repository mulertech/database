<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Collections\Collection;

/**
 * Class DatabaseCollection
 *
 * Collection class for database-managed entities with change tracking.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 * @template TKey of array-key
 * @template TValue of object
 * @extends Collection<TKey, TValue>
 */
class DatabaseCollection extends Collection
{
    /**
     * @var array<int, object> The initial state of the collection
     */
    private array $initialItems = [];

    /**
     * @param array<TKey, TValue> $items
     */
    public function __construct(
        array $items = []
    ) {
        parent::__construct($items);
        $this->saveInitialState();
    }

    /**
     * Gets the entities that were added to the collection since initialization
     * @return array<int, object>
     */
    public function getAddedEntities(): array
    {
        return array_values(
            array_diff_key(
                array_combine(array_map('spl_object_id', $this->items()), $this->items()),
                array_combine(array_map('spl_object_id', $this->initialItems), $this->initialItems),
            )
        );
    }

    /**
     * Gets the entities that were removed from the collection since initialization
     * @return array<int, object>
     */
    public function getRemovedEntities(): array
    {
        return array_values(
            array_diff_key(
                array_combine(array_map('spl_object_id', $this->initialItems), $this->initialItems),
                array_combine(array_map('spl_object_id', $this->items()), $this->items()),
            )
        );
    }

    /**
     * Check if the collection has any changes
     * @return bool
     */
    public function hasChanges(): bool
    {
        return !empty($this->getAddedEntities()) || !empty($this->getRemovedEntities());
    }

    /**
     * Synchronize the initial state after loading from database
     * This should be called after the collection is populated with data from the database
     * @return void
     */
    public function synchronizeInitialState(): void
    {
        $this->saveInitialState();
    }

    /**
     * Saves the initial state of the collection for future comparison
     * @return void
     */
    private function saveInitialState(): void
    {
        $this->initialItems = [];
        foreach ($this->items() as $entity) {
            $this->initialItems[spl_object_id($entity)] = $entity;
        }
    }
}
