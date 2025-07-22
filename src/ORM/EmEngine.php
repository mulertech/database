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
     * @var EntityHydrator
     */
    private EntityHydrator $hydrator;

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
        // Pour les recherches par string non-numeric, utiliser whereRaw directement
        if (is_string($idOrWhere) && !is_numeric($idOrWhere)) {
            $queryBuilder = new QueryBuilder($this)
                ->select('*')
                ->from($this->getTableName($entityName))
                ->whereRaw($idOrWhere);

            return $this->getQueryBuilderObjectResult($queryBuilder, $entityName);
        }

        // Check identity map first for numeric IDs
        $managed = $this->identityMap->get($entityName, $idOrWhere);
        if ($managed !== null) {
            return $managed;
        }

        $queryBuilder = new QueryBuilder($this)
            ->select('*')
            ->from($this->getTableName($entityName))
            ->where('id', $idOrWhere);

        return $this->getQueryBuilderObjectResult($queryBuilder, $entityName);
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
        if (is_array($fetch) && isset($fetch['id'])) {
            $entityId = $fetch['id'];
            if (is_int($entityId) || is_string($entityId)) {
                $managed = $this->identityMap->get($entityName, $entityId);
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
        }

        if (is_array($fetch)) {
            // Ensure the array has string keys for createManagedEntity
            $entityData = [];
            foreach ($fetch as $key => $value) {
                $stringKey = is_string($key) ? $key : (string)$key;
                $entityData[$stringKey] = $value;
            }
            return $this->createManagedEntity($entityData, $entityName, $loadRelations);
        }

        return null;
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

        // Ensure all entries have string keys for hydrateEntityList
        $validatedFetchAll = [];
        foreach ($fetchAll as $entityData) {
            if (is_array($entityData)) {
                $validatedEntityData = [];
                foreach ($entityData as $key => $value) {
                    $stringKey = is_string($key) ? $key : (string)$key;
                    $validatedEntityData[$stringKey] = $value;
                }
                $validatedFetchAll[] = $validatedEntityData;
            }
        }

        return $this->hydrateEntityList($validatedFetchAll, $entityName, $loadRelations);
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
        // Use hydrator directly to create and hydrate the entity
        $entity = $this->hydrator->hydrate($entityData, $entityName);

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

            // ALWAYS use DatabaseCollection for all relation collections to ensure change tracking
            if (!$reflectionProperty->isInitialized($entity)) {
                $reflectionProperty->setValue($entity, new DatabaseCollection([]));
            } else {
                // If already initialized, ALWAYS convert to DatabaseCollection
                $currentValue = $reflectionProperty->getValue($entity);
                if ($currentValue instanceof Collection && !($currentValue instanceof DatabaseCollection)) {
                    // Filter items to ensure they are objects
                    $items = $currentValue->items();
                    $objectItems = array_filter($items, static fn ($item): bool => is_object($item));
                    $reflectionProperty->setValue($entity, new DatabaseCollection($objectItems));
                } elseif (!($currentValue instanceof DatabaseCollection)) {
                    // Initialize with empty DatabaseCollection if it's not a Collection at all
                    $reflectionProperty->setValue($entity, new DatabaseCollection([]));
                }
            }
        } catch (ReflectionException) {
            // Property doesn't exist, ignore
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
            // entityData is already validated as array<string, mixed> by the method signature
            if (isset($entityData['id'])) {
                // Check if entity is already in identity map
                $entityId = $entityData['id'];
                if (is_int($entityId) || is_string($entityId)) {
                    $managed = $this->identityMap->get($entityName, $entityId);
                    if ($managed !== null) {
                        $entities[] = $managed;
                        continue;
                    }
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
                    $value = $this->hydrator->processValue($entityClass, $property, $dbData[$column]);
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
            $id = $entity->getId();
            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        if (method_exists($entity, 'getIdentifier')) {
            $id = $entity->getIdentifier();
            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        if (method_exists($entity, 'getUuid')) {
            $id = $entity->getUuid();
            if (is_int($id) || is_string($id)) {
                return $id;
            }
        }

        // Try direct property access
        $reflection = new ReflectionClass($entity);

        foreach (['id', 'uuid', 'identifier'] as $property) {
            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $value = $prop->getValue($entity);
                if ($value !== null && (is_int($value) || is_string($value))) {
                    return $value;
                }
            }
        }

        return null;
    }
}
