<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use MulerTech\Database\ORM\State\EntityState;
use ReflectionClass;
use ReflectionException;
use WeakMap;
use WeakReference;

/**
 * Class IdentityMap
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class IdentityMap
{
    /** @var array<class-string, array<int|string, WeakReference<object>>> */
    private array $entities = [];

    /** @var WeakMap<object, EntityMetadata> */
    private WeakMap $metadata;

    public function __construct()
    {
        $this->metadata = new WeakMap();
    }

    /**
     * @param class-string $entityClass
     * @param int|string $id
     * @return bool
     */
    public function contains(string $entityClass, int|string $id): bool
    {
        if (!isset($this->entities[$entityClass][$id])) {
            return false;
        }

        $entity = $this->entities[$entityClass][$id]->get();

        if ($entity === null) {
            // Cleanup dead reference
            unset($this->entities[$entityClass][$id]);
            return false;
        }

        return true;
    }

    /**
     * @param class-string $entityClass
     * @param int|string $id
     * @return object|null
     */
    public function get(string $entityClass, int|string $id): ?object
    {
        if (!isset($this->entities[$entityClass][$id])) {
            return null;
        }

        $entity = $this->entities[$entityClass][$id]->get();

        if ($entity === null) {
            // Cleanup dead reference
            unset($this->entities[$entityClass][$id]);
            return null;
        }

        return $entity;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function add(object $entity): void
    {
        $entityClass = $entity::class;
        $id = $this->extractEntityId($entity);

        if ($id === null) {
            // For entities without ID, we still store metadata but can't track in map
            $this->storeMetadata($entity, $id);
            return;
        }

        // Initialize class array if needed
        $this->entities[$entityClass] ??= [];

        // Store weak reference - inject WeakReference factory if needed
        $this->entities[$entityClass][$id] = $this->createWeakReference($entity);

        // Store metadata
        $this->storeMetadata($entity, $id);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $entityClass = $entity::class;
        $id = $this->extractEntityId($entity);

        if ($id !== null && isset($this->entities[$entityClass][$id])) {
            unset($this->entities[$entityClass][$id]);
        }

        unset($this->metadata[$entity]);
    }

    /**
     * @param class-string|null $entityClass
     * @return void
     */
    public function clear(?string $entityClass = null): void
    {
        if ($entityClass === null) {
            $this->entities = [];
            $this->metadata = new WeakMap();
        } else {
            unset($this->entities[$entityClass]);
            // Note: We can't selectively clear WeakMap, but GC will handle it
        }
    }

    /**
     * @param class-string $entityClass
     * @return array<object>
     */
    public function getByClass(string $entityClass): array
    {
        if (!isset($this->entities[$entityClass])) {
            return [];
        }

        $entities = [];
        foreach ($this->entities[$entityClass] as $id => $weakRef) {
            $entity = $weakRef->get();
            if ($entity !== null) {
                $entities[] = $entity;
            } else {
                // Cleanup dead reference
                unset($this->entities[$entityClass][$id]);
            }
        }

        return $entities;
    }

    /**
     * @return array<object>
     */
    public function getAllEntities(): array
    {
        $allEntities = [];

        array_walk(
            $this->entities,
            function ($references, $entityClass) use (&$allEntities) {
                array_push($allEntities, ...$this->getByClass($entityClass));
            }
        );

        return $allEntities;
    }

    /**
     * @param EntityState $state
     * @return array<object>
     */
    public function getEntitiesByState(EntityState $state): array
    {
        $entities = [];

        foreach ($this->metadata as $entity => $metadata) {
            if ($metadata->state === $state) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * @param object $entity
     * @return EntityMetadata|null
     */
    public function getMetadata(object $entity): ?EntityMetadata
    {
        return $this->metadata[$entity] ?? null;
    }

    /**
     * @param object $entity
     * @param EntityMetadata $newMetadata
     * @return void
     */
    public function updateMetadata(object $entity, EntityMetadata $newMetadata): void
    {
        if (!isset($this->metadata[$entity])) {
            throw new InvalidArgumentException('Entity is not managed by this IdentityMap');
        }

        $this->metadata[$entity] = $newMetadata;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        $metadata = $this->getMetadata($entity);
        return $metadata !== null && $metadata->isManaged();
    }

    /**
     * @param object $entity
     * @return EntityState|null
     */
    public function getEntityState(object $entity): ?EntityState
    {
        return $this->getMetadata($entity)?->state;
    }

    /**
     * @return void
     */
    public function cleanup(): void
    {
        foreach ($this->entities as $entityClass => $classEntities) {
            foreach ($classEntities as $id => $weakRef) {
                if ($weakRef->get() === null) {
                    unset($this->entities[$entityClass][$id]);
                }
            }

            // Remove empty class arrays
            if (empty($this->entities[$entityClass])) {
                unset($this->entities[$entityClass]);
            }
        }
    }

    /**
     * @param object $entity
     * @param int|string|null $id
     * @return void
     */
    private function storeMetadata(object $entity, int|string|null $id): void
    {
        $entityClass = $entity::class;

        // Determine initial state
        $state = $id === null ? EntityState::NEW : EntityState::MANAGED;

        // Extract current data for original state tracking
        $originalData = $this->extractEntityData($entity);

        $metadata = new EntityMetadata(
            className: $entityClass,
            identifier: $id ?? '',
            state: $state,
            originalData: $originalData,
            loadedAt: new DateTimeImmutable()
        );

        $this->metadata[$entity] = $metadata;
    }

    /**
     * @param object $entity
     * @return int|string|null
     */
    private function extractEntityId(object $entity): int|string|null
    {
        // Try common getter methods first
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        // Try direct property access
        $commonIdProperties = ['id', 'uuid', 'identifier'];

        foreach ($commonIdProperties as $property) {
            if (property_exists($entity, $property)) {
                try {
                    $reflection = new ReflectionClass($entity);
                    $prop = $reflection->getProperty($property);
                    $value = $prop->getValue($entity);
                    if ($value !== null && (is_int($value) || is_string($value))) {
                        return $value;
                    }
                } catch (ReflectionException) {
                    // Continue to next property
                }
            }
        }

        return null;
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    private function extractEntityData(object $entity): array
    {
        $reflection = new ReflectionClass($entity);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();

            // Try getter method first
            $getterMethod = 'get' . ucfirst($propertyName);
            if (method_exists($entity, $getterMethod)) {
                try {
                    $data[$propertyName] = $entity->$getterMethod();
                    continue;
                } catch (Error) {
                    // Getter failed, try direct property access
                }
            }

            try {
                $data[$propertyName] = $property->getValue($entity);
            } catch (Error) {
                // Property uninitialized, store as null
                $data[$propertyName] = null;
            }
        }

        return $data;
    }

    /**
     * Create a weak reference to an entity
     * This method can be overridden for testing or custom weak reference handling
     *
     * @param object $entity
     * @return WeakReference<object>
     */
    private function createWeakReference(object $entity): WeakReference
    {
        return WeakReference::create($entity);
    }
}
