<?php

namespace MulerTech\Database\ORM;

use InvalidArgumentException;
use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtOneToMany;
use ReflectionException;

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
        private EntityManagerInterface $entityManager,
        private readonly MtManyToMany|MtOneToMany $relationalMapping,
        array $items = []
    ) {
        if ($relationalMapping->entity === null) {
            throw new InvalidArgumentException('The entity class name must be set in the relationalMapping.');
        }

        if ($relationalMapping->targetEntity === null) {
            throw new InvalidArgumentException(
                'The targetEntity class name must be set in the relationalMapping.'
            );
        }

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
     * @throws ReflectionException
     */
    public function persist(object $entity): void
    {
        if ($this->relationalMapping instanceof MtManyToMany) {
            $this->persistManyToManyRelations($entity);
        }
        if ($this->relationalMapping instanceof MtOneToMany) {
            $this->persistOneToManyRelations($entity);
        }

        // Delete the pivot entity for the removed entities
    }

    private function persistOneToManyRelations(object $entity): void
    {
        $mapedBy = $this->relationalMapping->mappedBy;
        if ($mapedBy === null) {
            throw new InvalidArgumentException(
                'The mappedBy property must be set in the relationalMapping.'
            );
        }

        foreach ($this->getAddedEntities() as $relatedEntity) {
            $setter = 'set' . ucfirst($mapedBy);
            if (is_a($entity, $this->relationalMapping->targetEntity)) {
                $relatedEntity->$setter($entity);
            }
            $this->entityManager->persist($relatedEntity);
        }
    }

    private function persistManyToManyRelations(object $entity): void
    {
        $mapedBy = $this->relationalMapping->mappedBy;
        if ($mapedBy === null) {
            throw new InvalidArgumentException(
                'The mappedBy property must be set in the relationalMapping.'
            );
        }

        foreach ($this->getAddedEntities() as $relatedEntity) {
            $pivotEntity = new $this->relationalMapping->mappedBy();
            foreach ($this->entityManager->getDbMapping()->getOneToMany($this->relationalMapping->mappedBy) as $property => $manyToOne) {
                $setter = 'set' . ucfirst($property);
                if (is_a($entity, $manyToOne->targetEntity)) {
                    $pivotEntity->$setter($entity);
                    continue;
                }

                if (is_a($relatedEntity, $manyToOne->targetEntity)) {
                    $pivotEntity->$setter($relatedEntity);
                }
            }
            $this->entityManager->persist($pivotEntity);
        }
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
