<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;

/**
 * Manager for ManyToMany link entities.
 *
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
        private readonly StateManagerInterface $stateManager,
    ) {
    }

    /**
     * Process a ManyToMany operation (insert or delete).
     *
     * @param array{entity: object, related: object, manyToMany: MtManyToMany, action?: string} $operation
     *
     * @throws \ReflectionException
     */
    public function processOperation(array $operation): void
    {
        $entity = $operation['entity'];
        $relatedEntity = $operation['related'];
        $manyToMany = $operation['manyToMany'];
        $action = $operation['action'] ?? 'insert';

        if ('delete' === $action) {
            $this->processDeleteOperation($manyToMany, $entity, $relatedEntity);

            return;
        }

        $this->processInsertOperation($manyToMany, $entity, $relatedEntity);
    }

    /**
     * Process delete operation.
     *
     * @throws \ReflectionException
     */
    private function processDeleteOperation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity,
    ): void {
        $this->scheduleExistingLinkForDeletion($manyToMany, $entity, $relatedEntity);
        $this->removeFromEntityCollection($entity, $relatedEntity, $manyToMany);
    }

    /**
     * Process insert operation.
     *
     * @throws \ReflectionException
     */
    private function processInsertOperation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity,
    ): void {
        $existingLink = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);

        if (null === $existingLink) {
            $linkEntity = $this->createLinkEntity($manyToMany, $entity, $relatedEntity);
            $this->stateManager->scheduleForInsertion($linkEntity);
        }
    }

    /**
     * Find existing link relation.
     *
     * @throws \ReflectionException
     */
    public function findExistingLinkRelation(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity,
    ): ?object {
        $entityId = $this->getId($entity);
        $relatedEntityId = $this->getId($relatedEntity);

        if (null === $entityId || null === $relatedEntityId) {
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
     * Create link entity.
     */
    public function createLinkEntity(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity,
    ): object {
        $entityId = $this->getId($entity);
        $relatedEntityId = $this->getId($relatedEntity);

        if (null === $entityId || null === $relatedEntityId) {
            throw new \RuntimeException('Cannot create link entity without IDs');
        }

        if (!$this->validateJoinProperties($manyToMany)) {
            throw new \RuntimeException('Cannot create link entity without join properties');
        }

        /** @var class-string $linkEntityClass */
        $linkEntityClass = $manyToMany->mappedBy;
        $linkEntity = new $linkEntityClass();

        $this->setJoinProperties($linkEntity, $manyToMany, $entity, $relatedEntity);

        return $linkEntity;
    }

    /**
     * Schedule existing link for deletion.
     *
     * @throws \ReflectionException
     */
    private function scheduleExistingLinkForDeletion(
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity,
    ): void {
        $existingLink = $this->findExistingLinkRelation($manyToMany, $entity, $relatedEntity);

        if (null !== $existingLink) {
            $this->stateManager->scheduleForDeletion($existingLink);
        }
    }

    /**
     * Remove entity from collection.
     *
     * @throws \ReflectionException
     */
    private function removeFromEntityCollection(
        object $entity,
        object $relatedEntity,
        MtManyToMany $manyToMany,
    ): void {
        $entityName = $entity::class;
        $manyToManyList = $this->getManyToManyMapping($entityName);

        if (false === $manyToManyList) {
            return;
        }

        $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName);

        foreach ($manyToManyList as $property => $mapping) {
            if ($mapping === $manyToMany && $this->hasProperty($metadata, $property)) {
                $this->removeFromCollectionProperty($entity, $property, $relatedEntity);
                break;
            }
        }
    }

    /**
     * Remove from specific collection property using try/catch approach instead of reflection.
     */
    private function removeFromCollectionProperty(
        object $entity,
        string $property,
        object $relatedEntity,
    ): void {
        try {
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
        } catch (\Error $e) {
            // Handle uninitialized property errors in PHP 7.4+
            if (str_contains($e->getMessage(), 'uninitialized')) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Check if entity has a property using metadata.
     */
    private function hasProperty(EntityMetadata $metadata, string $property): bool
    {
        return null !== $metadata->getGetter($property);
    }

    /**
     * Validate join properties.
     */
    private function validateJoinProperties(MtManyToMany $manyToMany): bool
    {
        return null !== $manyToMany->joinProperty && null !== $manyToMany->inverseJoinProperty;
    }

    /**
     * Build cache key.
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
     * Query existing link from database.
     *
     * @throws \ReflectionException
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
            '%s = %s AND %s = %s',
            $joinColumn,
            is_numeric($entityId) ? (string) $entityId : "'".$entityId."'",
            $inverseJoinColumn,
            is_numeric($relatedEntityId) ? (string) $relatedEntityId : "'".$relatedEntityId."'"
        );

        return $this->entityManager->find($linkEntityClass, $where);
    }

    /**
     * Set join properties on link entity.
     */
    private function setJoinProperties(
        object $linkEntity,
        MtManyToMany $manyToMany,
        object $entity,
        object $relatedEntity,
    ): void {
        $joinProperty = $manyToMany->joinProperty;
        $inverseJoinProperty = $manyToMany->inverseJoinProperty;

        if (is_null($joinProperty) || is_null($inverseJoinProperty)) {
            return;
        }

        $joinPropertySetter = 'set'.ucfirst($joinProperty);
        $inverseJoinPropertySetter = 'set'.ucfirst($inverseJoinProperty);

        if (method_exists($linkEntity, $joinPropertySetter)) {
            $linkEntity->$joinPropertySetter($entity);
        }

        if (method_exists($linkEntity, $inverseJoinPropertySetter)) {
            $linkEntity->$inverseJoinPropertySetter($relatedEntity);
        }
    }

    private function getId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }

    /**
     * Get ManyToMany mapping for entity class.
     *
     * @param class-string $entityName
     *
     * @return array<string, mixed>|false
     *
     * @throws \ReflectionException
     */
    private function getManyToManyMapping(string $entityName): array|false
    {
        if (!isset($this->mappingCache[$entityName])) {
            $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName);
            $mapping = $metadata->getManyToManyRelations();
            $this->mappingCache[$entityName] = $mapping ?: false;
        }

        return $this->mappingCache[$entityName];
    }

    /**
     * Get property value using getter.
     *
     * @throws \ReflectionException
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $metadataRegistry = $this->entityManager->getMetadataRegistry();
        $metadata = $metadataRegistry->getEntityMetadata($entity::class);
        $getter = $metadata->getRequiredGetter($property);

        return $entity->$getter();
    }

    public function clear(): void
    {
        $this->existingLinkCache = [];
        $this->mappingCache = [];
    }
}
