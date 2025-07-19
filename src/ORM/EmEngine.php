<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use Exception;
use MulerTech\Collections\Collection;
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
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;
use PDO;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Class EmEngine
 *
 * Core engine for entity management and ORM operations.
 *
 * @package MulerTech\Database
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
     * @param int|string $idOrWhere
     * @return object|null
     * @throws ReflectionException
     */
    public function find(string $entityName, int|string $idOrWhere): ?object
    {
        // Pour les recherches par SqlOperations ou string non-numeric, toujours aller en base
        // car l'IdentityMap est indexée par ID, pas par autres critères
        if (is_string($idOrWhere) && !is_numeric($idOrWhere)) {
            return $this->findByStringCondition($entityName, $idOrWhere);
        }

        // At this point, $idOrWhere is numeric (int or numeric string)
        // Check identity map first
        $managed = $this->identityMap->get($entityName, $idOrWhere);
        if ($managed !== null) {
            return $managed;
        }

        $queryBuilder = new QueryBuilder($this)
            ->select('*')
            ->from($this->getTableName($entityName))
            ->where('id', $idOrWhere);

        $result = $this->getQueryBuilderObjectResult($queryBuilder, $entityName);

        // Ensure we return null instead of false or any other falsy value
        return $result ?: null;
    }

    /**
     * Find entity by string condition, always going to database
     *
     * @param class-string $entityName
     * @param string $condition
     * @return object|null
     * @throws ReflectionException
     */
    private function findByStringCondition(string $entityName, string $condition): ?object
    {
        $queryBuilder = new QueryBuilder($this)
            ->select('*')
            ->from($this->getTableName($entityName))
            ->whereRaw($condition);

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
                } catch (Exception) {
                    // Continue silently on relation loading errors
                }
                return $managed;
            }
        }

        // Create new managed entity if not found in identity map
        return $this->createManagedEntity($fetch, $entityName, true);
    }

    /**
     * @param SelectBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return object|null
     * @throws ReflectionException
     */
    public function getQueryBuilderObjectResult(
        SelectBuilder $queryBuilder,
        string $entityName,
        bool $loadRelations = true
    ): ?object {
        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $fetch = $pdoStatement->fetch(PDO::FETCH_ASSOC);
        $pdoStatement->closeCursor();

        if (empty($fetch)) {
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
                    } catch (Exception) {
                        // Continue silently on relation loading errors
                    }
                }
                return $managed;
            }
        }

        return $this->createManagedEntity($fetch, $entityName, $loadRelations);
    }

    /**
     * @param SelectBuilder $queryBuilder
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array<object>|null
     * @throws ReflectionException
     */
    public function getQueryBuilderListResult(
        SelectBuilder $queryBuilder,
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
        $this->relationManager->clear();

        // Clear existing link cache to ensure fresh relation data
        $this->relationManager->clear();
    }

    /**
     * @param class-string $entityName
     * @param string|null $where
     * @return int
     * @throws ReflectionException
     */
    public function rowCount(string $entityName, ?string $where = null): int
    {
        $queryBuilder = new QueryBuilder($this)
            ->select('*')
            ->from($this->getTableName($entityName));

        if (is_numeric($where)) {
            $queryBuilder->where('id', $where);
        } elseif (is_string($where)) {
            $queryBuilder->whereLike('id', '%' . $where . '%');
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
            } catch (Exception) {
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
            $reflection = new ReflectionClass($entity);
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
        } catch (ReflectionException) {
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

    /**
     * @return IdentityMap
     */
    public function getIdentityMap(): IdentityMap
    {
        return $this->identityMap;
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

        return array_map(static function ($change) {
            return [
                'old' => $change->oldValue,
                'new' => $change->newValue,
            ];
        }, $changeSet->getChanges());
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

            $reflection = new ReflectionClass($entity);

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
                    new DateTimeImmutable()
                );
                $this->identityMap->updateMetadata($entity, $newMetadata);
            }
        } catch (Exception) {
            // If update fails, log but don't fail the operation
        }
    }

    /**
     * Check if a property represents a relation
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     * @throws ReflectionException
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
        return is_array($manyToManyList) && isset($manyToManyList[$propertyName]);
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
        $reflection = new ReflectionClass($entity);

        foreach (['id', 'uuid', 'identifier'] as $property) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $value = $prop->getValue($entity);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }
}
