<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use DateTimeImmutable;
use Exception;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Engine\Persistence\DeletionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\InsertionProcessor;
use MulerTech\Database\ORM\Engine\Persistence\PersistenceManager;
use MulerTech\Database\ORM\Engine\Persistence\UpdateProcessor;
use MulerTech\Database\ORM\Engine\Relations\RelationManager;
use MulerTech\Database\ORM\State\DirectStateManager;
use MulerTech\Database\ORM\State\EntityLifecycleState;
use MulerTech\Database\ORM\State\StateManagerInterface;
use MulerTech\Database\ORM\State\StateTransitionManager;
use MulerTech\Database\ORM\State\StateValidator;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;
use PDO;
use ReflectionException;

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
     * @param MetadataRegistry $metadataRegistry
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MetadataRegistry $metadataRegistry
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
        // Check if the string contains SQL operators, suggesting it's a WHERE clause
        if (is_string($idOrWhere) && !is_numeric($idOrWhere) && $this->looksLikeWhereClause($idOrWhere)) {
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
                $entityData[$stringKey] = is_scalar($value) ? $value : null;
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
                    $validatedEntityData[$stringKey] = is_scalar($value) ? $value : null;
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

        // Add to identity map if not already there with proper metadata
        if (!$isManaged) {
            $this->identityMap->add($entity);

            // Store the original data for change detection
            $originalData = $this->changeDetector->extractCurrentData($entity);
            $metadata = new EntityState(
                $entity::class,
                EntityLifecycleState::NEW,
                $originalData,
                new DateTimeImmutable()
            );
            $this->identityMap->updateMetadata($entity, $metadata);
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
        $this->identityMap = new IdentityMap($this->metadataRegistry);
        $this->entityRegistry = new EntityRegistry();
        $this->changeDetector = new ChangeDetector($this->metadataRegistry);

        // ChangeSetManager needs core components
        $this->changeSetManager = new ChangeSetManager(
            $this->identityMap,
            $this->entityRegistry,
            $this->changeDetector,
            $this->metadataRegistry
        );

        // EntityHydrator uses MetadataRegistry
        $this->hydrator = new EntityHydrator($this->metadataRegistry);
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
            $instance = new StateTransitionManager($this->identityMap);
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
            $instance = new InsertionProcessor($this->entityManager, $this->entityManager->getMetadataRegistry());
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
            $instance = new UpdateProcessor($this->entityManager, $this->entityManager->getMetadataRegistry());
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
            $instance = new DeletionProcessor($this->entityManager, $this->entityManager->getMetadataRegistry());
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
        return $this->metadataRegistry->getEntityMetadata($entityName)->tableName;
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
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            return false;
        }

        $changeSet = $this->changeDetector->computeChangeSet($entity, $metadata->originalData);
        return !$changeSet->isEmpty();
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
            $entityMetadata = $this->metadataRegistry->getEntityMetadata($entityClass);
            $propertiesColumns = $entityMetadata->getPropertiesColumns();

            foreach ($propertiesColumns as $property => $column) {
                if (!isset($dbData[$column])) {
                    continue;
                }

                // Skip ALL relation properties to preserve existing relation changes
                if ($this->isRelationProperty($entityClass, $property)) {
                    continue;
                }

                $setterMethod = $entityMetadata->getSetter($property);
                if ($setterMethod !== null && method_exists($entity, $setterMethod)) {
                    // Process the value according to its type
                    $value = $this->hydrator->processValue($entityMetadata, $property, $dbData[$column]);
                    $entity->$setterMethod($value);
                }
            }

            // Update metadata with fresh data
            $metadata = $this->identityMap->getMetadata($entity);
            if ($metadata !== null) {
                $currentData = $this->changeDetector->extractCurrentData($entity);
                $newMetadata = new EntityState(
                    $metadata->className,
                    EntityLifecycleState::MANAGED,
                    $currentData,
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
        return $this->metadataRegistry->getEntityMetadata($entityClass)->hasRelation($propertyName);
    }

    /**
     * Determine if a string looks like a WHERE clause rather than an ID
     * @param string $value
     * @return bool
     */
    private function looksLikeWhereClause(string $value): bool
    {
        // Additional safety: if it's just alphanumeric characters with dashes/underscores,
        // it's likely an ID value, not a WHERE clause
        if (preg_match('/^[\w\-]+$/', $value)) {
            return false;
        }

        // Check for common SQL operators and keywords that indicate a WHERE clause
        // Use word boundaries and more precise matching to avoid false positives
        $patterns = [
            '/\s*=\s*/',           // Equals
            '/\s*[><=!]+\s*/',     // Comparison operators
            '/\bLIKE\b/i',         // LIKE (word boundary)
            '/\bIN\b/i',           // IN (word boundary)
            '/\bBETWEEN\b/i',      // BETWEEN (word boundary)
            '/\bIS\s+NULL\b/i',    // IS NULL (word boundary)
            '/\bIS\s+NOT\s+NULL\b/i', // IS NOT NULL (word boundary)
            '/\bAND\b/i',          // AND (word boundary)
            '/\bOR\b/i',           // OR (word boundary)
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param object $entity
     * @return int|string|null
     */
    private function extractEntityId(object $entity): int|string|null
    {
        $entityClass = $entity::class;
        $entityMetadata = $this->metadataRegistry->getEntityMetadata($entityClass);

        foreach (['id', 'uuid', 'identifier'] as $property) {
            $getterMethod = $entityMetadata->getGetter($property);
            if ($getterMethod !== null && method_exists($entity, $getterMethod)) {
                $id = $entity->$getterMethod();
                if (is_int($id) || is_string($id)) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isManaged(object $entity): bool
    {
        return $this->getStateManager()->isManaged($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isNew(object $entity): bool
    {
        return $this->getStateManager()->isNew($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isRemoved(object $entity): bool
    {
        return $this->getStateManager()->isRemoved($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isDetached(object $entity): bool
    {
        return $this->getStateManager()->isDetached($entity);
    }

    /**
     * @param object $entity
     * @return object
     */
    public function merge(object $entity): object
    {
        $entityId = $this->extractEntityId($entity);

        if ($entityId !== null) {
            $entityClass = $entity::class;
            $managed = $this->identityMap->get($entityClass, $entityId);

            if ($managed !== null) {
                return $managed;
            }
        }

        $this->identityMap->add($entity);
        $this->getStateManager()->manage($entity);

        return $entity;
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function refresh(object $entity): void
    {
        $entityId = $this->extractEntityId($entity);
        if ($entityId === null) {
            return;
        }

        $entityClass = $entity::class;
        $freshEntity = $this->find($entityClass, $entityId);

        if ($freshEntity !== null) {
            // Update the entity's state with fresh data
            $metadata = $this->identityMap->getMetadata($entity);
            if ($metadata !== null) {
                $currentData = $this->changeDetector->extractCurrentData($freshEntity);
                $newMetadata = new EntityState(
                    $metadata->className,
                    EntityLifecycleState::MANAGED,
                    $currentData,
                    new DateTimeImmutable()
                );
                $this->identityMap->updateMetadata($entity, $newMetadata);
            }
        }
    }

    /**
     * @return array<object>
     */
    public function getScheduledInsertions(): array
    {
        return $this->getStateManager()->getScheduledInsertions();
    }

    /**
     * @return array<object>
     */
    public function getScheduledUpdates(): array
    {
        return $this->getStateManager()->getScheduledUpdates();
    }

    /**
     * @return array<object>
     */
    public function getScheduledDeletions(): array
    {
        return $this->getStateManager()->getScheduledDeletions();
    }

    /**
     * @param object $entity
     * @return ChangeSet|null
     */
    public function getChangeSet(object $entity): ?ChangeSet
    {
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            return null;
        }

        $changeSet = $this->changeDetector->computeChangeSet($entity, $metadata->originalData);
        return $changeSet->isEmpty() ? null : $changeSet;
    }

    /**
     * @return void
     */
    public function computeChangeSets(): void
    {
        $this->changeSetManager->computeChangeSets();
    }
}
