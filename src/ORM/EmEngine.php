<?php

namespace MulerTech\Database\ORM;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\Engine\EntityState\EntityChangeTracker;
use MulerTech\Database\ORM\Engine\EntityState\EntityStateManager;
use MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\PersistenceManager;
use MulerTech\Database\ORM\Engine\Persistence\UpdateProcessor;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\EventManager\EventManager;
use PDO;
use ReflectionException;
use RuntimeException;

/**
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
class EmEngine
{
    /**
     * @var EntityStateManager
     */
    private EntityStateManager $stateManager;

    /**
     * @var EntityChangeTracker
     */
    private EntityChangeTracker $changeTracker;

    /**
     * @var PersistenceManager
     */
    private PersistenceManager $persistenceManager;

    /**
     * @var RelationManager
     */
    private RelationManager $relationManager;

    /**
     * @var EntityHydrator
     */
    private EntityHydrator $hydrator;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->initializeComponents();
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @param class-string $entityName
     * @param int|string|SqlOperations $idOrWhere
     * @return object|null
     * @throws ReflectionException
     */
    public function find(string $entityName, int|string|SqlOperations $idOrWhere): ?object
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->getTableName($entityName));

        // If it's a numeric ID, check if the entity is already managed
        if (is_numeric($idOrWhere)) {
            $managed = $this->findManagedEntity($entityName, $idOrWhere);
            if ($managed !== null) {
                return $managed;
            }
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($idOrWhere, 'find_id'));
        } else {
            $queryBuilder->where($idOrWhere);
        }

        return $this->getQueryBuilderObjectResult($queryBuilder, $entityName);
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

        // Check if the entity is already managed
        if (isset($fetch['id'])) {
            $managed = $this->findManagedEntity($entityName, $fetch['id']);
            if ($managed !== null) {
                return $managed;
            }
        }

        return $this->createManagedEntity($fetch, $entityName, $loadRelations);
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

        return $this->hydrateEntityList($fetchAll, $entityName, $loadRelations);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array<int, object>|null
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
            if (!isset($entityData['id'])) {
                throw new RuntimeException(
                    sprintf('The entity %s must have an ID column', $entityName)
                );
            }

            $entity = $this->createManagedEntity($entityData, $entityName, $loadRelations);
            if (!method_exists($entity, 'getId')) {
                throw new RuntimeException(
                    sprintf('The entity %s must have a getId method', $entity::class)
                );
            }
            $entities[$entity->getId()] = $entity;
        }

        return $entities;
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function persist(object $entity): void
    {
        $this->persistenceManager->persist($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->persistenceManager->remove($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->persistenceManager->detach($entity);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        $this->persistenceManager->flush();
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->persistenceManager->clear();
    }

    /**
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
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($where, 'count_id'));
        } elseif (is_string($where)) {
            $queryBuilder->where($where);
        }

        $statement = $queryBuilder->getResult();
        $statement->execute();
        return $statement->rowCount();
    }

    /**
     * Initializes all components
     *
     * @return void
     */
    private function initializeComponents(): void
    {
        $dbMapping = $this->entityManager->getDbMapping();
        $eventManager = $this->entityManager->getEventManager();

        // Basic components
        $this->stateManager = new EntityStateManager();
        $this->changeTracker = new EntityChangeTracker($dbMapping);
        $this->hydrator = new EntityHydrator($dbMapping);

        // Persistence processors
        $insertionProcessor = new InsertionProcessor($this->entityManager, $dbMapping);
        $updateProcessor = new UpdateProcessor($this->entityManager, $dbMapping);
        $deletionProcessor = new DeletionProcessor($this->entityManager, $dbMapping);

        // Relation manager
        $this->relationManager = new RelationManager($this->entityManager, $this->stateManager);

        // Main persistence manager
        $this->persistenceManager = new PersistenceManager(
            $this->entityManager,
            $this->stateManager,
            $this->changeTracker,
            $this->relationManager,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor,
            $eventManager
        );
    }

    /**
     * @param array<string, mixed> $entityData
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return object
     * @throws ReflectionException
     */
    private function createManagedEntity(array $entityData, string $entityName, bool $loadRelations): object
    {
        $entity = $this->hydrator->hydrate($entityData, $entityName);
        $this->persistenceManager->manageNewEntity($entity, $entityData);

        if ($loadRelations) {
            $this->relationManager->loadEntityRelations($entity, $entityData);
        }

        return $entity;
    }

    /**
     * @param array<array<string, mixed>> $entitiesData
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array<object>
     * @throws ReflectionException
     */
    private function hydrateEntityList(array $entitiesData, string $entityName, bool $loadRelations): array
    {
        $managedEntitiesOfType = $this->indexManagedEntitiesByType($entityName);
        $entities = [];

        foreach ($entitiesData as $entityData) {
            if (isset($entityData['id']) && isset($managedEntitiesOfType[$entityData['id']])) {
                $entities[] = $managedEntitiesOfType[$entityData['id']];
            } else {
                $entities[] = $this->createManagedEntity($entityData, $entityName, $loadRelations);
            }
        }

        return $entities;
    }

    /**
     * @param class-string $entityName
     * @param int|string $id
     * @return object|null
     */
    private function findManagedEntity(string $entityName, int|string $id): ?object
    {
        foreach ($this->stateManager->getManagedEntities() as $entity) {
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
     * @param class-string $entityName
     * @return array<int|string, object>
     */
    private function indexManagedEntitiesByType(string $entityName): array
    {
        $indexed = [];

        foreach ($this->stateManager->getManagedEntities() as $entity) {
            if (
                $entity instanceof $entityName &&
                method_exists($entity, 'getId') &&
                $entity->getId() !== null
            ) {
                $indexed[$entity->getId()] = $entity;
            }
        }

        return $indexed;
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

    // Methods for compatibility with the existing API

    /**
     * @return EntityStateManager
     */
    public function getStateManager(): EntityStateManager
    {
        return $this->stateManager;
    }

    /**
     * @return EntityChangeTracker
     */
    public function getChangeTracker(): EntityChangeTracker
    {
        return $this->changeTracker;
    }

    /**
     * @return PersistenceManager
     */
    public function getPersistenceManager(): PersistenceManager
    {
        return $this->persistenceManager;
    }

    /**
     * @return RelationManager
     */
    public function getRelationManager(): RelationManager
    {
        return $this->relationManager;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return $this->stateManager->isManaged($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool
    {
        return $this->changeTracker->hasChanges($entity);
    }

    /**
     * @param object $entity
     * @return array<string, array<int, mixed>>
     */
    public function getChanges(object $entity): array
    {
        return $this->changeTracker->getChanges($entity);
    }

    /**
     * @return bool
     */
    public function hasPendingChanges(): bool
    {
        return $this->stateManager->hasPendingChanges();
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->stateManager->getScheduledInsertions();
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->stateManager->getScheduledUpdates();
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->stateManager->getScheduledDeletions();
    }
}
