<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use InvalidArgumentException;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\State\EntityLifecycleState;
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
    /**
     * @var array<class-string, array<int|string, WeakReference<object>>>
     */
    private array $entities = [];

    /**
     * @var WeakMap<object, EntityState>
     */
    private WeakMap $metadata;

    /**
     * @var WeakMap<object, int|string|null>
     */
    private WeakMap $entityIds;

    public function __construct(private readonly MetadataRegistry $metadataRegistry)
    {
        $this->metadata = new WeakMap();
        $this->entityIds = new WeakMap();
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
            unset($this->entities[$entityClass][$id]);
            return null;
        }

        return $entity;
    }

    /**
     * @param object $entity
     * @param int|string|null $id
     * @param EntityState|null $entityState
     * @return void
     * @throws ReflectionException
     */
    public function add(object $entity, int|string|null $id = null, ?EntityState $entityState = null): void
    {
        $entityClass = $entity::class;

        // If ID is explicitly provided, use it; otherwise extract from entity
        if ($id === null) {
            $id = $this->extractEntityId($entity);
        }

        if ($id !== null) {
            $this->entities[$entityClass] ??= [];
            $this->entities[$entityClass][$id] = $this->createWeakReference($entity);
        }

        // Store the ID mapping for later removal
        $this->entityIds[$entity] = $id;

        // If EntityState is explicitly provided, use it; otherwise create automatically
        if ($entityState !== null) {
            $this->metadata[$entity] = $entityState;
            return;
        }

        $this->storeMetadata($entity, $id);
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function remove(object $entity): void
    {
        $entityClass = $entity::class;

        // Try to get the stored ID first, fallback to extraction
        $id = $this->entityIds[$entity] ?? $this->extractEntityId($entity);

        if ($id !== null && isset($this->entities[$entityClass][$id])) {
            unset($this->entities[$entityClass][$id]);
        }

        unset($this->metadata[$entity], $this->entityIds[$entity]);
    }

    /**
     * @param class-string|null $entityClass
     * @return void
     */
    public function clear(?string $entityClass = null): void
    {
        if ($entityClass !== null) {
            unset($this->entities[$entityClass]);
            return;
        }

        $this->entities = [];
        $this->metadata = new WeakMap();
        $this->entityIds = new WeakMap();
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

                continue;
            }
            unset($this->entities[$entityClass][$id]);
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
     * @param EntityLifecycleState $state
     * @return array<object>
     */
    public function getEntitiesByState(EntityLifecycleState $state): array
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
     * @return EntityState|null
     */
    public function getMetadata(object $entity): ?EntityState
    {
        return $this->metadata[$entity] ?? null;
    }

    /**
     * @param object $entity
     * @param EntityState $newMetadata
     * @return void
     */
    public function updateMetadata(object $entity, EntityState $newMetadata): void
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
     * @return EntityLifecycleState|null
     */
    public function getEntityState(object $entity): ?EntityLifecycleState
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
            if (empty($this->entities[$entityClass])) {
                unset($this->entities[$entityClass]);
            }
        }
    }

    /**
     * @param object $entity
     * @param int|string|null $id
     * @return void
     * @throws ReflectionException
     */
    private function storeMetadata(object $entity, int|string|null $id): void
    {
        $entityClass = $entity::class;
        $state = $id === null ? EntityLifecycleState::NEW : EntityLifecycleState::MANAGED;
        $originalData = $this->extractEntityData($entity);

        $metadata = new EntityState(
            $entityClass,
            $state,
            $originalData,
            new DateTimeImmutable()
        );

        $this->metadata[$entity] = $metadata;
    }

    /**
     * @param object $entity
     * @return int|string|null
     * @throws ReflectionException
     */
    private function extractEntityId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        $metadata = $this->metadataRegistry->getEntityMetadata($entity::class);

        // Check common ID properties using metadata
        foreach (['id', 'uuid', 'identifier'] as $property) {
            $getter = $metadata->getGetter($property);
            if ($getter !== null && method_exists($entity, $getter)) {
                $value = $entity->$getter();
                if (is_int($value) || is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private function extractEntityData(object $entity): array
    {
        $entityClass = $entity::class;
        $data = [];

        $metadata = $this->metadataRegistry->getEntityMetadata($entityClass);
        $propertyGetterMapping = $metadata->getPropertyGetterMapping();

        foreach ($propertyGetterMapping as $propertyName => $getter) {
            if (method_exists($entity, $getter)) {
                $data[$propertyName] = $entity->$getter();
            }
        }

        return $data;
    }

    /**
     * @param object $entity
     * @return WeakReference<object>
     */
    private function createWeakReference(object $entity): WeakReference
    {
        return WeakReference::create($entity);
    }
}
