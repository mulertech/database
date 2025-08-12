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
     * @var array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    private array $operations = [];

    /**
     * @var array<string, bool> Track processed relations to avoid duplicates
     */
    private array $processedRelations = [];

    /**
     * @var array<class-string, array<string, MtManyToMany>|false> Cache for ManyToMany mappings
     */
    private array $mappingCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager
    ) {
    }

    /**
     * Process ManyToMany relations for an entity
     * @param object $entity
     * @throws ReflectionException
     */
    public function process(object $entity): void
    {
        $entityName = $entity::class;
        $manyToManyList = $this->getManyToManyMapping($entityName);

        if ($manyToManyList === false) {
            return;
        }

        $entityId = spl_object_id($entity);

        foreach ($manyToManyList as $property => $manyToMany) {
            $this->processProperty($entity, $property, $manyToMany, $entityId);
        }
    }

    /**
     * Process a specific ManyToMany property
     * @param object $entity
     * @param string $property
     * @param MtManyToMany $manyToMany
     * @param int $entityId
     * @throws ReflectionException
     */
    private function processProperty(
        object $entity,
        string $property,
        MtManyToMany $manyToMany,
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

        if (!$this->hasValidProperty($entity, $property)) {
            return;
        }

        $entities = $this->getPropertyValue($entity, $property);

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
     * @param object $entity
     * @param string $property
     * @return bool
     * @throws ReflectionException
     */
    private function hasValidProperty(object $entity, string $property): bool
    {
        $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entity::class);
        $reflectionProperty = $metadata->getProperty($property);

        if ($reflectionProperty === null) {
            return false;
        }

        return $reflectionProperty->isInitialized($entity);
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
     * @param MtManyToMany $manyToMany
     */
    private function processDatabaseCollection(
        object $entity,
        DatabaseCollection $collection,
        MtManyToMany $manyToMany
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
     * @param object $entity
     * @param object $relatedEntity
     * @param MtManyToMany $manyToMany
     * @param string $action
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
     * @return array<string, MtManyToMany>|false
     * @throws ReflectionException
     */
    private function getManyToManyMapping(string $entityName): array|false
    {
        if (!isset($this->mappingCache[$entityName])) {
            $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName);
            $mapping = $metadata->getManyToManyRelations();
            $this->mappingCache[$entityName] = $mapping ?: false;
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
     * @return void
     */
    public function clear(): void
    {
        $this->operations = [];
        $this->processedRelations = [];
        $this->mappingCache = [];
    }

    /**
     * Get property value using getter
     * @param object $entity
     * @param string $property
     * @return mixed
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $metadataRegistry = $this->entityManager->getMetadataRegistry();
        $metadata = $metadataRegistry->getEntityMetadata($entity::class);
        $getter = $metadata->getRequiredGetter($property);
        return $entity->$getter();
    }

    /**
     * @return void
     */
    public function startFlushCycle(): void
    {
        $this->processedRelations = [];
        $this->mappingCache = [];
    }

}
