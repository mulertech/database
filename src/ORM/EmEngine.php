<?php

namespace MulerTech\Database\ORM;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\PersistenceManager;
use MulerTech\Database\ORM\Engine\Persistence\UpdateProcessor;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\State\DirectStateManager;
use MulerTech\Database\ORM\State\EntityState;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\ORM\State\StateTransitionManager;
use MulerTech\Database\ORM\State\StateValidator;
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
     * @var StateManagerInterface
     */
    private StateManagerInterface $stateManager;

    /**
     * @var IdentityMap
     */
    private IdentityMap $identityMap;

    /**
     * @var ChangeDetector
     */
    private ChangeDetector $changeDetector;

    /**
     * @var ChangeSetManager
     */
    private ChangeSetManager $changeSetManager;

    /**
     * @var EntityFactory
     */
    private EntityFactory $entityFactory;

    /**
     * @var EntityRegistry
     */
    private EntityRegistry $entityRegistry;

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
        // Pour les recherches par SqlOperations ou string non-numeric, toujours aller en base
        // car l'IdentityMap est indexée par ID, pas par autres critères
        if ($idOrWhere instanceof SqlOperations || (is_string($idOrWhere) && !is_numeric($idOrWhere))) {
            return $this->findByStringCondition($entityName, $idOrWhere);
        }

        // At this point, $idOrWhere is numeric (int or numeric string)
        // Check identity map first
        $managed = $this->identityMap->get($entityName, $idOrWhere);
        if ($managed !== null) {
            return $managed;
        }

        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->getTableName($entityName));
        $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($idOrWhere));

        $result = $this->getQueryBuilderObjectResult($queryBuilder, $entityName);

        // Ensure we return null instead of false or any other falsy value
        return $result ?: null;
    }

    /**
     * Find entity by string condition, always going to database
     *
     * @param class-string $entityName
     * @param string|SqlOperations $condition
     * @return object|null
     * @throws ReflectionException
     */
    private function findByStringCondition(string $entityName, string|SqlOperations $condition): ?object
    {
        $queryBuilder = new QueryBuilder($this);
        $queryBuilder->select('*')->from($this->getTableName($entityName))->where($condition);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $fetch = $pdoStatement->fetch(PDO::FETCH_ASSOC);
        $pdoStatement->closeCursor();

        if ($fetch === false || $fetch === null) {
            return null;
        }

        // Check if the entity is already in identity map
        if (isset($fetch['id'])) {
            $managed = $this->identityMap->get($entityName, $fetch['id']);
            if ($managed !== null) {
                // Update the managed entity with fresh data from database
                $this->updateManagedEntityFromDbData($managed, $fetch);

                // Force reload of relations to ensure fresh state
                try {
                    $this->relationManager->loadEntityRelations($managed, $fetch);
                } catch (\Exception $e) {
                    // Continue silently on relation loading errors
                }
                return $managed;
            }
        }

        // Create new managed entity if not found in identity map
        $entity = $this->createManagedEntity($fetch, $entityName, true);

        // IMPORTANT: Re-add to identity map with correct ID to ensure future finds work
        if (isset($fetch['id']) && !$this->identityMap->contains($entityName, $fetch['id'])) {
            $this->identityMap->add($entity);
        }

        return $entity;
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

        if ($fetch === false || $fetch === null || empty($fetch)) {
            return null;
        }

        // Check if the entity is already in identity map
        if (isset($fetch['id'])) {
            $managed = $this->identityMap->get($entityName, $fetch['id']);
            if ($managed !== null) {
                // Update the managed entity with fresh data from database
                $this->updateManagedEntityFromDbData($managed, $fetch);

                // Force reload of relations to ensure fresh state
                if ($loadRelations) {
                    try {
                        $this->relationManager->loadEntityRelations($managed, $fetch);
                    } catch (\Exception $e) {
                        // Continue silently on relation loading errors
                    }
                }
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
     */
    public function persist(object $entity): void
    {
        // Check if entity is already managed and has an ID
        $entityId = $this->extractEntityId($entity);
        $isManaged = $this->identityMap->isManaged($entity);

        // If entity already has an ID, it's already persisted
        if ($entityId !== null) {
            // Just ensure it's in the identity map and marked as managed
            if (!$isManaged) {
                $this->identityMap->add($entity);
                $this->stateManager->manage($entity);
            }
            return;
        }

        // Add to identity map if not already there
        if (!$isManaged) {
            $this->identityMap->add($entity);
        }

        // Only schedule for insertion if entity doesn't have an ID (is truly new)
        $this->stateManager->scheduleForInsertion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        // Use stateManager instead of changeSetManager
        $this->stateManager->scheduleForDeletion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        // Use stateManager for detach
        $this->stateManager->detach($entity);
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
        $this->identityMap->clear();
        $this->entityRegistry->clear();
        $this->changeSetManager->clear();
        $this->stateManager->clear();
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
            $queryBuilder->where('id = ' . $queryBuilder->addNamedParameter($where));
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
        $this->identityMap = new IdentityMap();
        $this->entityRegistry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector();
        $this->changeSetManager = new ChangeSetManager(
            $this->identityMap,
            $this->entityRegistry,
            $this->changeDetector
        );
        $this->hydrator = new EntityHydrator($dbMapping);
        $this->entityFactory = new EntityFactory($this->hydrator, $this->identityMap);

        // State management - Use direct state manager with ChangeSetManager integration
        $stateTransitionManager = new StateTransitionManager($eventManager);
        $stateValidator = new StateValidator();

        // Create DirectStateManager with ChangeSetManager
        $this->stateManager = new DirectStateManager(
            $this->identityMap,
            $stateTransitionManager,
            $stateValidator,
            $this->changeSetManager
        );

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
            $this->changeDetector,
            $this->relationManager,
            $insertionProcessor,
            $updateProcessor,
            $deletionProcessor,
            $eventManager,
            $this->changeSetManager,
            $this->identityMap
        );
    }

    /**
     * @param array<string, mixed> $entityData
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return object
     * @throws ReflectionException
     */
    public function createManagedEntity(array $entityData, string $entityName, bool $loadRelations): object
    {
        // Use entity factory to create and hydrate the entity
        $entity = $this->entityFactory->createFromDbData($entityName, $entityData);

        // CRITICAL: Ensure all collections are DatabaseCollection before adding to identity map
        $this->ensureCollectionsAreDatabaseCollection($entity);

        // Add to identity map first
        if (isset($entityData['id'])) {
            $this->identityMap->add($entity);
        }

        // Register in entity registry
        $this->entityRegistry->register($entity);

        // Mark as managed in state manager
        if (!$this->stateManager->isManaged($entity)) {
            $this->stateManager->manage($entity);
        }

        // Load relations after the entity is properly managed
        if ($loadRelations) {
            try {
                $this->relationManager->loadEntityRelations($entity, $entityData);
            } catch (\Exception $e) {
                // If relation loading fails, log but don't fail the entity creation
                // This ensures that at least the scalar properties are hydrated
            }
        }

        return $entity;
    }

    /**
     * Ensure all collection properties are DatabaseCollection instances
     *
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function ensureCollectionsAreDatabaseCollection(object $entity): void
    {
        $entityClass = $entity::class;
        $dbMapping = $this->entityManager->getDbMapping();

        // Convert OneToMany collections
        $oneToManyList = $dbMapping->getOneToMany($entityClass);
        if (is_array($oneToManyList)) {
            foreach ($oneToManyList as $property => $oneToMany) {
                $this->convertPropertyToeDatabaseCollection($entity, $property);
            }
        }

        // Convert ManyToMany collections
        $manyToManyList = $dbMapping->getManyToMany($entityClass);
        if (is_array($manyToManyList)) {
            foreach ($manyToManyList as $property => $manyToMany) {
                $this->convertPropertyToeDatabaseCollection($entity, $property);
            }
        }
    }

    /**
     * Convert a property to DatabaseCollection if it's a Collection
     *
     * @param object $entity
     * @param string $property
     * @return void
     */
    private function convertPropertyToeDatabaseCollection(object $entity, string $property): void
    {
        try {
            $reflection = new \ReflectionClass($entity);
            $reflectionProperty = $reflection->getProperty($property);

            if ($reflectionProperty->isInitialized($entity)) {
                $value = $reflectionProperty->getValue($entity);

                // Convert Collections to DatabaseCollection
                if ($value instanceof Collection && !($value instanceof DatabaseCollection)) {
                    $reflectionProperty->setValue($entity, new DatabaseCollection($value->items()));
                }
            } else {
                // Initialize uninitialized collection properties with empty DatabaseCollection
                $reflectionProperty->setValue($entity, new DatabaseCollection());
            }
        } catch (\ReflectionException $e) {
            // Property doesn't exist or can't be accessed, ignore
        }
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
        $entities = [];

        foreach ($entitiesData as $entityData) {
            if (isset($entityData['id'])) {
                // Check if entity is already in identity map
                $managed = $this->identityMap->get($entityName, $entityData['id']);
                if ($managed !== null) {
                    $entities[] = $managed;
                    continue;
                }
            }

            $entities[] = $this->createManagedEntity($entityData, $entityName, $loadRelations);
        }

        return $entities;
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
     * @return StateManagerInterface
     */
    public function getStateManager(): StateManagerInterface
    {
        return $this->stateManager;
    }

    /**
     * @return ChangeDetector
     */
    public function getChangeTracker(): ChangeDetector
    {
        return $this->changeDetector;
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
     * @return IdentityMap
     */
    public function getIdentityMap(): IdentityMap
    {
        return $this->identityMap;
    }

    /**
     * @return ChangeSetManager
     */
    public function getChangeSetManager(): ChangeSetManager
    {
        return $this->changeSetManager;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return $this->identityMap->isManaged($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool
    {
        return $this->changeSetManager->hasChanges($entity);
    }

    /**
     * @param object $entity
     * @return array<string, array<string, mixed>>
     */
    public function getChanges(object $entity): array
    {
        $changeSet = $this->changeSetManager->getChangeSet($entity);
        if ($changeSet === null) {
            return [];
        }

        $changes = [];
        foreach ($changeSet->getChanges() as $property => $change) {
            $changes[$property] = [
                'old' => $change->oldValue,
                'new' => $change->newValue
            ];
        }

        return $changes;
    }

    /**
     * @return bool
     */
    public function hasPendingChanges(): bool
    {
        return $this->changeSetManager->hasPendingChanges();
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return array_values($this->stateManager->getScheduledInsertions());
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return array_values($this->stateManager->getScheduledUpdates());
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return array_values($this->stateManager->getScheduledDeletions());
    }

    /**
     * Update a managed entity with fresh data from the database
     *
     * @param object $entity
     * @param array<string, mixed> $dbData
     * @return void
     */
    private function updateManagedEntityFromDbData(object $entity, array $dbData): void
    {
        try {
            $entityClass = $entity::class;
            $dbMapping = $this->entityManager->getDbMapping();
            $propertiesColumns = $dbMapping->getPropertiesColumns($entityClass);

            $reflection = new \ReflectionClass($entity);

            foreach ($propertiesColumns as $property => $column) {
                if (!isset($dbData[$column])) {
                    continue;
                }

                // Skip relation properties - they will be handled séparément
                if ($this->isRelationProperty($entityClass, $property)) {
                    continue;
                }

                if ($reflection->hasProperty($property)) {
                    $reflectionProperty = $reflection->getProperty($property);
                    $reflectionProperty->setAccessible(true);

                    // Process the value according to its type
                    $value = $this->hydrator->processPropertyValue($entityClass, $property, $dbData[$column]);
                    $reflectionProperty->setValue($entity, $value);
                }
            }

            // Update metadata with fresh data
            $metadata = $this->identityMap->getMetadata($entity);
            if ($metadata !== null) {
                $currentData = $this->changeDetector->extractCurrentData($entity);
                $newMetadata = new EntityMetadata(
                    $metadata->className,
                    $metadata->identifier,
                    EntityState::MANAGED,
                    $currentData,
                    $metadata->loadedAt,
                    new \DateTimeImmutable()
                );
                $this->identityMap->updateMetadata($entity, $newMetadata);
            }
        } catch (\Exception $e) {
            // If update fails, log but don't fail the operation
        }
    }

    /**
     * Check if a property represents a relation
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    private function isRelationProperty(string $entityClass, string $propertyName): bool
    {
        $dbMapping = $this->entityManager->getDbMapping();

        // Check if it's a OneToOne relation
        $oneToOneList = $dbMapping->getOneToOne($entityClass);
        if (is_array($oneToOneList) && isset($oneToOneList[$propertyName])) {
            return true;
        }

        // Check if it's a ManyToOne relation
        $manyToOneList = $dbMapping->getManyToOne($entityClass);
        if (is_array($manyToOneList) && isset($manyToOneList[$propertyName])) {
            return true;
        }

        // Check if it's a OneToMany relation
        $oneToManyList = $dbMapping->getOneToMany($entityClass);
        if (is_array($oneToManyList) && isset($oneToManyList[$propertyName])) {
            return true;
        }

        // Check if it's a ManyToMany relation
        $manyToManyList = $dbMapping->getManyToMany($entityClass);
        if (is_array($manyToManyList) && isset($manyToManyList[$propertyName])) {
            return true;
        }

        return false;
    }

    /**
     * @param object $entity
     * @return int|string|null
     */
    private function extractEntityId(object $entity): int|string|null
    {
        // Try common getter methods
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        if (method_exists($entity, 'getIdentifier')) {
            return $entity->getIdentifier();
        }

        if (method_exists($entity, 'getUuid')) {
            return $entity->getUuid();
        }

        // Try direct property access
        try {
            $reflection = new \ReflectionClass($entity);

            foreach (['id', 'uuid', 'identifier'] as $property) {
                if ($reflection->hasProperty($property)) {
                    $prop = $reflection->getProperty($property);
                    $prop->setAccessible(true);
                    $value = $prop->getValue($entity);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // Property access failed
        }

        return null;
    }
}
