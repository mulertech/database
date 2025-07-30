<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Processor for ManyToMany relations
 */
class ManyToManyProcessor
{
    /**
     * @var array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    private array $operations = [];

    /**
     * @var array<string, bool> Track processed relations to avoid duplicates
     */
    private array $processedRelations = [];

    /**
     * @var array<class-string, array<string, mixed>|false> Cache for ManyToMany mappings
     */
    private array $mappingCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager
    ) {
    }

    /**
     * Process ManyToMany relations for an entity
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @throws ReflectionException
     */
    public function process(object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = $entity::class;
        $manyToManyList = $this->getManyToManyMapping($entityName);

        if ($manyToManyList === false) {
            return;
        }

        $entityId = spl_object_id($entity);

        foreach ($manyToManyList as $property => $manyToMany) {
            $this->processProperty($entity, $entityReflection, $property, $manyToMany, $entityId);
        }
    }

    /**
     * Process a specific ManyToMany property
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $property
     * @param mixed $manyToMany
     * @param int $entityId
     * @throws ReflectionException
     */
    private function processProperty(
        object $entity,
        ReflectionClass $entityReflection,
        string $property,
        mixed $manyToMany,
        int $entityId
    ): void {
        // Create a unique key for this entity+property combination
        $relationKey = $entityId . '_' . $property;

        // Skip if this specific relation was already processed
        if (isset($this->processedRelations[$relationKey])) {
            return;
        }

        // Mark this relation as processed
        $this->processedRelations[$relationKey] = true;

        if (!$this->hasValidProperty($entityReflection, $entity, $property)) {
            return;
        }

        $entities = $entityReflection->getProperty($property)->getValue($entity);

        if ($entities instanceof DatabaseCollection) {
            $this->processDatabaseCollection($entity, $entities, $manyToMany);
        } elseif ($this->shouldProcessNewCollection($entities, $manyToMany, $entity)) {
            if ($entities instanceof Collection && $manyToMany instanceof MtManyToMany) {
                $this->processNewCollection($entity, $entities, $manyToMany);
            }
        }
    }

    /**
     * Check if property is valid and initialized
     * @template T of object
     * @param ReflectionClass<T> $entityReflection
     * @param object $entity
     * @param string $property
     * @return bool
     */
    private function hasValidProperty(ReflectionClass $entityReflection, object $entity, string $property): bool
    {
        if (!$entityReflection->hasProperty($property)) {
            return false;
        }

        return $entityReflection->getProperty($property)->isInitialized($entity);
    }

    /**
     * Check if new collection should be processed
     */
    private function shouldProcessNewCollection(mixed $entities, mixed $manyToMany, object $entity): bool
    {
        return $entities instanceof Collection
            && $manyToMany instanceof MtManyToMany
            && $entities->count() > 0
            && $this->stateManager->isScheduledForInsertion($entity);
    }

    /**
     * Process DatabaseCollection changes
     * @template TKey of int|string
     * @template TValue of object
     * @param object $entity
     * @param DatabaseCollection<TKey, TValue> $collection
     * @param mixed $manyToMany
     */
    private function processDatabaseCollection(
        object $entity,
        DatabaseCollection $collection,
        mixed $manyToMany
    ): void {
        if (!($manyToMany instanceof MtManyToMany) || !$collection->hasChanges()) {
            return;
        }

        // Process additions
        foreach ($collection->getAddedEntities() as $relatedEntity) {
            $this->addOperation($entity, $relatedEntity, $manyToMany, 'insert');
        }

        // Process deletions
        foreach ($collection->getRemovedEntities() as $relatedEntity) {
            $this->addOperation($entity, $relatedEntity, $manyToMany, 'delete');
        }
    }

    /**
     * Process new Collection relations
     * @template TKey of int|string
     * @template TValue of object
     * @param object $entity
     * @param Collection<TKey, TValue> $collection
     * @param MtManyToMany $manyToMany
     */
    private function processNewCollection(
        object $entity,
        Collection $collection,
        MtManyToMany $manyToMany
    ): void {
        foreach ($collection->items() as $relatedEntity) {
            $this->addOperation($entity, $relatedEntity, $manyToMany, 'insert');
        }
    }

    /**
     * Add operation to queue
     */
    private function addOperation(
        object $entity,
        object $relatedEntity,
        MtManyToMany $manyToMany,
        string $action
    ): void {
        $this->operations[] = [
            'entity' => $entity,
            'related' => $relatedEntity,
            'manyToMany' => $manyToMany,
            'action' => $action,
        ];
    }

    /**
     * Get ManyToMany mapping for entity class
     * @param class-string $entityName
     * @return array<string, mixed>|false
     * @throws ReflectionException
     */
    private function getManyToManyMapping(string $entityName): array|false
    {
        if (!isset($this->mappingCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getManyToMany($entityName);
            $this->mappingCache[$entityName] = $mapping;
        }

        return $this->mappingCache[$entityName];
    }

    /**
     * Get all queued operations
     * @return array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Clear all operations and caches
     */
    public function clear(): void
    {
        $this->operations = [];
        $this->processedRelations = [];
        $this->mappingCache = [];
    }

    /**
     * Start new flush cycle
     */
    public function startFlushCycle(): void
    {
        $this->processedRelations = [];
        $this->mappingCache = [];
    }
}
