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
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ManyToManyProcessor
{
    /**
     * @var array<int, array{entity: object, related: object, manyToMany: array<string, mixed>, action?: string}>
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
            if (!($manyToMany instanceof MtManyToMany)) {
                continue;
            }
            // Convert Mt object to array format for existing methods
            $manyToManyArray = $this->convertManyToManyToArray($manyToMany);
            $this->processProperty($entity, $entityReflection, $property, $manyToManyArray, $entityId);
        }
    }

    /**
     * Process a specific ManyToMany property
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $property
     * @param array<string, mixed> $manyToMany
     * @param int $entityId
     * @throws ReflectionException
     */
    private function processProperty(
        object $entity,
        ReflectionClass $entityReflection,
        string $property,
        array $manyToMany,
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
            return;
        }

        if ($entities instanceof Collection && $this->shouldProcessNewCollection($entities, $entity)) {
            $this->processNewCollection($entity, $entities, $manyToMany);
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
     * @param mixed $entities
     * @param object $entity
     * @return bool
     */
    private function shouldProcessNewCollection(mixed $entities, object $entity): bool
    {
        return $entities instanceof Collection
            && $entities->count() > 0
            && $this->stateManager->isScheduledForInsertion($entity);
    }

    /**
     * Process DatabaseCollection changes
     * @template TKey of int|string
     * @template TValue of object
     * @param object $entity
     * @param DatabaseCollection<TKey, TValue> $collection
     * @param array<string, mixed> $manyToMany
     */
    private function processDatabaseCollection(
        object $entity,
        DatabaseCollection $collection,
        array $manyToMany
    ): void {
        if (!$collection->hasChanges()) {
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
     * @param array<string, mixed> $manyToMany
     */
    private function processNewCollection(
        object $entity,
        Collection $collection,
        array $manyToMany
    ): void {
        foreach ($collection->items() as $relatedEntity) {
            $this->addOperation($entity, $relatedEntity, $manyToMany, 'insert');
        }
    }

    /**
     * Add operation to queue
     * @param object $entity
     * @param object $relatedEntity
     * @param array<string, mixed> $manyToMany
     * @param string $action
     */
    private function addOperation(
        object $entity,
        object $relatedEntity,
        array $manyToMany,
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
            $metadata = $this->entityManager->getMetadataCache()->getEntityMetadata($entityName);
            $mapping = $metadata->getRelationsByType('ManyToMany');
            $this->mappingCache[$entityName] = $mapping;
        }

        return $this->mappingCache[$entityName];
    }

    /**
     * Get all queued operations
     * @return array<int, array{entity: object, related: object, manyToMany: array<string, mixed>, action?: string}>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->operations = [];
        $this->processedRelations = [];
        $this->mappingCache = [];
    }

    /**
     * @return void
     */
    public function startFlushCycle(): void
    {
        $this->processedRelations = [];
        $this->mappingCache = [];
    }

    /**
     * Convert MtManyToMany object to array format expected by existing relation processing methods
     * @param MtManyToMany $relation
     * @return array<string, mixed>
     */
    private function convertManyToManyToArray(MtManyToMany $relation): array
    {
        return [
            'targetEntity' => $relation->targetEntity,
            'mappedBy' => $relation->mappedBy,
            'joinProperty' => $relation->joinProperty,
            'inverseJoinProperty' => $relation->inverseJoinProperty,
        ];
    }
}
