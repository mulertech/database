<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Manager for ManyToMany link entities
 */
class LinkEntityManager
{
    /**
     * @var array<string, object|null>
     */
    private array $existingLinkCache = [];

    /**
     * @var array<class-string, array<string, mixed>|false> Cache for ManyToMany mappings
     */
    private array $mappingCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager
    ) {
    }

    /**
     * Process a ManyToMany operation (insert or delete)
     * @param array{entity: object, related: object, manyToMany: MtManyToMany, action?: string} $operation
     * @throws ReflectionException
     */
    public function processOperation(array $operation): void
    {
        $entity = $operation['entity'];
        $relatedEntity = $operation['related'];
        $manyToMany = $operation['manyToMany'];
        $action = $operation['action'] ?? 'insert';

        if ($action === 'delete') {
            $this->processDeleteOperation($manyToMany, $entity, $relatedEntity);
        } else {
            $this->processInsertOperation($manyToMany, $entity, $relatedEntity);
        }
    }

    /**
     * Process delete operation
     * @throws ReflectionException
     */
    private function processDeleteOperation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): void {
        $this->scheduleExistingLinkForDeletion($manyToMany, $entity, $relatedEntity);
        $this->removeFromEntityCollection($entity, $relatedEntity, $manyToMany);
    }

    /**
     * Process insert operation
     * @throws ReflectionException
     */
    private function processInsertOperation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): void {
        $existingLink = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);

        if ($existingLink === null) {
            $linkEntity = $this->createLinkEntity($manyToMany, $entity, $relatedEntity);
            $this->stateManager->scheduleForInsertion($linkEntity);
        }
    }

    /**
     * Find existing link relation
     * @throws ReflectionException
     */
    public function findExistingLinkRelation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): ?object {
        $entityId = $this->getId($entity);
        $relatedEntityId = $this->getId($relatedEntity);

        if ($entityId === null || $relatedEntityId === null) {
            return null;
        }

        if (!$this->validateJoinProperties($manyToMany)) {
            return null;
        }

        $cacheKey = $this->buildCacheKey($manyToMany, $entityId, $relatedEntityId);

        if (isset($this->existingLinkCache[$cacheKey])) {
            return $this->existingLinkCache[$cacheKey];
        }

        $existingLink = $this->queryExistingLink($manyToMany, $entityId, $relatedEntityId);
        $this->existingLinkCache[$cacheKey] = $existingLink;

        return $existingLink;
    }

    /**
     * Create link entity
     */
    public function createLinkEntity(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): object {
        $entityId = $this->getId($entity);
        $relatedEntityId = $this->getId($relatedEntity);

        if ($entityId === null || $relatedEntityId === null) {
            throw new RuntimeException('Cannot create link entity without IDs');
        }

        if (!$this->validateJoinProperties($manyToMany)) {
            throw new RuntimeException('Cannot create link entity without join properties');
        }

        /** @var class-string $linkEntityClass */
        $linkEntityClass = $manyToMany->mappedBy;
        $linkEntity = new $linkEntityClass();

        $this->setJoinProperties($linkEntity, $manyToMany, $entity, $relatedEntity);

        return $linkEntity;
    }

    /**
     * Schedule existing link for deletion
     * @throws ReflectionException
     */
    private function scheduleExistingLinkForDeletion(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): void {
        $existingLink = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);

        if ($existingLink !== null) {
            $this->stateManager->scheduleForDeletion($existingLink);
        }
    }

    /**
     * Remove entity from collection
     * @throws ReflectionException
     */
    private function removeFromEntityCollection(
        object $entity,
        object $relatedEntity,
        MtManyToMany $manyToMany
    ): void {
        $entityReflection = new ReflectionClass($entity);
        $entityName = $entity::class;
        $manyToManyList = $this->getManyToManyMapping($entityName);

        if ($manyToManyList === false) {
            return;
        }

        foreach ($manyToManyList as $property => $mapping) {
            if ($mapping === $manyToMany && $entityReflection->hasProperty($property)) {
                $this->removeFromCollectionProperty($entity, $entityReflection, $property, $relatedEntity);
                break;
            }
        }
    }

    /**
     * Remove from specific collection property
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $property
     * @param object $relatedEntity
     * @throws ReflectionException
     */
    private function removeFromCollectionProperty(
        object $entity,
        ReflectionClass $entityReflection,
        string $property,
        object $relatedEntity
    ): void {
        $reflectionProperty = $entityReflection->getProperty($property);

        if (!$reflectionProperty->isInitialized($entity)) {
            return;
        }

        $collection = $reflectionProperty->getValue($entity);

        if (!$collection instanceof Collection) {
            return;
        }

        $items = $collection->items();
        foreach ($items as $key => $item) {
            if ($item === $relatedEntity) {
                $collection->remove($key);
                break;
            }
        }
    }

    /**
     * Validate join properties
     */
    private function validateJoinProperties(MtManyToMany $manyToMany): bool
    {
        return $manyToMany->joinProperty !== null && $manyToMany->inverseJoinProperty !== null;
    }

    /**
     * Build cache key
     */
    private function buildCacheKey(MtManyToMany $manyToMany, int|string $entityId, int|string $relatedEntityId): string
    {
        return sprintf(
            '%s_%s_%s_%s',
            $manyToMany->mappedBy,
            $manyToMany->joinProperty,
            $entityId,
            $relatedEntityId
        );
    }

    /**
     * Query existing link from database
     * @throws ReflectionException
     */
    private function queryExistingLink(MtManyToMany $manyToMany, int|string $entityId, int|string $relatedEntityId): ?object
    {
        /** @var class-string $linkEntityClass */
        $linkEntityClass = $manyToMany->mappedBy;

        $joinProperty = $manyToMany->joinProperty;
        $inverseJoinProperty = $manyToMany->inverseJoinProperty;

        if ($joinProperty === null || $inverseJoinProperty === null) {
            return null;
        }

        $joinColumn = $this->entityManager->getDbMapping()->getColumnName(
            $linkEntityClass,
            $joinProperty
        );
        $inverseJoinColumn = $this->entityManager->getDbMapping()->getColumnName(
            $linkEntityClass,
            $inverseJoinProperty
        );

        $where = sprintf(
            "%s = %s AND %s = %s",
            $joinColumn,
            is_numeric($entityId) ? (string) $entityId : "'" . $entityId . "'",
            $inverseJoinColumn,
            is_numeric($relatedEntityId) ? (string) $relatedEntityId : "'" . $relatedEntityId . "'"
        );

        return $this->entityManager->find($linkEntityClass, $where);
    }

    /**
     * Set join properties on link entity
     */
    private function setJoinProperties(
        object $linkEntity,
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): void {
        $joinProperty = $manyToMany->joinProperty;
        $inverseJoinProperty = $manyToMany->inverseJoinProperty;

        if ($joinProperty === null || $inverseJoinProperty === null) {
            return;
        }

        $joinPropertySetter = 'set' . ucfirst($joinProperty);
        $inverseJoinPropertySetter = 'set' . ucfirst($inverseJoinProperty);

        if (method_exists($linkEntity, $joinPropertySetter)) {
            $linkEntity->$joinPropertySetter($entity);
        }

        if (method_exists($linkEntity, $inverseJoinPropertySetter)) {
            $linkEntity->$inverseJoinPropertySetter($relatedEntity);
        }
    }

    /**
     * Get entity ID
     */
    private function getId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }

    /**
     * Get ManyToMany mapping for entity class
     * @param class-string $entityName
     * @return array<string, mixed>|false
     * @throws ReflectionException
     */
    private function getManyToManyMapping(string $entityName): array|false
    {
        if (!isset($this->mappingCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getManyToMany($entityName);
            $this->mappingCache[$entityName] = $mapping;
        }

        return $this->mappingCache[$entityName];
    }

    /**
     * Clear caches
     */
    public function clear(): void
    {
        $this->existingLinkCache = [];
        $this->mappingCache = [];
    }
}
