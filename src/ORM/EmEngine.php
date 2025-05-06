<?php

namespace MulerTech\Database\ORM;

use _config\UpdateDatabaseMysql;
use App\Entity\Version;
use DateTime;
use Exception;
use MulerTech\ArrayManipulation\ArrayManipulation;
use MulerTech\Collections\Collection;
use MulerTech\Database\Event\DbEvents;
use MulerTech\Database\Event\PostFlushEvent;
use MulerTech\Database\Event\PostPersistEvent;
use MulerTech\Database\Event\PostRemoveEvent;
use MulerTech\Database\Event\PostUpdateEvent;
use MulerTech\Database\Event\PrePersistEvent;
use MulerTech\Database\Event\PreUpdateEvent;
use MulerTech\Database\Mapping\MtFk;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\EventManager\EventManager;
use MulerTech\FileManipulation\FileType\Json;
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
     * @deprecated use DbMapping
     * @var string
     */
    private const string DB_STRUCTURE_PATH = ".." . DIRECTORY_SEPARATOR . "_config" . DIRECTORY_SEPARATOR;
    /**
     * @deprecated use DbMapping
     * @var string
     */
    private const string DB_STRUCTURE_NAME = 'dbstructure.json';

    /**
     * @var array<int, object> The list of managed entities for example :
     * [$objectId => $entity]
     */
    private array $managedEntities = [];
    /**
     * @var array<int, object> The list of entities to be inserted for example :
     * [$objectId => $entity]
     */
    private array $entityInsertions = [];
    /**
     * @var array<int, object> The list of entities to be updated for example :
     * [$objectId => $entity]
     */
    private array $entityUpdates = [];
    /**
     * @var array<int, object> The list of entities to be deleted for example :
     * [$objectId => $entity]
     */
    private array $entityDeletions = [];
    /**
     * @var array<object> The list of MtManyToMany objects to be inserted.
     */
    private array $manyToManyInsertions = [];
    /**
     * @var array<int, array<string, array<int, string>>> Save all the entity changes example :
     * [$objectId => [$property => [$oldValue, $newValue]]]
     */
    private array $entityChanges = [];
    /**
     * @var array<int, array<string, mixed>> Save the original entity before update example :
     * [$objectId => [$column => $value]]
     */
    private array $originalEntityData = [];
    /**
     * @var array<int, array<int>> $entityInsertionOrder
     * * [$objectId1 => [$objectId2, $objectId3]] $objectId1 must be inserted after $objectId2 and $objectId3
     */
    private array $entityInsertionOrder = [];
    /**
     * @var array<class-string, array<int, string>> Save the entity and this event when updated example :
     * [$entityName => $eventCalled]
     */
    private array $eventCalled = [];
    /**
     * @deprecated
     * @var array
     */
    private array $tablesLinked = [];
    /**
     * @deprecated
     * @var string
     */
    private string $join = '';
    /**
     * @var EventManager|null $eventManager
     */
    private ?EventManager $eventManager;
    /**
     * @var EntityRelationLoader $entityRelationLoader
     */
    private EntityRelationLoader $entityRelationLoader;
    private EntityHydrator $hydrator;

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
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return object|null
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

        return $this->manageNewEntity($fetch, $entityName, $loadRelations);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array<object>|null
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

        return array_map(
            function ($entityData) use ($entityName, $loadRelations) {
                return $this->manageNewEntity($entityData, $entityName, $loadRelations);
            },
            $fetchAll
        );
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array|null
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

        $entities = [];
        foreach ($fetchAll as $entityData) {
            $entity = $this->manageNewEntity($entityData, $entityName, $loadRelations);
            $entities[$entity->getId()] = $entity;
        }

        return $entities;
    }

    /**
     * @param array $entityData
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return object
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
            if ($this->eventManager) {
                foreach ($entities as $entity) {
                    $this->eventManager->dispatch(new PostPersistEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * @return array<int, object>
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
     * @param Object $entity
     * @return QueryBuilder
     * @throws ReflectionException
     */
    private function generateInsertQueryBuilder(Object $entity): QueryBuilder
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
     * @param class-string $entityName
     * @return string
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
     * @param Object $entity
     * @return array<string, array<int, string>>
     */
    private function getEntityChanges(Object $entity): array
    {
        return $this->entityChanges[spl_object_id($entity)] ?? [];
    }

    /**
     * @param class-string $entityName
     * @param bool $keepId
     * @return array
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
     * Find an entity by its ID or a WHERE clause.
     * @param class-string $entityName
     * @param int|string|SqlOperations $idOrWhere
     * @return Object|null Entity filled or null
     * @throws ReflectionException
     */
    public function find(string $entityName, int|string|SqlOperations $idOrWhere): ?Object
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->getTableName($entityName));

        if (is_numeric($idOrWhere)) {
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($idOrWhere));
        } else {
            $queryBuilder->where($idOrWhere);
        }

        return $this->getQueryBuilderObjectResult($queryBuilder, $entityName);
    }

    /**
     * Manage new entity for creation, if this entity must be deleted, it will be removed from the entityInsertions.
     * @param Object $entity
     * @return void
     */
    public function persist(Object $entity): void
    {
        if ($this->getId($entity) === null && !isset($this->entityInsertions[spl_object_id($entity)])) {
            //Event Pre persist
            $this->eventManager?->dispatch(new PrePersistEvent($entity, $this->entityManager));
            $this->entityInsertions[spl_object_id($entity)] = $entity;
        }
    }

    /**
     * @param Object $entity
     * @return int|null
     */
    private function getId(Object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            throw new RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
    }

    /**
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
        $this->eventManager?->dispatch(new PostFlushEvent($this->entityManager));
    }

    /**
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
     * @param object $entity
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
     * @param array $entityData
     * @param class-string $entityName
     * @return object
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
     * @throws ReflectionException
     * Todo : set field_id if $property is a relational property into originalEntityData setter
     */
    private function computeEntityChanges(Object $entity): void
    {
        $originalEntityData = $this->originalEntityData[spl_object_id($entity)] ?? null;

        // properties = keys
        $properties = $this->getPropertiesColumns($entity::class);
        $entityReflection = new ReflectionClass($entity);

        $entityChanges = [];
        foreach ($properties as $property => $column) {
            $newValue = $entityReflection->getProperty($property)->getValue($entity);
            $oldValue = $originalEntityData ? $originalEntityData[$column] : null;

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
     * This method is used to manage the related entities not persisted yet
     * @param Object $entity
     * @param ReflectionClass $entityReflection
     * @throws ReflectionException
     */
    private function manageOneToManyRelations(Object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = get_class($entity);
        foreach ($this->entityManager->getDbMapping()->getOneToMany($entityName) as $property => $oneToMany) {
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
     * @throws ReflectionException
     */
    private function manageManyToManyRelations(Object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = get_class($entity);
        foreach ($this->entityManager->getDbMapping()->getManyToMany($entityName) as $property => $manyToMany) {
            $entities = $entityReflection->getProperty($property)->getValue($entity);

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
                continue;
            }

            // Check for any changes in the collection
            $addedEntities = $entities->getAddedEntities();
            if (!empty($addedEntities)) {
                // Process added entities
                foreach ($entities->getAddedEntities() as $relatedEntity) {
                    $this->manyToManyInsertions[] = [
                        'entity' => $entity,
                        'related' => $relatedEntity,
                        'manyToMany' => $manyToMany,
                    ];
                }
            }

            // Check for any deletions
            $deletedEntities = $entities->getDeletedEntities();
            if (!empty($deletedEntities)) {
                // Process deleted entities
                foreach ($deletedEntities as $relatedEntity) {
                    $this->manyToManyInsertions[] = [
                        'entity' => $entity,
                        'related' => $relatedEntity,
                        'manyToMany' => $manyToMany,
                        'action' => 'delete'
                    ];
                }
            }
        }
    }

    private function createLinkEntity(
        MtManyToMany $manyToMany,
        Object $entity,
        Object $relatedEntity
    ): object {
        $linkEntity = new $manyToMany->mappedBy();
        $setEntity = 'set' . ucfirst($manyToMany->joinProperty);
        $setRelatedEntity = 'set' . ucfirst($manyToMany->inverseJoinProperty);
        $linkEntity->$setEntity($entity);
        $linkEntity->$setRelatedEntity($relatedEntity);
        return $linkEntity;
    }

    /**
     *
     * @throws ReflectionException
     */
    private function executeUpdates(): void
    {
        if (!empty($this->entityUpdates)) {
            foreach ($this->entityUpdates as $uio => $entity) {
                $entityChanges = $this->getEntityChanges($entity);

                //Pre update Event
                if ($this->eventManager && !$this->isEventCalled($entity::class, DbEvents::preUpdate->value)) {
                    $this->eventCalled($entity::class, DbEvents::preUpdate->value);
                    $this->eventManager->dispatch(new PreUpdateEvent($entity, $this->entityManager, $entityChanges));
                }

                $queryBuilder = $this->generateUpdateQueryBuilder($entity);

                // prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                // close cursor
                $pdoStatement->closeCursor();
                unset($this->entityUpdates[$uio]);

                //Post update Event
                if ($this->eventManager && !$this->isEventCalled($entity::class, DbEvents::postUpdate->value)) {
                    $this->eventCalled($entity::class, DbEvents::postUpdate->value);
                    $this->eventManager->dispatch(new PostUpdateEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * @param class-string $entityName
     * @param string $event
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
     * @param string $entityName
     * @param string $event
     * @return bool
     */
    private function isEventCalled(string $entityName, string $event): bool
    {
        if (!isset($this->eventCalled[$entityName])) {
            return false;
        }

        return in_array($event, $this->eventCalled[$entityName], true);
    }

    /**
     * @param Object $entity
     * @return QueryBuilder
     * @throws ReflectionException
     */
    private function generateUpdateQueryBuilder(Object $entity): QueryBuilder
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
     * @param Object $entity
     */
    public function remove(Object $entity): void
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
     * @return void
     * @throws ReflectionException
     */
    private function executeDeletions(): void
    {
        if (!empty($this->entityDeletions)) {
            $entitiesEvent = [];
            foreach ($this->entityDeletions as $uio => $entity) {
                $queryBuilder = new QueryBuilder($this);
                $queryBuilder->delete($this->getTableName($entity::class));
                $queryBuilder->where(
                    SqlOperations::equal('id', $queryBuilder->addNamedParameter($this->getId($entity)))
                );

                // prepare request
                $pdoStatement = $queryBuilder->getResult();
                $pdoStatement->execute();

                // close cursor
                $pdoStatement->closeCursor();
                unset($this->entityDeletions[$uio]);
                $entitiesEvent[] = $entity;
            }

            //Event Post remove
            if ($this->eventManager) {
                foreach ($entitiesEvent as $entity) {
                    $this->eventManager->dispatch(new PostRemoveEvent($entity, $this->entityManager));
                }
            }
        }
    }

    /**
     * Process many-to-many relationship changes in database
     * This method should be called during flush
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

            $getEntity = 'get' . ucfirst($manyToMany->joinProperty);
            $getRelatedEntity = 'get' . ucfirst($manyToMany->inverseJoinProperty);

            if ($managedEntity->$getEntity() === $entity->id
                && $managedEntity->$getRelatedEntity() === $relatedEntity->id
            ) {
                return $managedEntity;
            }
        }

        return null;
    }

    /**
     * Count the result of the request with the table $table and the $where conditions
     * @param class-string $entityName
     * @param string|null $where
     * @return int
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