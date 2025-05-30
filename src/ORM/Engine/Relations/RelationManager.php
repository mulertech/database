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
        foreach ($this->stateManager->getScheduledInsertions() as $entity) {
            $this->processEntityRelations($entity);
        }

        foreach ($this->stateManager->getManagedEntities() as $entity) {
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
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    private function processEntityRelations(object $entity): void
    {
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
                if (is_object($relatedEntity) && $this->getId($relatedEntity) === null) {
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
            $entities = $entityReflection->getProperty($property)->getValue($entity);

            if ($entities instanceof DatabaseCollection) {
                $this->processDatabaseCollectionChanges($entity, $entities, $manyToMany);
            } elseif ($entities instanceof Collection && $entities->count() > 0) {
                $this->processNewCollectionRelations($entity, $entities, $manyToMany);
            }
        }
    }

    /**
     * @param object $entity
     * @param DatabaseCollection $entities
     * @param MtManyToMany $manyToMany
     * @return void
     */
    private function processDatabaseCollectionChanges(
        object $entity,
        DatabaseCollection $entities,
        MtManyToMany $manyToMany
    ): void {
        // Handle additions
        $addedEntities = $entities->getAddedEntities();
        if (!empty($addedEntities)) {
            foreach ($addedEntities as $relatedEntity) {
                $this->manyToManyInsertions[] = [
                    'entity' => $entity,
                    'related' => $relatedEntity,
                    'manyToMany' => $manyToMany,
                ];
            }
        }

        // Handle removals
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
     * @param Collection $entities
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
        foreach ($this->manyToManyInsertions as $key => $relation) {
            $entity = $relation['entity'];
            $relatedEntity = $relation['related'];
            $manyToMany = $relation['manyToMany'];
            $action = $relation['action'] ?? 'insert';

            $linkRelation = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);

            if ($linkRelation !== null) {
                if ($action === 'delete') {
                    $this->stateManager->scheduleForDeletion($linkRelation);
                }
                unset($this->manyToManyInsertions[$key]);
                continue;
            }

            // Create a new link entity
            $linkEntity = $this->createLinkEntity($manyToMany, $entity, $relatedEntity);

            if ($action === 'insert') {
                $this->stateManager->scheduleForInsertion($linkEntity);
            } else {
                $this->stateManager->scheduleForDeletion($linkEntity);
            }

            unset($this->manyToManyInsertions[$key]);
        }

        // Clear the list after processing
        $this->manyToManyInsertions = [];
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
