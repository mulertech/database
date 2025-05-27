<?php

namespace MulerTech\Database\ORM;

use MulerTech\Collections\Collection;

/**
 * Class DatabaseCollection
 * @package MulerTech\Database\ORM
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
    public array $initialItems = [];

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
     * Saves the initial state of the collection for future comparison
     * @return void
     */
    private function saveInitialState(): void
    {
        foreach ($this->items() as $entity) {
            $this->initialItems[spl_object_id($entity)] = $entity;
        }
    }
}
