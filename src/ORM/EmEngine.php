<?php

namespace MulerTech\Database\ORM;

use MulerTech\Collections\Collection;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreRemoveEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\EventManager\EventManager;
use PDO;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Class EmEngine
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EmEngine
{
    /**
     * The list of managed entities
     *
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $managedEntities = [];

    /**
     * The list of entities to be inserted
     *
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $entityInsertions = [];

    /**
     * The list of entities to be updated
     *
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $entityUpdates = [];

    /**
     * The list of entities to be deleted
     *
     * @var array<int, object> Format: [$objectId => $entity]
     */
    private array $entityDeletions = [];

    /**
     * The list of MtManyToMany objects to be inserted
     *
     * @var array<int, array{entity: object, related: object, manyToMany: MtManyToMany, action?: string}>
     */
    private array $manyToManyInsertions = [];

    /**
     * Save all the entity changes
     *
     * @var array<int, array<string, array<int, mixed>>> Format: [$objectId => [$property => [$oldValue, $newValue]]]
     */
    private array $entityChanges = [];

    /**
     * Save the original entity data before update
     *
     * @var array<int, array<string, mixed>> Format: [$objectId => [$column => $value]]
     */
    private array $originalEntityData = [];

    /**
     * Entity insertion order dependencies
     *
     * @var array<int, array<int>> Format: [$objectId1 => [$objectId2, $objectId3]]
     *                             $objectId1 must be inserted after $objectId2 and $objectId3
     */
    private array $entityInsertionOrder = [];

    /**
     * Save the entity and its event when updated
     *
     * @var array<class-string, array<int, string>> Format: [$entityName => $eventCalled]
     */
    private array $eventCalled = [];

    /**
     * Event manager instance
     *
     * @var EventManager|null
     */
    private ?EventManager $eventManager;

    /**
     * Entity relation loader instance
     *
     * @var EntityRelationLoader
     */
    private EntityRelationLoader $entityRelationLoader;

    /**
     * Entity hydrator instance
     *
     * @var EntityHydrator
     */
    private EntityHydrator $hydrator;

    /**
     * Indicates if flush is in progress
     *
     * @var bool
     */
    private bool $isFlushInProgress = false;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        $this->eventManager = $this->entityManager->getEventManager();
        $this->entityRelationLoader = new EntityRelationLoader($this->entityManager);
        $this->hydrator = new EntityHydrator($this->entityManager->getDbMapping());
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Retourne l'entité déjà managed si elle existe, sinon null.
     *
     * @param class-string $entityName
     * @param int|string $id
     * @return object|null
     */
    private function getManagedEntity(string $entityName, int|string $id): ?object
    {
        foreach ($this->managedEntities as $entity) {
            if (
                $entity instanceof $entityName &&
                method_exists($entity, 'getId') &&
                $entity->getId() == $id
            ) {
                return $entity;
            }
        }
        return null;
    }

    /**
     * Find an entity by its ID or a WHERE clause
     *
     * @param class-string $entityName The entity class name
     * @param int|string|SqlOperations $idOrWhere ID value or WHERE clause
     * @return object|null The found entity or null
     * @throws ReflectionException
     */
    public function find(string $entityName, int|string|SqlOperations $idOrWhere): ?object
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->getTableName($entityName));

        // If it's a numeric ID, check if the entity is already managed
        if (is_numeric($idOrWhere)) {
            $managed = $this->getManagedEntity($entityName, $idOrWhere);
            if ($managed !== null) {
                return $managed;
            }
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($idOrWhere));

            return $this->getQueryBuilderObjectResult($queryBuilder, $entityName);
        }

        $queryBuilder->where($idOrWhere);

        $entity = $this->getQueryBuilderObjectResult($queryBuilder, $entityName);

        // If the entity is not null, check if it is already managed
        if ($entity !== null && method_exists($entity, 'getId')) {
            $managed = $this->getManagedEntity($entityName, $entity->getId());
            if ($managed !== null) {
                return $managed;
            }
        }

        return $entity;
    }

    /**
     * Get a single object result from a query builder
     *
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param class-string $entityName The entity class name
     * @param bool $loadRelations Whether to load relations
     * @return object|null The fetched entity or null if not found
     * @throws ReflectionException
     */
    public function getQueryBuilderObjectResult(
        QueryBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?object {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $fetch = $pdoStatement->fetch(PDO::FETCH_ASSOC);
        $pdoStatement->closeCursor();

        if ($fetch === false) {
            return null;
        }

        // Vérifie si l'entité est déjà managed avant de l'hydrater
        if (isset($fetch['id'])) {
            $managed = $this->getManagedEntity($entityName, $fetch['id']);
            if ($managed !== null) {
                return $managed;
            }
        }

        return $this->manageNewEntity($fetch, $entityName, $loadRelations);
    }

    /**
     * Get a list of objects from a query builder
     *
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param class-string $entityName The entity class name
     * @param bool $loadRelations Whether to load relations
     * @return array<object>|null List of fetched entities or null if none found
     * @throws ReflectionException
     */
    public function getQueryBuilderListResult(
        QueryBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?array {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $fetchAll = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $pdoStatement->closeCursor();

        if ($fetchAll === []) {
            return null;
        }

        // Index temporaire des entités managées de ce type par id
        $managedEntitiesOfType = [];
        foreach ($this->managedEntities as $entity) {
            if (
                $entity instanceof $entityName &&
                method_exists($entity, 'getId') &&
                $entity->getId() !== null
            ) {
                $managedEntitiesOfType[$entity->getId()] = $entity;
            }
        }

        $entities = [];
        foreach ($fetchAll as $entityData) {
            if (isset($entityData['id']) && isset($managedEntitiesOfType[$entityData['id']])) {
                $entities[] = $managedEntitiesOfType[$entityData['id']];
            } else {
                $entities[] = $this->manageNewEntity($entityData, $entityName, $loadRelations);
            }
        }

        return $entities;
    }

    /**
     * Get a list of objects from a query builder, indexed by their ID
     *
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param class-string $entityName The entity class name
     * @param bool $loadRelations Whether to load relations
     * @return array<int, object>|null List of fetched entities indexed by ID or null if none found
     * @throws ReflectionException
     */
    public function getQueryBuilderListKeyByIdResult(
        QueryBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?array {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $fetchAll = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
        $pdoStatement->closeCursor();

        if ($fetchAll === []) {
            return null;
        }

        // Index temporaire des entités managées de ce type par id
        $managedEntitiesOfType = [];
        foreach ($this->managedEntities as $entity) {
            if (
                $entity instanceof $entityName &&
                method_exists($entity, 'getId') &&
                $entity->getId() !== null
            ) {
                $managedEntitiesOfType[$entity->getId()] = $entity;
            }
        }

        $entities = [];
        foreach ($fetchAll as $entityData) {
            if (!isset($entityData['id'])) {
                throw new RuntimeException(
                    sprintf('The entity %s must have an ID column', $entityName)
                );
            }

            if (isset($managedEntitiesOfType[$entityData['id']])) {
                $entities[$entityData['id']] = $managedEntitiesOfType[$entityData['id']];
            } else {
                $entity = $this->manageNewEntity($entityData, $entityName, $loadRelations);
                if (!method_exists($entity, 'getId')) {
                    throw new RuntimeException(
                        sprintf('The entity %s must have a getId method', $entity::class)
                    );
                }
                $entities[$entity->getId()] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Manage new entity creation and relation loading
     *
     * @param array<string, mixed> $entityData The entity data from database
     * @param class-string $entityName The entity class name
     * @param bool $loadRelations Whether to load relations
     * @return object The created and managed entity
     * @throws ReflectionException
     */
    private function manageNewEntity(array $entityData, string $entityName, bool $loadRelations): object
    {
        $entity = $this->computeOriginalEntity($entityData, $entityName);

        if ($loadRelations) {
            $this->entityRelationLoader->loadRelations($entity, $entityData);
        }

        return $entity;
    }

    /**
     * Execute entity insertions
     *
     * @return void
     * @throws ReflectionException
     */
    private function executeInsertions(): void
    {
        if (!empty($this->entityInsertions)) {
            $entities = [];

            foreach ($this->getEntityInsertions() as $uio => $entity) {
                $queryBuilder = $this->generateInsertQueryBuilder($entity);

                //prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                //set id
                if (!empty($lastId = $this->entityManager->getPdm()->lastInsertId())) {
                    if (!method_exists($entity, 'setId')) {
                        throw new RuntimeException(
                            sprintf('The entity %s must have a setId method', $entity::class)
                        );
                    }

                    $entity->setId($lastId);
                }

                //close cursor
                $pdoStatement->closeCursor();
                unset($this->entityInsertions[$uio]);

                //For event
                $entities[] = $entity;
            }
            //Event Post persist
            if ($this->eventManager && !$this->isFlushInProgress) {
                foreach ($entities as $entity) {
                    $this->eventManager->dispatch(new PostPersistEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * Get entity insertions with correct insertion order
     *
     * @return array<int, object> Ordered list of entities to be inserted
     */
    private function getEntityInsertions(): array
    {
        if (empty($this->entityInsertionOrder)) {
            return $this->entityInsertions;
        }

        $first = [];
        $second = [];

        foreach ($this->entityInsertionOrder as $childEntities) {
            foreach ($childEntities as $childEntityId) {
                if (!isset($this->entityInsertionOrder[$childEntityId])) {
                    $first[$childEntityId] = $this->entityInsertions[$childEntityId];
                    continue;
                }
                $second[$childEntityId] = $this->entityInsertions[$childEntityId];
            }
        }

        if ($first === []) {
            return $second + $this->entityInsertions;
        }

        if ($second === []) {
            return $first + $this->entityInsertions;
        }

        return $first + $second + $this->entityInsertions;
    }

    /**
     * Generate insert query builder for entity
     *
     * @param object $entity The entity to insert
     * @return QueryBuilder The insert query builder
     * @throws ReflectionException
     */
    private function generateInsertQueryBuilder(object $entity): QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->insert($this->getTableName(get_class($entity)));

        $entityChanges = $this->getEntityChanges($entity);

        foreach ($this->getPropertiesColumns($entity::class, false) as $property => $column) {
            if (!isset($entityChanges[$property][1])) {
                continue;
            }

            if (is_object($entityChanges[$property][1])) {
                $entityChanges[$property][1] = $this->getId($entityChanges[$property][1]);
            }

            $queryBuilder->setValue($column, $queryBuilder->addNamedParameter($entityChanges[$property][1]));
        }

        return $queryBuilder;
    }

    /**
     * Get table name for entity
     *
     * @param class-string $entityName The entity class name
     * @return string The table name
     * @throws ReflectionException
     */
    private function getTableName(string $entityName): string
    {
        $tableName = $this->entityManager->getDbMapping()->getTableName($entityName);

        if ($tableName === null) {
            throw new RuntimeException(
                sprintf('The entity %s is not mapped in the database', $entityName)
            );
        }

        return $tableName;
    }

    /**
     * Get entity changes
     *
     * @param object $entity The entity to check for changes
     * @return array<string, array<int, mixed>> The changes as [property => [oldValue, newValue]]
     */
    private function getEntityChanges(object $entity): array
    {
        return $this->entityChanges[spl_object_id($entity)] ?? [];
    }

    /**
     * Get properties columns mapping for entity
     *
     * @param class-string $entityName The entity class name
     * @param bool $keepId Whether to keep ID in the result
     * @return array<string, string> Property to column mapping
     * @throws ReflectionException
     */
    private function getPropertiesColumns(string $entityName, bool $keepId = true): array
    {
        $propertiesColumns = $this->entityManager->getDbMapping()->getPropertiesColumns($entityName);

        if ($keepId) {
            return $propertiesColumns;
        }

        if (isset($propertiesColumns['id'])) {
            unset($propertiesColumns['id']);
        }

        return $propertiesColumns;
    }

    /**
     * Manage new entity for creation
     *
     * @param object $entity The entity to persist
     * @return void
     */
    public function persist(object $entity): void
    {
        // Check if the entity is already managed
        if (!isset($this->managedEntities[spl_object_id($entity)])) {
            // Add the entity to managed entities
            $this->managedEntities[spl_object_id($entity)] = $entity;
        }

        // If the entity is already in insertions, do not add it again
        if ($this->getId($entity) === null && !isset($this->entityInsertions[spl_object_id($entity)])) {
            //Event Pre persist
            if ($this->eventManager !== null && !$this->isFlushInProgress) {
                $this->eventManager->dispatch(new PrePersistEvent($entity, $this->entityManager));
            }
            $this->entityInsertions[spl_object_id($entity)] = $entity;
        }
    }

    /**
     * Get entity ID
     *
     * @param object $entity The entity
     * @return int|null The entity ID or null if not set
     * @throws RuntimeException If entity doesn't have getId method
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
     * Flush all changes to database
     *
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        // Before begin transaction, prepare entity changes
        $this->removeDeleteEntitiesFromUpdates();
        $this->computeEntitiesChanges();

        if (!($this->entityInsertions || $this->entityUpdates || $this->entityDeletions)) {
            return;
        }

        // process flush entities
        $this->entityManager->getPdm()->beginTransaction();

        if (!empty($this->entityInsertions)) {
            $this->executeInsertions();
        }

        if (!empty($this->entityUpdates)) {
            $this->executeUpdates();
        }

        if (!empty($this->entityDeletions)) {
            $this->executeDeletions();
        }

        if (!empty($this->manyToManyInsertions)) {
            $this->executeManyToManyRelations();
        }

        $this->entityManager->getPdm()->commit();

        //Event Post flush
        if ($this->eventManager !== null && !$this->isFlushInProgress) {
            $this->isFlushInProgress = true;
            $this->eventManager->dispatch(new PostFlushEvent($this->entityManager));
        }
    }

    /**
     * Remove deleted entities from updates list
     *
     * @return void
     */
    private function removeDeleteEntitiesFromUpdates(): void
    {
        foreach ($this->entityDeletions as $entity) {
            if (isset($this->entityUpdates[spl_object_id($entity)])) {
                unset($this->entityUpdates[spl_object_id($entity)]);
            }
        }
    }

    /**
     * Detach entity from entity manager
     *
     * @param object $entity The entity to detach
     * @return void
     */
    public function detach(object $entity): void
    {
        $objectId = spl_object_id($entity);

        unset(
            $this->managedEntities[$objectId],
            $this->entityInsertions[$objectId],
            $this->entityUpdates[$objectId],
            $this->entityDeletions[$objectId],
            $this->entityChanges[$objectId],
            $this->originalEntityData[$objectId]
        );
    }

    /**
     * Compute changes for all managed entities
     *
     * @return void
     * @throws ReflectionException
     */
    private function computeEntitiesChanges(): void
    {
        foreach ($this->entityInsertions as $entity) {
            $this->computeEntityChanges($entity);
        }

        foreach ($this->managedEntities as $entity) {
            if (isset($this->entityDeletions[spl_object_id($entity)])) {
                continue;
            }

            $this->computeEntityChanges($entity);
        }
    }

    /**
     * Compute entity from original data
     *
     * @param array<string, mixed> $entityData The entity data
     * @param class-string $entityName The entity class name
     * @return object The created entity
     * @throws ReflectionException
     */
    private function computeOriginalEntity(array $entityData, string $entityName): object
    {
        $entity = $this->hydrator->hydrate($entityData, $entityName);

        $this->managedEntities[spl_object_id($entity)] = $entity;
        $this->originalEntityData[spl_object_id($entity)] = $entityData;

        return $entity;
    }

    /**
     * Compute entity changes
     *
     * @param object $entity The entity to compute changes for
     * @return void
     * @throws ReflectionException
     */
    private function computeEntityChanges(object $entity): void
    {
        $originalEntityData = $this->originalEntityData[spl_object_id($entity)] ?? null;

        // properties = keys
        $properties = $this->getPropertiesColumns($entity::class);
        $entityReflection = new ReflectionClass($entity);

        $entityChanges = [];
        foreach ($properties as $property => $column) {
            $newValue = $entityReflection->getProperty($property)->getValue($entity);
            $oldValue = (is_array($originalEntityData) && isset($originalEntityData[$column]))
                ? $originalEntityData[$column] : null;
            if ($oldValue === $newValue && !is_null($originalEntityData)) {
                continue;
            }

            // One-to-one entity relation who is not persisted yet (not id)
            // Add the entity to the entityInsertions list and persist it
            if (is_object($newValue) && is_null($this->getId($newValue))) {
                if (!isset($this->entityInsertions[spl_object_id($newValue)])) {
                    $this->persist($newValue);
                    $this->computeEntityChanges($newValue);
                }
                $this->entityInsertionOrder[spl_object_id($entity)] = [spl_object_id($newValue)];
            }

            $entityChanges[$property] = [$oldValue, $newValue];
        }

        // If there is an update, add the entity to the updates list
        if (
            $entityChanges !== [] &&
            !is_null($originalEntityData) &&
            !isset($this->entityUpdates[spl_object_id($entity)])
        ) {
            $this->entityUpdates[spl_object_id($entity)] = $entity;
        }

        $this->manageOneToManyRelations($entity, $entityReflection);
        $this->manageManyToManyRelations($entity, $entityReflection);

        $this->entityChanges[spl_object_id($entity)] = $entityChanges;
    }

    /**
     * Manage one-to-many relations for entity
     *
     * @param object $entity The entity with relations
     * @param ReflectionClass<object> $entityReflection The entity reflection
     * @return void
     * @throws ReflectionException
     */
    private function manageOneToManyRelations(object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = get_class($entity);
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
                // Check if the related entity is not already managed
                if (is_object($relatedEntity) && $this->getId($relatedEntity) === null) {
                    $this->persist($relatedEntity);
                    $this->computeEntityChanges($relatedEntity);
                }
            }
        }
    }

    /**
     * Manage many-to-many relations for entity
     *
     * @param object $entity The entity with relations
     * @param ReflectionClass<object> $entityReflection The entity reflection
     * @return void
     * @throws ReflectionException
     */
    private function manageManyToManyRelations(object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = get_class($entity);
        $manyToManyList = $this->entityManager->getDbMapping()->getManyToMany($entityName);
        if (!is_array($manyToManyList)) {
            return;
        }
        foreach ($manyToManyList as $property => $manyToMany) {
            $entities = $entityReflection->getProperty($property)->getValue($entity);

            if ($entities instanceof DatabaseCollection) {
                // Check for any changes in the collection
                $addedEntities = $entities->getAddedEntities();
                if (!empty($addedEntities)) {
                    // Process added entities
                    foreach ($addedEntities as $relatedEntity) {
                        $this->manyToManyInsertions[] = [
                            'entity' => $entity,
                            'related' => $relatedEntity,
                            'manyToMany' => $manyToMany,
                        ];
                    }
                }

                // Check for any deletions
                $removedEntities = $entities->getRemovedEntities();
                if (!empty($removedEntities)) {
                    // Process deleted entities
                    foreach ($removedEntities as $relatedEntity) {
                        $this->manyToManyInsertions[] = [
                            'entity' => $entity,
                            'related' => $relatedEntity,
                            'manyToMany' => $manyToMany,
                            'action' => 'delete'
                        ];
                    }
                }

                continue;
            }

            if ($entities instanceof Collection) {
                if (count($entities) === 0) {
                    continue;
                }

                // If the collection is instance of Collection, all the related entities are not persisted yet
                foreach ($entities->items() as $relatedEntity) {
                    $this->manyToManyInsertions[] = [
                        'entity' => $entity,
                        'related' => $relatedEntity,
                        'manyToMany' => $manyToMany,
                    ];
                }
            }
        }
    }

    /**
     * Create link entity for many-to-many relation
     *
     * @param MtManyToMany $manyToMany The many-to-many relation configuration
     * @param object $entity The owner entity
     * @param object $relatedEntity The related entity
     * @return object The created link entity
     */
    private function createLinkEntity(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): object {
        $linkEntity = new $manyToMany->mappedBy();
        $joinProperty = $manyToMany->joinProperty ?? '';
        $inverseJoinProperty = $manyToMany->inverseJoinProperty ?? '';
        if ($joinProperty === '' || $inverseJoinProperty === '') {
            throw new RuntimeException(
                sprintf(
                    'The many-to-many relation %s must have joinProperty and inverseJoinProperty defined',
                    $manyToMany->mappedBy ?? 'unknown'
                )
            );
        }
        $setEntity = 'set' . ucfirst($joinProperty);
        $setRelatedEntity = 'set' . ucfirst($inverseJoinProperty);
        $linkEntity->$setEntity($entity);
        $linkEntity->$setRelatedEntity($relatedEntity);
        return $linkEntity;
    }

    /**
     * Execute updates for entities
     *
     * @return void
     * @throws ReflectionException
     */
    private function executeUpdates(): void
    {
        if (!empty($this->entityUpdates)) {
            $eventManager = $this->eventManager;

            foreach ($this->entityUpdates as $uio => $entity) {
                $entityChanges = $this->getEntityChanges($entity);

                //Pre update Event
                if ($eventManager !== null && !$this->isEventCalled($entity::class, DbEvents::preUpdate->value)) {
                    $this->eventCalled($entity::class, DbEvents::preUpdate->value);
                    $eventManager->dispatch(new PreUpdateEvent($entity, $this->entityManager, $entityChanges));
                }

                $queryBuilder = $this->generateUpdateQueryBuilder($entity);

                // prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                // close cursor
                $pdoStatement->closeCursor();
                unset($this->entityUpdates[$uio]);

                //Post update Event
                if ($eventManager !== null && !$this->isEventCalled($entity::class, DbEvents::postUpdate->value)) {
                    $this->eventCalled($entity::class, DbEvents::postUpdate->value);
                    $eventManager->dispatch(new PostUpdateEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * Track that an event has been called for an entity
     *
     * @param class-string $entityName The entity class name
     * @param string $event The event name
     * @return void
     */
    private function eventCalled(string $entityName, string $event): void
    {
        if (isset($this->eventCalled[$entityName])) {
            $this->eventCalled[$entityName][] = $event;
            return;
        }

        $this->eventCalled[$entityName] = [$event];
    }

    /**
     * Check if an event has been called for an entity
     *
     * @param string $entityName The entity class name
     * @param string $event The event name
     * @return bool True if event has been called
     */
    private function isEventCalled(string $entityName, string $event): bool
    {
        if (!isset($this->eventCalled[$entityName])) {
            return false;
        }

        return in_array($event, $this->eventCalled[$entityName], true);
    }

    /**
     * Generate update query builder for entity
     *
     * @param object $entity The entity to update
     * @return QueryBuilder The query builder
     * @throws ReflectionException
     */
    private function generateUpdateQueryBuilder(object $entity): QueryBuilder
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->update($this->getTableName($entity::class));

        $entityChanges = $this->getEntityChanges($entity);

        foreach ($this->getPropertiesColumns($entity::class, false) as $property => $column) {
            if (!isset($entityChanges[$property][1])) {
                continue;
            }

            if (is_object($entityChanges[$property][1])) {
                $entityChanges[$property][1] = $this->getId($entityChanges[$property][1]);
            }

            $queryBuilder->setValue($column, $queryBuilder->addDynamicParameter($entityChanges[$property][1]));
        }
        $queryBuilder->where(SqlOperations::equal('id', $queryBuilder->addDynamicParameter($this->getId($entity))));

        return $queryBuilder;
    }

    /**
     * Mark entity for removal
     *
     * @param object $entity The entity to remove
     * @return void
     */
    public function remove(object $entity): void
    {
        if (isset($this->entityUpdates[spl_object_id($entity)])) {
            unset($this->entityUpdates[spl_object_id($entity)]);
        }

        if (isset($this->entityInsertions[spl_object_id($entity)])) {
            // It is not necessary to insert an entity and then remove it.
            unset($this->entityInsertions[spl_object_id($entity)]);
            return;
        }

        $this->entityDeletions[spl_object_id($entity)] = $entity;
    }

    /**
     * Execute entity deletions
     *
     * @return void
     * @throws ReflectionException
     */
    private function executeDeletions(): void
    {
        if (!empty($this->entityDeletions)) {
            $eventManager = $this->eventManager;
            $entitiesEvent = [];
            foreach ($this->entityDeletions as $uio => $entity) {
                $queryBuilder = new QueryBuilder($this);
                $queryBuilder->delete($this->getTableName($entity::class));
                $queryBuilder->where(
                    SqlOperations::equal('id', $queryBuilder->addNamedParameter($this->getId($entity)))
                );

                // Pre remove Event
                if ($eventManager !== null && !$this->isEventCalled($entity::class, DbEvents::preRemove->value)) {
                    $this->eventCalled($entity::class, DbEvents::preRemove->value);
                    $eventManager->dispatch(new PreRemoveEvent($entity, $this->entityManager));
                }

                // prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                // close cursor
                $pdoStatement->closeCursor();
                unset($this->entityDeletions[$uio]);
                $entitiesEvent[] = $entity;
            }

            //Event Post remove
            if ($eventManager !== null) {
                foreach ($entitiesEvent as $entity) {
                    $eventManager->dispatch(new PostRemoveEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * Process many-to-many relationship changes in database
     *
     * @return void
     * @throws ReflectionException
     */
    private function executeManyToManyRelations(): void
    {
        if (empty($this->manyToManyInsertions)) {
            return;
        }

        foreach ($this->manyToManyInsertions as $key => $relation) {
            $entity = $relation['entity'];
            $relatedEntity = $relation['related'];
            $manyToMany = $relation['manyToMany'];
            $action = $relation['action'] ?? 'insert';

            $linkRelation = $this->getLinkRelation($manyToMany, $entity, $relatedEntity);
            if (!is_null($linkRelation)) {
                if ($action === 'delete') {
                    $this->remove($linkRelation);
                }

                unset($this->manyToManyInsertions[$key]);
                continue;
            }

            // Unmanaged entities
            $linkEntity = $this->createLinkEntity($manyToMany, $entity, $relatedEntity);
            if ($action === 'insert') {
                $this->persist($linkEntity);
            } else {
                $this->remove($linkEntity);
            }

            unset($this->manyToManyInsertions[$key]);
        }
        $this->flush();
    }

    /**
     * Get link relation entity for many-to-many relation
     *
     * @param MtManyToMany $manyToMany The many-to-many relation configuration
     * @param object $entity The owner entity
     * @param object $relatedEntity The related entity
     * @return object|null The link entity if found
     */
    private function getLinkRelation(MtManyToMany $manyToMany, object $entity, object $relatedEntity): ?object
    {
        if ($manyToMany->entity === null) {
            $manyToMany->entity = $entity::class;
        }

        if ($manyToMany->joinProperty === null || $manyToMany->inverseJoinProperty === null) {
            throw new RuntimeException(
                sprintf(
                    'Invalid MtManyToMany relation configuration between %s and %s classes.',
                    $entity::class,
                    $relatedEntity::class
                )
            );
        }

        foreach ($this->managedEntities as $managedEntity) {
            if (!($managedEntity instanceof $manyToMany->mappedBy)) {
                continue;
            }

            $getEntity = 'get' . ucfirst($manyToMany->joinProperty ?? '');
            $getRelatedEntity = 'get' . ucfirst($manyToMany->inverseJoinProperty ?? '');

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
     * Count the result of the request
     *
     * @param class-string $entityName The entity class name
     * @param string|null $where WHERE condition
     * @return int Number of matching rows
     * @throws ReflectionException
     */
    public function rowCount(string $entityName, ?string $where = null): int
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->getTableName($entityName));

        if (is_numeric($where)) {
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($where));
        } elseif (is_string($where)) {
            $queryBuilder->where($where);
        }

        $statement = $queryBuilder->getResult();
        $statement->execute();
        return $statement->rowCount();
    }
}
