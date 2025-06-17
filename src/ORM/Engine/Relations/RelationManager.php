<?php

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtManyToOne;
use MulerTech\Database\Mapping\MtOneToMany;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EntityRelationLoader;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Manager for relations between entities (OneToMany, ManyToMany, etc.)
 *
 * @package MulerTech\Database\ORM\Engine\Relations
 * @author Sébastien Muler
 */
class RelationManager
{
    /**
     * @var array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    private array $manyToManyInsertions = [];

    /**
     * @var EntityRelationLoader
     */
    private EntityRelationLoader $relationLoader;

    /**
     * @var array<int> Track processed entities during the entire flush cycle
     */
    private array $processedEntities = [];

    /**
     * @var array<string, bool> Track processed relations to avoid duplicates
     */
    private array $processedRelations = [];

    /**
     * @var array<class-string, array<string, mixed>|false> Cache for OneToMany mappings
     */
    private array $oneToManyCache = [];

    /**
     * @var array<class-string, array<string, mixed>|false> Cache for ManyToMany mappings
     */
    private array $manyToManyCache = [];

    /**
     * @var array<string, object|null>
     */
    private array $existingLinkCache = [];

    /**
     * @param EntityManagerInterface $entityManager
     * @param StateManagerInterface $stateManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager
    ) {
        $this->relationLoader = new EntityRelationLoader($this->entityManager);
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $entityData
     * @return void
     * @throws ReflectionException
     */
    public function loadEntityRelations(object $entity, array $entityData): void
    {
        $this->relationLoader->loadRelations($entity, $entityData);
    }

    /**
     * Start a new flush cycle - reset tracking
     * @return void
     */
    public function startFlushCycle(): void
    {
        $this->processedEntities = [];
        $this->processedRelations = [];
        $this->manyToManyInsertions = [];
        // Clear caches for new flush cycle
        $this->oneToManyCache = [];
        $this->manyToManyCache = [];
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function processRelationChanges(): void
    {
        // Collect all entities to process first to avoid duplicates
        $entitiesToProcess = [];

        // Add scheduled insertions
        $scheduledInsertions = $this->stateManager->getScheduledInsertions();
//        var_dump('DEBUG: Scheduled Insertions', $scheduledInsertions);
        foreach ($scheduledInsertions as $entity) {
            $entityId = spl_object_id($entity);
            $entitiesToProcess[$entityId] = $entity;
        }

        // Add managed entities that are not scheduled for deletion
        $managedEntities = $this->stateManager->getManagedEntities();
        foreach ($managedEntities as $entity) {
            if (!$this->stateManager->isScheduledForDeletion($entity)) {
                $entityId = spl_object_id($entity);
                // Only add if not already in the list (from insertions)
                if (!isset($entitiesToProcess[$entityId])) {
                    $entitiesToProcess[$entityId] = $entity;
                }
            }
        }

        // Now process each unique entity only once
        foreach ($entitiesToProcess as $entity) {
            $this->processEntityRelations($entity);
        }
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        // Process all relation changes first
        $this->processRelationChanges();

        // Then execute the ManyToMany relations
        if (!empty($this->manyToManyInsertions)) {
            $this->executeManyToManyRelations();
        }
    }

    /**
     * @return array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    public function getPendingManyToManyInsertions(): array
    {
        return $this->manyToManyInsertions;
    }

    /**
     * @param object $entity
     * @param string $relationProperty
     * @return bool
     * @throws ReflectionException
     */
    public function hasRelationChanges(object $entity, string $relationProperty): bool
    {
        $entityReflection = new ReflectionClass($entity);
        $relationValue = $entityReflection->getProperty($relationProperty)->getValue($entity);

        if ($relationValue instanceof DatabaseCollection) {
            return $relationValue->hasChanges();
        }

        return false;
    }

    /**
     * @param object $entity
     * @return array<string>
     * @throws ReflectionException
     */
    public function getChangedRelations(object $entity): array
    {
        $changedRelations = [];
        $entityName = $entity::class;
        $entityReflection = new ReflectionClass($entity);

        // Check OneToMany relations
        $oneToManyList = $this->entityManager->getDbMapping()->getOneToMany($entityName);
        if (is_array($oneToManyList)) {
            foreach ($oneToManyList as $property => $oneToMany) {
                if ($this->hasRelationChanges($entity, $property)) {
                    $changedRelations[] = $property;
                }
            }
        }

        // Check ManyToMany relations
        $manyToManyList = $this->entityManager->getDbMapping()->getManyToMany($entityName);
        if (is_array($manyToManyList)) {
            foreach ($manyToManyList as $property => $manyToMany) {
                if ($this->hasRelationChanges($entity, $property)) {
                    $changedRelations[] = $property;
                }
            }
        }

        return $changedRelations;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->manyToManyInsertions = [];
        $this->processedEntities = [];
        $this->processedRelations = [];
        $this->oneToManyCache = [];
        $this->manyToManyCache = [];
        $this->existingLinkCache = [];
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processEntityRelations(object $entity): void
    {
        $entityId = spl_object_id($entity);

        // Skip if already processed in this flush cycle
        if (in_array($entityId, $this->processedEntities, true)) {
            return;
        }

        // Mark as processed
        $this->processedEntities[] = $entityId;

        $entityName = $entity::class;

        // Check if entity has any relations to process (using cache)
        $oneToManyList = $this->getOneToManyMapping($entityName);
        $manyToManyList = $this->getManyToManyMapping($entityName);

        // Skip if no relations
        if ($oneToManyList === false && $manyToManyList === false) {
            return;
        }

        $entityReflection = new ReflectionClass($entity);

        if ($oneToManyList !== false) {
            $this->processOneToManyRelations($entity, $entityReflection);
        }

        if ($manyToManyList !== false) {
            $this->processManyToManyRelations($entity, $entityReflection);
        }
    }

    /**
     * @param object $entity
     * @param ReflectionClass<object> $entityReflection
     * @return void
     * @throws ReflectionException
     */
    private function processOneToManyRelations(object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = $entity::class;
        $oneToManyList = $this->getOneToManyMapping($entityName);

        if ($oneToManyList === false) {
            return;
        }

        foreach ($oneToManyList as $property => $oneToMany) {
            if (!$entityReflection->hasProperty($property)) {
                continue;
            }

            $reflectionProperty = $entityReflection->getProperty($property);
            if (!$reflectionProperty->isInitialized($entity)) {
                continue;
            }

            $entities = $reflectionProperty->getValue($entity);

            if (!$entities instanceof Collection) {
                continue;
            }

            foreach ($entities->items() as $relatedEntity) {
                if ($relatedEntity !== null && is_object($relatedEntity) && $this->getId($relatedEntity) === null) {
                    $this->stateManager->scheduleForInsertion($relatedEntity);
                    $this->stateManager->addInsertionDependency($relatedEntity, $entity);
                }
            }
        }
    }

    /**
     * @param object $entity
     * @param ReflectionClass<object> $entityReflection
     * @return void
     * @throws ReflectionException
     */
    private function processManyToManyRelations(object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = $entity::class;
        $manyToManyList = $this->getManyToManyMapping($entityName);

        if ($manyToManyList === false) {
            return;
        }

        $entityId = spl_object_id($entity);

        foreach ($manyToManyList as $property => $manyToMany) {
            // Create a unique key for this entity+property combination
            $relationKey = $entityId . '_' . $property;

            // Skip if this specific relation was already processed
            if (isset($this->processedRelations[$relationKey])) {
                continue;
            }

            // Mark this relation as processed
            $this->processedRelations[$relationKey] = true;

            if (!$entityReflection->hasProperty($property)) {
                continue;
            }

            $reflectionProperty = $entityReflection->getProperty($property);
            if (!$reflectionProperty->isInitialized($entity)) {
                continue;
            }

            $entities = $reflectionProperty->getValue($entity);

            if ($entities instanceof DatabaseCollection) {
                // Only process if there are actual changes
                if ($entities->hasChanges()) {
                    $this->processDatabaseCollectionChanges($entity, $entities, $manyToMany);
                }
            } elseif ($entities instanceof Collection && $entities->count() > 0) {
                // For new collections, only process if entity is being inserted
                if ($this->stateManager->isScheduledForInsertion($entity)) {
                    $this->processNewCollectionRelations($entity, $entities, $manyToMany);
                }
            }
        }
    }

    /**
     * @param object $entity
     * @param DatabaseCollection $collection
     * @param MtManyToMany $manyToMany
     * @return void
     */
    private function processDatabaseCollectionChanges(
        object $entity,
        DatabaseCollection $collection,
        MtManyToMany $manyToMany
    ): void {
        // Process additions
        foreach ($collection->getAddedEntities() as $relatedEntity) {
            if ($relatedEntity !== null) {
                $this->manyToManyInsertions[] = [
                    'entity' => $entity,
                    'related' => $relatedEntity,
                    'manyToMany' => $manyToMany,
                    'action' => 'insert'
                ];
            }
        }

        // Process deletions
        foreach ($collection->getRemovedEntities() as $relatedEntity) {
            if ($relatedEntity !== null) {
                $this->manyToManyInsertions[] = [
                    'entity' => $entity,
                    'related' => $relatedEntity,
                    'manyToMany' => $manyToMany,
                    'action' => 'delete'
                ];
            }
        }
    }

    /**
     * @param object $entity
     * @param Collection $collection
     * @param MtManyToMany $manyToMany
     * @return void
     */
    private function processNewCollectionRelations(
        object $entity,
        Collection $collection,
        MtManyToMany $manyToMany
    ): void {
        foreach ($collection->items() as $relatedEntity) {
            if ($relatedEntity !== null) {
                $this->manyToManyInsertions[] = [
                    'entity' => $entity,
                    'related' => $relatedEntity,
                    'manyToMany' => $manyToMany
                ];
            }
        }
    }

    /**
     * @param class-string $entityName
     * @return array<string, mixed>|false
     */
    private function getOneToManyMapping(string $entityName): array|false
    {
        if (!isset($this->oneToManyCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getOneToMany($entityName);
            $this->oneToManyCache[$entityName] = is_array($mapping) ? $mapping : false;
        }

        return $this->oneToManyCache[$entityName];
    }

    /**
     * @param class-string $entityName
     * @return array<string, mixed>|false
     */
    private function getManyToManyMapping(string $entityName): array|false
    {
        if (!isset($this->manyToManyCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getManyToMany($entityName);
            $this->manyToManyCache[$entityName] = is_array($mapping) ? $mapping : false;
        }

        return $this->manyToManyCache[$entityName];
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function executeManyToManyRelations(): void
    {
        // Implementation for executing many-to-many relations
        // This would create the link entities and schedule them for insertion
        // The implementation depends on your specific requirements
    }

    /**
     * @param object $entity
     * @return int|string|null
     */
    private function getId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }

    /**
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return object|null
     */
    private function findExistingLinkRelation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): ?object {
        // Implementation to find existing link relations
        // This would query the database or check the identity map
        return null;
    }

    /**
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return void
     */
    private function scheduleExistingLinkForDeletion(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): void {
        // Implementation to schedule existing link for deletion
    }

    /**
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return object
     */
    private function createLinkEntity(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): object {
        // Implementation to create link entity
        throw new RuntimeException('Not implemented yet');
    }
}
