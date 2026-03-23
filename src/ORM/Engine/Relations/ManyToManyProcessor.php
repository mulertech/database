<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use Error;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;

/**
 * Processor for ManyToMany relations.
 *
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
    ) {
    }

    /**
     * Process ManyToMany relations for an entity.
     *
     * @throws \ReflectionException
     */
    public function process(object $entity): void
    {
        $entityName = $entity::class;
        $manyToManyList = $this->getManyToManyMapping($entityName);

        if (false === $manyToManyList) {
            return;
        }

        $entityId = spl_object_id($entity);

        foreach ($manyToManyList as $property => $manyToMany) {
            $this->processProperty($entity, $property, $manyToMany, $entityId);
        }
    }

    /**
     * Process a specific ManyToMany property.
     *
     * @throws \ReflectionException
     */
    private function processProperty(
        object $entity,
        string $property,
        MtManyToMany $manyToMany,
        int $entityId,
    ): void {
        // Create a unique key for this entity+property combination
        $relationKey = $entityId.'_'.$property;

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

        if (null === $entities) {
            return;
        }

        if ($entities instanceof DatabaseCollection) {
            $this->processDatabaseCollection($entity, $entities, $manyToMany);
        }
    }

    /**
     * Check if property has a valid ManyToMany relation and can be accessed.
     *
     * @throws \ReflectionException
     */
    private function hasValidProperty(object $entity, string $property): bool
    {
        $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entity::class);

        return null !== $metadata->getGetter($property);
    }

    /**
     * Process DatabaseCollection changes.
     *
     * @template TKey of int|string
     * @template TValue of object
     *
     * @param DatabaseCollection<TKey, TValue> $collection
     */
    private function processDatabaseCollection(
        object $entity,
        DatabaseCollection $collection,
        MtManyToMany $manyToMany,
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
     * Add operation to queue.
     */
    private function addOperation(
        object $entity,
        object $relatedEntity,
        MtManyToMany $manyToMany,
        string $action,
    ): void {
        $this->operations[] = [
            'entity' => $entity,
            'related' => $relatedEntity,
            'manyToMany' => $manyToMany,
            'action' => $action,
        ];
    }

    /**
     * Get ManyToMany mapping for entity class.
     *
     * @param class-string $entityName
     *
     * @return array<string, MtManyToMany>|false
     *
     * @throws \ReflectionException
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
     * Get all queued operations.
     *
     * @return array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function clear(): void
    {
        $this->operations = [];
        $this->processedRelations = [];
        $this->mappingCache = [];
    }

    /**
     * Get property value using getter with error handling for uninitialized properties.
     *
     * @throws \ReflectionException
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        try {
            $metadataRegistry = $this->entityManager->getMetadataRegistry();
            $metadata = $metadataRegistry->getEntityMetadata($entity::class);
            $getter = $metadata->getRequiredGetter($property);

            return $entity->$getter();
        } catch (\Error $e) {
            // Handle uninitialized property errors in PHP 7.4+
            if (str_contains($e->getMessage(), 'uninitialized')) {
                return null;
            }
            throw $e;
        }
    }

    public function startFlushCycle(): void
    {
        $this->processedRelations = [];
        $this->mappingCache = [];
    }
}
