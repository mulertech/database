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
 * @package MulerTech\Database
 * @author Sébastien Muler
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
            return;
        }

        $this->processInsertOperation($manyToMany, $entity, $relatedEntity);
    }

    /**
     * Process delete operation
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
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
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
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
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return object|null
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
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     * @return object
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
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
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
     * @param object $entity
     * @param object $relatedEntity
     * @param MtManyToMany $manyToMany
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

        $collection = $this->getPropertyValue($entity, $property);

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
     * @param MtManyToMany $manyToMany
     * @return bool
     */
    private function validateJoinProperties(MtManyToMany $manyToMany): bool
    {
        return $manyToMany->joinProperty !== null && $manyToMany->inverseJoinProperty !== null;
    }

    /**
     * Build cache key
     * @param MtManyToMany $manyToMany
     * @param int|string $entityId
     * @param int|string $relatedEntityId
     * @return string
     */
    private function buildCacheKey(MtManyToMany $manyToMany, int|string $entityId, int|string $relatedEntityId): string
    {
        $mappedBy = $manyToMany->mappedBy ?? '';
        $joinProperty = $manyToMany->joinProperty ?? '';

        return sprintf(
            '%s_%s_%s_%s',
            $mappedBy,
            $joinProperty,
            $entityId,
            $relatedEntityId
        );
    }

    /**
     * Query existing link from database
     * @param MtManyToMany $manyToMany
     * @param int|string $entityId
     * @param int|string $relatedEntityId
     * @return object|null
     * @throws ReflectionException
     */
    private function queryExistingLink(MtManyToMany $manyToMany, int|string $entityId, int|string $relatedEntityId): ?object
    {
        /** @var class-string $linkEntityClass */
        $linkEntityClass = $manyToMany->mappedBy;

        $joinProperty = $manyToMany->joinProperty;
        $inverseJoinProperty = $manyToMany->inverseJoinProperty;

        if (is_null($joinProperty) || is_null($inverseJoinProperty)) {
            return null;
        }

        $linkMetadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($linkEntityClass);
        $joinColumn = $linkMetadata->getColumnName($joinProperty);
        $inverseJoinColumn = $linkMetadata->getColumnName($inverseJoinProperty);

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
     * @param object $linkEntity
     * @param MtManyToMany $manyToMany
     * @param object $entity
     * @param object $relatedEntity
     */
    private function setJoinProperties(
        object $linkEntity,
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity
    ): void {
        $joinProperty = $manyToMany->joinProperty;
        $inverseJoinProperty = $manyToMany->inverseJoinProperty;

        if (is_null($joinProperty) || is_null($inverseJoinProperty)) {
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
     * @param object $entity
     * @return int|string|null
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
            $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName);
            $mapping = $metadata->getRelationsByType('ManyToMany');
            $this->mappingCache[$entityName] = $mapping;
        }

        return $this->mappingCache[$entityName];
    }

    /**
     * Get property value using getter
     * @param object $entity
     * @param string $property
     * @return mixed
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $metadataRegistry = $this->entityManager->getMetadataRegistry();
        $metadata = $metadataRegistry->getEntityMetadata($entity::class);
        $getter = $metadata->getRequiredGetter($property);
        return $entity->$getter();
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->existingLinkCache = [];
        $this->mappingCache = [];
    }
}
