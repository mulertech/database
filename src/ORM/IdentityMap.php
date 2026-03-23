<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\State\EntityLifecycleState;

/**
 * Class IdentityMap.
 *
 * @author Sébastien Muler
 */
final class IdentityMap
{
    /**
     * @var array<class-string, array<int|string, \WeakReference<object>>>
     */
    private array $entities = [];

    /**
     * @var \WeakMap<object, EntityState>
     */
    private \WeakMap $metadata;

    /**
     * @var \WeakMap<object, int|string|null>
     */
    private \WeakMap $entityIds;

    public function __construct(private readonly MetadataRegistry $metadataRegistry)
    {
        $this->metadata = new \WeakMap();
        $this->entityIds = new \WeakMap();
    }

    /**
     * @param class-string $entityClass
     */
    public function contains(string $entityClass, int|string $id): bool
    {
        if (!isset($this->entities[$entityClass][$id])) {
            return false;
        }

        $entity = $this->entities[$entityClass][$id]->get();
        if (null === $entity) {
            unset($this->entities[$entityClass][$id]);

            return false;
        }

        return true;
    }

    /**
     * @param class-string $entityClass
     */
    public function get(string $entityClass, int|string $id): ?object
    {
        if (!isset($this->entities[$entityClass][$id])) {
            return null;
        }

        $entity = $this->entities[$entityClass][$id]->get();
        if (null === $entity) {
            unset($this->entities[$entityClass][$id]);

            return null;
        }

        return $entity;
    }

    /**
     * @throws \ReflectionException
     */
    public function add(object $entity, int|string|null $id = null, ?EntityState $entityState = null): void
    {
        $entityClass = $entity::class;

        // If ID is explicitly provided, use it; otherwise extract from entity
        if (null === $id) {
            $id = $this->extractEntityId($entity);
        }

        if (null !== $id) {
            $this->entities[$entityClass] ??= [];
            $this->entities[$entityClass][$id] = $this->createWeakReference($entity);
        }

        // Store the ID mapping for later removal
        $this->entityIds[$entity] = $id;

        // If EntityState is explicitly provided, use it; otherwise create automatically
        if (null !== $entityState) {
            $this->metadata[$entity] = $entityState;

            return;
        }

        $this->storeMetadata($entity, $id);
    }

    /**
     * @throws \ReflectionException
     */
    public function remove(object $entity): void
    {
        $entityClass = $entity::class;

        // Try to get the stored ID first, fallback to extraction
        $id = $this->entityIds[$entity] ?? $this->extractEntityId($entity);

        if (null !== $id && isset($this->entities[$entityClass][$id])) {
            unset($this->entities[$entityClass][$id]);
        }

        unset($this->metadata[$entity], $this->entityIds[$entity]);
    }

    /**
     * @param class-string|null $entityClass
     */
    public function clear(?string $entityClass = null): void
    {
        if (null !== $entityClass) {
            unset($this->entities[$entityClass]);

            return;
        }

        $this->entities = [];
        $this->metadata = new \WeakMap();
        $this->entityIds = new \WeakMap();
    }

    /**
     * @param class-string $entityClass
     *
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
            if (null !== $entity) {
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

    public function getMetadata(object $entity): ?EntityState
    {
        return $this->metadata[$entity] ?? null;
    }

    public function updateMetadata(object $entity, EntityState $newMetadata): void
    {
        if (!isset($this->metadata[$entity])) {
            throw new \InvalidArgumentException('Entity is not managed by this IdentityMap');
        }

        $this->metadata[$entity] = $newMetadata;
    }

    public function isManaged(object $entity): bool
    {
        $metadata = $this->getMetadata($entity);

        return null !== $metadata && $metadata->isManaged();
    }

    public function getEntityState(object $entity): ?EntityLifecycleState
    {
        return $this->getMetadata($entity)?->state;
    }

    public function cleanup(): void
    {
        foreach ($this->entities as $entityClass => $classEntities) {
            foreach ($classEntities as $id => $weakRef) {
                if (null === $weakRef->get()) {
                    unset($this->entities[$entityClass][$id]);
                }
            }
            if (empty($this->entities[$entityClass])) {
                unset($this->entities[$entityClass]);
            }
        }
    }

    /**
     * @throws \ReflectionException
     */
    private function storeMetadata(object $entity, int|string|null $id): void
    {
        $entityClass = $entity::class;
        $state = null === $id ? EntityLifecycleState::NEW : EntityLifecycleState::MANAGED;
        $originalData = $this->extractEntityData($entity);

        $metadata = new EntityState(
            $entityClass,
            $state,
            $originalData,
            new \DateTimeImmutable()
        );

        $this->metadata[$entity] = $metadata;
    }

    /**
     * @throws \ReflectionException
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
            if (null !== $getter && method_exists($entity, $getter)) {
                $value = $entity->$getter();
                if (is_int($value) || is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \ReflectionException
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
     * @return \WeakReference<object>
     */
    private function createWeakReference(object $entity): \WeakReference
    {
        return \WeakReference::create($entity);
    }
}
