<?php

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\Engine\EntityState\EntityStateManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\EntityRelationLoader;
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
     * @var array<int> Track processed entities to avoid duplicates
     */
    private array $processedEntities = [];

    /**
     * @param EntityManagerInterface $entityManager
     * @param EntityStateManager $stateManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityStateManager $stateManager
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
     * @return void
     * @throws ReflectionException
     */
    public function processRelationChanges(): void
    {
        // Reset processed entities for this flush cycle
        $this->processedEntities = [];

        // Process insertions first - they might create new entities with relations
        $scheduledInsertions = $this->stateManager->getScheduledInsertions();
        foreach ($scheduledInsertions as $entity) {
            $this->processEntityRelations($entity);
        }

        // Then process managed entities that might have relation changes
        $managedEntities = $this->stateManager->getManagedEntities();
        foreach ($managedEntities as $entity) {
            if (!$this->stateManager->isScheduledForDeletion($entity)) {
                $this->processEntityRelations($entity);
            }
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
     * @return void
     */
    public function clear(): void
    {
        $this->manyToManyInsertions = [];
        $this->processedEntities = [];
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processEntityRelations(object $entity): void
    {
        $entityId = spl_object_id($entity);

        // Skip if already processed
        if (in_array($entityId, $this->processedEntities, true)) {
            return;
        }

        // Mark as processed
        $this->processedEntities[] = $entityId;

        $entityReflection = new ReflectionClass($entity);

        $this->processOneToManyRelations($entity, $entityReflection);
        $this->processManyToManyRelations($entity, $entityReflection);
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
        $oneToManyList = $this->entityManager->getDbMapping()->getOneToMany($entityName);

        if (!is_array($oneToManyList)) {
            return;
        }

        foreach ($oneToManyList as $property => $oneToMany) {
            $entities = $entityReflection->getProperty($property)->getValue($entity);

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
        $manyToManyList = $this->entityManager->getDbMapping()->getManyToMany($entityName);

        if (!is_array($manyToManyList)) {
            return;
        }

        foreach ($manyToManyList as $property => $manyToMany) {
            var_dump('DEBUG: RelationManager->processManyToManyRelations', $entity->getId(), $entityName);
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
     * @param DatabaseCollection<int|string, object> $entities
     * @param MtManyToMany $manyToMany
     * @return void
     */
    private function processDatabaseCollectionChanges(
        object $entity,
        DatabaseCollection $entities,
        MtManyToMany $manyToMany
    ): void {
        $addedEntities = $entities->getAddedEntities();
        if (!empty($addedEntities)) {
            foreach ($addedEntities as $relatedEntity) {
                $this->manyToManyInsertions[] = [
                    'entity' => $entity,
                    'related' => $relatedEntity,
                    'manyToMany' => $manyToMany,
                    'action' => 'insert'
                ];
            }
        }

        // Handle removals using the new DatabaseCollection method
        $removedEntities = $entities->getRemovedEntities();
        if (!empty($removedEntities)) {
            foreach ($removedEntities as $relatedEntity) {
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
     * @param Collection<int|string, object> $entities
     * @param MtManyToMany $manyToMany
     * @return void
     */
    private function processNewCollectionRelations(
        object $entity,
        Collection $entities,
        MtManyToMany $manyToMany
    ): void {
        foreach ($entities->items() as $relatedEntity) {
            $this->manyToManyInsertions[] = [
                'entity' => $entity,
                'related' => $relatedEntity,
                'manyToMany' => $manyToMany,
            ];
        }
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function executeManyToManyRelations(): void
    {
        if (empty($this->manyToManyInsertions)) {
            return;
        }

        // Group relations by unique key to avoid duplicates
        $uniqueRelations = [];

        foreach ($this->manyToManyInsertions as $relation) {
            $entity = $relation['entity'];
            $relatedEntity = $relation['related'];
            $manyToMany = $relation['manyToMany'];
            $action = $relation['action'] ?? 'insert';

            // Get IDs
            $entityId = $this->getId($entity);
            $relatedEntityId = $this->getId($relatedEntity);

            if ($entityId === null || $relatedEntityId === null) {
                continue;
            }

            // Create a unique key for this relation
            // Include the pivot table name to ensure uniqueness
            $pivotTable = $manyToMany->mappedBy ?? '';
            $relationKey = sprintf(
                '%s_%s_%s_%s_%s',
                $action,
                $pivotTable,
                min($entityId, $relatedEntityId),
                max($entityId, $relatedEntityId),
                $entityId < $relatedEntityId ? 'forward' : 'reverse'
            );

            // Store only the first occurrence of each unique relation
            if (!isset($uniqueRelations[$relationKey])) {
                $uniqueRelations[$relationKey] = $relation;
            }
        }

        // Process unique relations only
        foreach ($uniqueRelations as $relation) {
            $entity = $relation['entity'];
            $relatedEntity = $relation['related'];
            $manyToMany = $relation['manyToMany'];
            $action = $relation['action'] ?? 'insert';

            if ($action === 'delete') {
                // For deletions, we need to find and remove the existing link
                $linkRelation = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);
                if ($linkRelation !== null) {
                    $this->stateManager->scheduleForDeletion($linkRelation);
                } else {
                    // If no existing link found, try to find it in the database
                    $this->scheduleExistingLinkForDeletion($manyToMany, $entity, $relatedEntity);
                }
            } else {
                // For insertions, check if link already exists
                $linkRelation = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);
                if ($linkRelation === null) {
                    // Create a new link entity for insertions
                    try {
                        $linkEntity = $this->createLinkEntity($manyToMany, $entity, $relatedEntity);
                        $this->stateManager->scheduleForInsertion($linkEntity);
                    } catch (\Exception $e) {
                        // Log error if needed, continue processing other relations
                    }
                }
            }
        }

        // Clear the list after processing
        $this->manyToManyInsertions = [];
    }

    /**
     * Schedule an existing link entity for deletion by finding it in the database
     *
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return void
     */
    private function scheduleExistingLinkForDeletion(MtManyToMany $manyToMany, object $entity, object $relatedEntity): void
    {
        try {
            $mappedBy = $manyToMany->mappedBy;
            $joinProperty = $manyToMany->joinProperty;
            $inverseJoinProperty = $manyToMany->inverseJoinProperty;

            if ($mappedBy === null || $joinProperty === null || $inverseJoinProperty === null) {
                return;
            }

            // Find the existing link entity in the database
            $entityId = $this->getId($entity);
            $relatedEntityId = $this->getId($relatedEntity);

            if ($entityId === null || $relatedEntityId === null) {
                return;
            }

            // Build a query to find the link entity - DIRECTLY use PDO to avoid cache issues
            $tableName = $this->entityManager->getDbMapping()->getTableName($mappedBy);
            $joinColumn = $this->entityManager->getDbMapping()->getColumnName($mappedBy, $joinProperty);
            $inverseJoinColumn = $this->entityManager->getDbMapping()->getColumnName($mappedBy, $inverseJoinProperty);

            if ($tableName === null || $joinColumn === null || $inverseJoinColumn === null) {
                return;
            }

            // Use direct PDO query to find the link entity
            $pdo = $this->entityManager->getPdm();
            $sql = "SELECT * FROM `$tableName` WHERE `$joinColumn` = ? AND `$inverseJoinColumn` = ?";
            $statement = $pdo->prepare($sql);
            $statement->execute([$entityId, $relatedEntityId]);
            $linkData = $statement->fetch(\PDO::FETCH_ASSOC);
            $statement->closeCursor();

            if ($linkData && isset($linkData['id'])) {
                // Try to find the link entity in the identity map first
                $emEngine = $this->entityManager->getEmEngine();
                $linkEntity = $emEngine->getIdentityMap()->get($mappedBy, $linkData['id']);

                if ($linkEntity === null) {
                    // Create a minimal link entity just for deletion
                    // We don't need to fully hydrate it, just need the ID
                    $linkEntity = new $mappedBy();

                    // Set the ID using reflection if no setter exists
                    if (method_exists($linkEntity, 'setId')) {
                        $linkEntity->setId($linkData['id']);
                    } else {
                        $reflection = new \ReflectionClass($linkEntity);
                        if ($reflection->hasProperty('id')) {
                            $idProperty = $reflection->getProperty('id');
                            $idProperty->setAccessible(true);
                            $idProperty->setValue($linkEntity, $linkData['id']);
                        }
                    }

                    // Mark it as managed so it can be deleted
                    $this->stateManager->manage($linkEntity);
                }

                // Schedule for deletion
                $this->stateManager->scheduleForDeletion($linkEntity);
            }
        } catch (\Exception $e) {
            // If we can't find or delete the link, continue
            // Log the error if needed
        }
    }

    /**
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return object
     */
    private function createLinkEntity(MtManyToMany $manyToMany, object $entity, object $relatedEntity): object
    {
        if ($manyToMany->mappedBy === null) {
            throw new RuntimeException('MappedBy property is required for ManyToMany relations');
        }

        $linkEntity = new $manyToMany->mappedBy();
        $joinProperty = $manyToMany->joinProperty ?? '';
        $inverseJoinProperty = $manyToMany->inverseJoinProperty ?? '';

        if ($joinProperty === '' || $inverseJoinProperty === '') {
            throw new RuntimeException(
                sprintf(
                    'The many-to-many relation %s must have joinProperty and inverseJoinProperty defined',
                    $manyToMany->mappedBy
                )
            );
        }

        $setEntity = 'set' . ucfirst($joinProperty);
        $setRelatedEntity = 'set' . ucfirst($inverseJoinProperty);

        if (!method_exists($linkEntity, $setEntity) || !method_exists($linkEntity, $setRelatedEntity)) {
            throw new RuntimeException(
                sprintf(
                    'Link entity %s must have methods %s and %s',
                    $manyToMany->mappedBy,
                    $setEntity,
                    $setRelatedEntity
                )
            );
        }

        $linkEntity->$setEntity($entity);
        $linkEntity->$setRelatedEntity($relatedEntity);

        return $linkEntity;
    }

    /**
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return object|null
     */
    private function findExistingLinkRelation(MtManyToMany $manyToMany, object $entity, object $relatedEntity): ?object
    {
        $manyToMany->entity = $entity::class;

        if ($manyToMany->joinProperty === null || $manyToMany->inverseJoinProperty === null) {
            throw new RuntimeException(
                sprintf(
                    'Invalid MtManyToMany relation configuration between %s and %s classes.',
                    $entity::class,
                    $relatedEntity::class
                )
            );
        }

        $getEntity = 'get' . ucfirst($manyToMany->joinProperty);
        $getRelatedEntity = 'get' . ucfirst($manyToMany->inverseJoinProperty);

        foreach ($this->stateManager->getManagedEntities() as $managedEntity) {
            if (!($managedEntity instanceof $manyToMany->mappedBy)) {
                continue;
            }

            if (
                method_exists($managedEntity, $getEntity) &&
                method_exists($managedEntity, $getRelatedEntity) &&
                $managedEntity->$getEntity() !== null &&
                $managedEntity->$getRelatedEntity() !== null &&
                method_exists($entity, 'getId') &&
                method_exists($relatedEntity, 'getId') &&
                $managedEntity->$getEntity()->getId() === $entity->getId() &&
                $managedEntity->$getRelatedEntity()->getId() === $relatedEntity->getId()
            ) {
                return $managedEntity;
            }
        }

        return null;
    }

    /**
     * @param object $entity
     * @return int|null
     */
    private function getId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            throw new RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
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
}
