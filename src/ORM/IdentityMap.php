<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use MulerTech\Database\ORM\State\EntityState;
use ReflectionClass;
use WeakMap;
use WeakReference;

/**
 * Identity Map pour éviter les doublons d'entités en mémoire
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final class IdentityMap
{
    /** @var array<class-string, array<int|string, WeakReference<object>>> */
    private array $entities = [];

    /** @var WeakMap<object, EntityMetadata> */
    private WeakMap $metadata;

    /** @var array<string, array{methods: array<string>,properties: array<string>}> */
    private array $identifierMethodsCache = [];

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
        if (!$this->contains($entityClass, $id)) {
            return null;
        }

        return $this->entities[$entityClass][$id]->get();
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

        // Store weak reference
        $this->entities[$entityClass][$id] = WeakReference::create($entity);

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
            $this->identifierMethodsCache = [];
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

        foreach ($this->entities as $entityClass => $classEntities) {
            $allEntities = array_merge($allEntities, $this->getByClass($entityClass));
        }

        return $allEntities;
    }

    /**
     * @param EntityState $state
     * @return array<object>
     */
    public function getEntitiesByState(EntityState $state): array
    {
        $entities = [];

        foreach ($this->getAllEntities() as $entity) {
            $metadata = $this->getMetadata($entity);
            if ($metadata !== null && $metadata->state === $state) {
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
     * @param EntityMetadata $metadata
     * @return void
     */
    public function updateMetadata(object $entity, EntityMetadata $metadata): void
    {
        $this->metadata[$entity] = $metadata;
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
        $metadata = $this->getMetadata($entity);
        return $metadata?->state;
    }

    /**
     * @return array{entities: int, classes: int, managedEntities: int, newEntities: int, removedEntities: int, memory: int}
     */
    public function getStatistics(): array
    {
        $entityCount = 0;
        $classCount = count($this->entities);
        $managedCount = 0;
        $newCount = 0;
        $removedCount = 0;

        foreach ($this->entities as $classEntities) {
            $entityCount += count($classEntities);
        }

        foreach ($this->getAllEntities() as $entity) {
            $metadata = $this->getMetadata($entity);
            if ($metadata !== null) {
                match ($metadata->state) {
                    EntityState::MANAGED => $managedCount++,
                    EntityState::NEW => $newCount++,
                    EntityState::REMOVED => $removedCount++,
                    default => null,
                };
            }
        }

        return [
            'entities' => $entityCount,
            'classes' => $classCount,
            'managedEntities' => $managedCount,
            'newEntities' => $newCount,
            'removedEntities' => $removedCount,
            'memory' => memory_get_usage(true),
        ];
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
        $entityClass = $entity::class;

        // Use cached methods if available
        if (!isset($this->identifierMethodsCache[$entityClass])) {
            $this->cacheIdentifierMethods($entityClass);
        }

        $methods = $this->identifierMethodsCache[$entityClass];

        // Try ID methods
        foreach ($methods['methods'] as $method) {
            if (method_exists($entity, $method)) {
                $value = $entity->$method();
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // Try ID properties
        $entityReflection = new ReflectionClass($entity);
        $publicProperties = $entityReflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($methods['properties'] as $property) {
            // Check if property exists and is public
            if (property_exists($entity, $property) && in_array($property, $publicProperties)) {
                $value = $entity->$property;
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param class-string $entityClass
     * @return void
     */
    private function cacheIdentifierMethods(string $entityClass): void
    {
        // Common ID getter methods in order of preference
        $methods = ['getId', 'getIdentifier', 'getUuid', 'getPrimaryKey'];

        // Common ID properties in order of preference
        $properties = ['id', 'identifier', 'uuid', 'primaryKey'];

        $this->identifierMethodsCache[$entityClass] = [
            'methods' => $methods,
            'properties' => $properties,
        ];
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

            // Utiliser des getters explicites si disponibles
            $getterMethod = 'get' . ucfirst($propertyName);
            if (method_exists($entity, $getterMethod)) {
                $data[$propertyName] = $entity->$getterMethod();
                continue;
            }

            // Sinon utiliser la réflexion avec setAccessible
            try {
                $property->setAccessible(true);
                $data[$propertyName] = $property->getValue($entity);
            } catch (\Error|\Exception $e) {
                // Handle uninitialized or inaccessible properties
                $data[$propertyName] = null;
            }
        }

        return $data;
    }
}
