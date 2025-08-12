<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use Exception;
use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;
use PDO;
use ReflectionException;
use RuntimeException;

/**
 * Loader for ORM entity relations
 *
 * This class handles loading related entities based on the defined relations
 * in the entity metadata. It supports OneToOne, OneToMany, ManyToOne, and
 * ManyToMany relations.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class EntityRelationLoader
{
    private RelationValidator $validator;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->validator = new RelationValidator();
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $entityData
     * @return array<int, object|Collection<int, object>|null>
     * @throws ReflectionException
     */
    public function loadRelations(object $entity, array $entityData): array
    {
        /** @var class-string $entityName */
        $entityName = get_class($entity);
        $entitiesToLoad = [];
        $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName);

        if (!empty($entityOneToOne = $metadata->getRelationsByType('OneToOne'))) {
            foreach ($entityOneToOne as $property => $oneToOne) {
                if (!($oneToOne instanceof MtOneToOne)) {
                    continue;
                }
                // Convert Mt object to array format for existing methods
                $oneToOneArray = $this->convertOneToOneToArray($oneToOne);
                $result = $this->loadSingleRelation($entity, $oneToOneArray, $property, $entityData);
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityOneToMany = $metadata->getRelationsByType('OneToMany'))) {
            foreach ($entityOneToMany as $property => $oneToMany) {
                if (!($oneToMany instanceof MtOneToMany)) {
                    continue;
                }
                // Convert Mt object to array format for existing methods
                $oneToManyArray = $this->convertOneToManyToArray($oneToMany);
                $result = $this->loadOneToMany($entity, $oneToManyArray, $property);
                if (count($result) > 0) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityManyToOne = $metadata->getRelationsByType('ManyToOne'))) {
            foreach ($entityManyToOne as $property => $manyToOne) {
                if (!($manyToOne instanceof MtManyToOne)) {
                    continue;
                }
                // Convert Mt object to array format for existing methods
                $manyToOneArray = $this->convertManyToOneToArray($manyToOne);
                $result = $this->loadSingleRelation($entity, $manyToOneArray, $property, $entityData);
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityManyToMany = $metadata->getRelationsByType('ManyToMany'))) {
            foreach ($entityManyToMany as $property => $manyToMany) {
                if (!($manyToMany instanceof MtManyToMany)) {
                    continue;
                }
                // Convert Mt object to array format for existing methods
                $manyToManyArray = $this->convertManyToManyToArray($manyToMany);
                $result = $this->loadManyToMany($entity, $manyToManyArray, $property);
                if (count($result) > 0) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        return $entitiesToLoad;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $relation
     * @param string $property
     * @param array<string, mixed> $entityData
     * @return object|null
     * @throws ReflectionException
     */
    private function loadSingleRelation(
        object $entity,
        array $relation,
        string $property,
        array $entityData
    ): ?object {
        // Always reload relations for fresh data, don't check if already set
        // This is important for cases where foreign keys might have been updated

        $column = $this->getColumnName(get_class($entity), $property);
        $relatedEntity = null;

        if (isset($entityData[$column])) {
            /** @var class-string $targetEntity */
            $targetEntity = $this->validator->validateTargetEntity($relation, get_class($entity), $property);

            try {
                $entityId = $entityData[$column];
                if (is_int($entityId) || is_string($entityId)) {
                    $foundEntity = $this->entityManager->find(
                        $targetEntity,
                        $entityId
                    );

                    // Only set if we actually found an entity
                    $relatedEntity = $foundEntity;
                    $this->validator->setRelationValue($entity, $property, $relatedEntity);
                }
            } catch (Exception) {
                // If loading fails, try to set to null
                $this->validator->setRelationValue($entity, $property, null);
            }

            return $relatedEntity;
        }

        // Try to set null if no data found
        $this->validator->setRelationValue($entity, $property, null);

        return null;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $oneToMany
     * @param string $property
     * @return Collection<int, object>
     * @throws ReflectionException
     */
    private function loadOneToMany(object $entity, array $oneToMany, string $property): Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($entityId === null) {
            // If entity has no ID, it cannot have related OneToMany entities from DB
            // Set an empty collection if setter exists
            $this->validator->setRelationValue($entity, $property, new DatabaseCollection());
            return new DatabaseCollection();
        }

        /** @var class-string $entityClass */
        $entityClass = get_class($entity);
        /** @var class-string $targetEntity */
        // Get target entity class from the relation attribute
        $targetEntity = $this->validator->validateTargetEntity($oneToMany, $entityClass, $property);

        // 'mappedBy' on MtOneToMany is the property name on the target entity
        $mappedByProperty = $this->validator->validateInverseJoinProperty($oneToMany, $entityClass, $property);
        $mappedByColumn = $this->getColumnName($targetEntity, $mappedByProperty);

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->select('*')
            ->from($this->getTableName($targetEntity))
            ->where($mappedByColumn, $entityId);

        $result = $this->entityManager->getEmEngine()->getQueryBuilderListResult($queryBuilder, $targetEntity);

        $collection = new DatabaseCollection();
        if ($result !== null) {
            $collection = new DatabaseCollection($result);
        }

        $this->validator->setRelationValue($entity, $property, $collection);

        return $collection;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $relation
     * @param string $property
     * @return Collection<int, object>
     * @throws ReflectionException
     */
    private function loadManyToMany(object $entity, array $relation, string $property): Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($entityId === null) {
            // Set empty collection if no ID
            $collection = new DatabaseCollection();
            $collection->synchronizeInitialState();
            $this->validator->setRelationValue($entity, $property, $collection);
            return new DatabaseCollection();
        }

        // For ManyToMany: ALWAYS reload from database
        // to ensure state reflects changes

        /** @var class-string $mappedBy */
        $mappedBy = $this->validator->validateMappedBy($relation, get_class($entity), $property);
        $pivotTable = $this->getTableName($mappedBy);
        /** @var class-string $targetEntity */
        $targetEntity = $this->validator->validateTargetEntity($relation, get_class($entity), $property);
        $targetTable = $this->getTableName($targetEntity);

        $joinProperty = $this->validator->validateRelationProperty(
            $relation['joinProperty'] ?? null,
            'join property',
            get_class($entity),
            $property
        );
        $inverseJoinProperty = $this->validator->validateRelationProperty(
            $relation['inverseJoinProperty'] ?? null,
            'inverse join property',
            get_class($entity),
            $property
        );

        $joinColumn = $this->getColumnName($mappedBy, $joinProperty);
        $inverseJoinColumn = $this->getColumnName($mappedBy, $inverseJoinProperty);

        // Use a direct PDO query to ensure we get fresh data
        $pdo = $this->entityManager->getPdm();
        $sql = "SELECT t.* FROM `$targetTable` t 
                INNER JOIN `$pivotTable` p ON t.id = p.`$inverseJoinColumn` 
                WHERE p.`$joinColumn` = ?";

        $statement = $pdo->prepare($sql);
        $statement->execute([$entityId]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $entities = [];
        if (!empty($results)) {
            // Create managed entities from the results
            foreach ($results as $entityData) {
                // Check if entity is already in identity map
                if (is_array($entityData) && isset($entityData['id'])) {
                    $entityId = $entityData['id'];
                    if (is_int($entityId) || is_string($entityId)) {
                        $managedEntity = $this->entityManager->getEmEngine()->getIdentityMap()->get($targetEntity, $entityId);
                        if ($managedEntity !== null) {
                            $entities[] = $managedEntity;
                            continue;
                        }
                    }
                }

                // Ensure entityData has string keys before creating managed entity
                $validatedEntityData = $this->validator->validateEntityData($entityData);
                if (!empty($validatedEntityData)) {
                    // Create new managed entity
                    $relatedEntity = $this->entityManager->getEmEngine()->createManagedEntity($validatedEntityData, $targetEntity, false);
                    $entities[] = $relatedEntity;
                }
            }
        }

        $collection = new DatabaseCollection($entities);

        // IMPORTANT: Synchronize the initial state after loading from database
        $collection->synchronizeInitialState();

        // Set the collection on the entity
        $this->validator->setRelationValue($entity, $property, $collection);

        return $collection;
    }

    /**
     * @param class-string $entityName
     * @throws ReflectionException
     * @throws Exception
     */
    private function getTableName(string $entityName): string
    {
        return $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName)->tableName;
    }

    /**
     * @param class-string $entityName
     * @throws ReflectionException
     */
    private function getColumnName(string $entityName, string $property): string
    {
        $metadata = $this->entityManager->getMetadataRegistry()->getEntityMetadata($entityName);
        if (null === $columnName = $metadata->getColumnName($property)) {
            throw new RuntimeException(
                sprintf(
                    'Column name is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entityName,
                    $property
                )
            );
        }

        return $columnName;
    }

    /**
     * Convert MtOneToOne object to array format expected by existing relation loading methods
     * @param MtOneToOne $relation
     * @return array<string, mixed>
     */
    private function convertOneToOneToArray(MtOneToOne $relation): array
    {
        return [
            'targetEntity' => $relation->targetEntity,
        ];
    }

    /**
     * Convert MtOneToMany object to array format expected by existing relation loading methods
     * @param MtOneToMany $relation
     * @return array<string, mixed>
     */
    private function convertOneToManyToArray(MtOneToMany $relation): array
    {
        return [
            'targetEntity' => $relation->targetEntity,
            'inverseJoinProperty' => $relation->inverseJoinProperty,
        ];
    }

    /**
     * Convert MtManyToOne object to array format expected by existing relation loading methods
     * @param MtManyToOne $relation
     * @return array<string, mixed>
     */
    private function convertManyToOneToArray(MtManyToOne $relation): array
    {
        return [
            'targetEntity' => $relation->targetEntity,
        ];
    }

    /**
     * Convert MtManyToMany object to array format expected by existing relation loading methods
     * @param MtManyToMany $relation
     * @return array<string, mixed>
     */
    private function convertManyToManyToArray(MtManyToMany $relation): array
    {
        return [
            'targetEntity' => $relation->targetEntity,
            'mappedBy' => $relation->mappedBy,
            'joinProperty' => $relation->joinProperty,
            'inverseJoinProperty' => $relation->inverseJoinProperty,
        ];
    }
}
