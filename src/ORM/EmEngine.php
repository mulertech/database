<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use Exception;
use MulerTech\Collections\Collection;
use MulerTech\Database\Core\Cache\MetadataCache;
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
        private readonly MetadataCache $metadataCache
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
                    // For managed entities, only update scalar properties, NOT relations
                    // This prevents overwriting relation changes that haven't been flushed yet
                    $this->updateManagedEntityScalarPropertiesOnly($managed, $fetch);
                    return $managed;
                }
            }
        }

        if (is_array($fetch)) {
            $entityData = [];
            foreach ($fetch as $key => $value) {
                $stringKey = (string)$key;
                if (is_scalar($value) || $value === null) {
                    $entityData[$stringKey] = $value;
                } else {
                    $entityData[$stringKey] = null;
                }
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

        $validatedFetchAll = [];
        foreach ($fetchAll as $entityData) {
            if (is_array($entityData)) {
                $validatedEntityData = [];
                foreach ($entityData as $key => $value) {
                    $stringKey = (string)$key;
                    if (is_scalar($value) || $value === null) {
                        $validatedEntityData[$stringKey] = $value;
                    } else {
                        $validatedEntityData[$stringKey] = null;
                    }
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
                $this->getStateManager()->manage($entity);
            }
            return;
        }

        // Add to identity map if not already there
        if (!$isManaged) {
            $this->identityMap->add($entity);
        }

        // Only schedule for insertion if entity doesn't have an ID (is truly new)
        $this->getStateManager()->scheduleForInsertion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->getStateManager()->scheduleForDeletion($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->getStateManager()->detach($entity);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        $this->getPersistenceManager()->flush();
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->identityMap->clear();
        $this->entityRegistry->clear();
        $this->changeSetManager->clear();

        if (isset($this->stateManager)) {
            $this->stateManager->clear();
        }

        if (isset($this->relationManager)) {
            $this->relationManager->clear();
        }
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
        // Initialize core components that are directly used by EmEngine
        $this->identityMap = new IdentityMap();
        $this->entityRegistry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector();

        // ChangeSetManager needs core components
        $this->changeSetManager = new ChangeSetManager(
            $this->identityMap,
            $this->entityRegistry,
            $this->changeDetector
        );

        // EntityHydrator needs DbMapping
        $this->hydrator = new EntityHydrator($this->metadataCache);
    }

    /**
     * Get or create StateManager lazily
     *
     * @return StateManagerInterface
     */
    private function getStateManager(): StateManagerInterface
    {
        if (!isset($this->stateManager)) {
            $this->stateManager = new DirectStateManager(
                $this->identityMap,
                $this->getStateTransitionManager(),
                $this->getStateValidator(),
                $this->changeSetManager
            );
        }

        return $this->stateManager;
    }

    /**
     * Get or create PersistenceManager lazily
     *
     * @return PersistenceManager
     */
    private function getPersistenceManager(): PersistenceManager
    {
        if (!isset($this->persistenceManager)) {
            $this->persistenceManager = new PersistenceManager(
                $this->entityManager,
                $this->getStateManager(),
                $this->changeDetector,
                $this->getRelationManager(),
                $this->getInsertionProcessor(),
                $this->getUpdateProcessor(),
                $this->getDeletionProcessor(),
                $this->entityManager->getEventManager(),
                $this->changeSetManager,
                $this->identityMap,
            );
        }

        return $this->persistenceManager;
    }

    /**
     * Get or create RelationManager lazily
     *
     * @return RelationManager
     */
    private function getRelationManager(): RelationManager
    {
        if (!isset($this->relationManager)) {
            $this->relationManager = new RelationManager($this->entityManager, $this->getStateManager());
        }

        return $this->relationManager;
    }

    /**
     * Get or create StateTransitionManager lazily
     *
     * @return StateTransitionManager
     */
    private function getStateTransitionManager(): StateTransitionManager
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new StateTransitionManager($this->entityManager->getEventManager());
        }

        return $instance;
    }

    /**
     * Get or create StateValidator lazily
     *
     * @return StateValidator
     */
    private function getStateValidator(): StateValidator
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new StateValidator();
        }

        return $instance;
    }

    /**
     * Get or create InsertionProcessor lazily
     *
     * @return InsertionProcessor
     */
    private function getInsertionProcessor(): InsertionProcessor
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new InsertionProcessor($this->entityManager, $this->entityManager->getDbMapping());
        }

        return $instance;
    }

    /**
     * Get or create UpdateProcessor lazily
     *
     * @return UpdateProcessor
     */
    private function getUpdateProcessor(): UpdateProcessor
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new UpdateProcessor($this->entityManager, $this->entityManager->getDbMapping());
        }

        return $instance;
    }

    /**
     * Get or create DeletionProcessor lazily
     *
     * @return DeletionProcessor
     */
    private function getDeletionProcessor(): DeletionProcessor
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new DeletionProcessor($this->entityManager, $this->entityManager->getDbMapping());
        }

        return $instance;
    }

    /**
     * @param array<string, bool|float|int|string|null> $entityData
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

        $this->entityRegistry->register($entity);

        if (!$this->getStateManager()->isManaged($entity)) {
            $this->getStateManager()->manage($entity);
        }

        if ($loadRelations) {
            try {
                $this->getRelationManager()->loadEntityRelations($entity, $entityData);
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
        foreach ($oneToManyList as $property => $oneToMany) {
            $this->convertPropertyToeDatabaseCollection($entity, $property);
        }

        // Convert ManyToMany collections
        $manyToManyList = $dbMapping->getManyToMany($entityClass);
        foreach ($manyToManyList as $property => $manyToMany) {
            $this->convertPropertyToeDatabaseCollection($entity, $property);
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

            if (!$reflectionProperty->isInitialized($entity)) {
                $reflectionProperty->setValue($entity, new DatabaseCollection([]));
                return;
            }

            $currentValue = $reflectionProperty->getValue($entity);
            if ($currentValue instanceof DatabaseCollection) {
                return;
            }
            if ($currentValue instanceof Collection) {
                $items = $currentValue->items();
                $objectItems = array_filter($items, static fn ($item): bool => is_object($item));
                $reflectionProperty->setValue($entity, new DatabaseCollection($objectItems));
                return;
            }
            $reflectionProperty->setValue($entity, new DatabaseCollection([]));
        } catch (ReflectionException) {
            // Property doesn't exist, ignore
        }
    }

    /**
     * @param array<array<string, bool|float|int|string|null>> $entitiesData
     * @param class-string $entityName
     * @param bool $loadRelations
     * @return array<object>
     * @throws ReflectionException
     */
    private function hydrateEntityList(array $entitiesData, string $entityName, bool $loadRelations): array
    {
        $entities = [];

        foreach ($entitiesData as $entityData) {
            $validatedEntityData = [];
            foreach ($entityData as $key => $value) {
                $stringKey = $key;
                $validatedEntityData[$stringKey] = $value;
            }
            if (isset($validatedEntityData['id'])) {
                $entityId = $validatedEntityData['id'];
                if (is_int($entityId) || is_string($entityId)) {
                    $managed = $this->identityMap->get($entityName, $entityId);
                    if ($managed !== null) {
                        $entities[] = $managed;
                        continue;
                    }
                }
            }

            $entities[] = $this->createManagedEntity($validatedEntityData, $entityName, $loadRelations);
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
     * Update a managed entity with fresh scalar data from the database, preserving relations
     *
     * @param object $entity
     * @param array<string, string|int|float|bool|null> $dbData
     * @return void
     */
    private function updateManagedEntityScalarPropertiesOnly(object $entity, array $dbData): void
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

                // Skip ALL relation properties to preserve existing relation changes
                if ($this->isRelationProperty($entityClass, $property)) {
                    continue;
                }

                if ($reflection->hasProperty($property)) {
                    $reflectionProperty = $reflection->getProperty($property);

                    // Process the value according to its type
                    $entityMetadata = $this->metadataCache->getEntityMetadata($entityClass);
                    $value = $this->hydrator->processValue($entityMetadata, $property, $dbData[$column]);
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
     */
    private function isRelationProperty(string $entityClass, string $propertyName): bool
    {
        return $this->entityManager->getDbMapping()->isRelationProperty($entityClass, $propertyName);
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
                if (is_int($value) || is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }
}
