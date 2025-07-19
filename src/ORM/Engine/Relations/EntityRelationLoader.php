<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use Exception;
use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;
use PDO;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class EntityRelationLoader
{
    private DbMappingInterface $dbMapping;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->dbMapping = $entityManager->getDbMapping();
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

        if (!empty($entityOneToOne = $this->dbMapping->getOneToOne($entityName))) {
            foreach ($entityOneToOne as $property => $oneToOne) {
                // Validate that oneToOne is the correct type
                if ($oneToOne instanceof MtOneToOne) {
                    $result = $this->loadSingleRelation($entity, $oneToOne, $property, $entityData);
                    if ($result !== null) {
                        $entitiesToLoad[] = $result;
                    }
                }
            }
        }

        if (!empty($entityOneToMany = $this->dbMapping->getOneToMany($entityName))) {
            foreach ($entityOneToMany as $property => $oneToMany) {
                // Validate that oneToMany is the correct type
                if ($oneToMany instanceof MtOneToMany) {
                    $result = $this->loadOneToMany($entity, $oneToMany, $property);
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityManyToOne = $this->dbMapping->getManyToOne($entityName))) {
            foreach ($entityManyToOne as $property => $manyToOne) {
                // Validate that manyToOne is the correct type
                if ($manyToOne instanceof MtManyToOne) {
                    $result = $this->loadSingleRelation($entity, $manyToOne, $property, $entityData);
                    if ($result !== null) {
                        $entitiesToLoad[] = $result;
                    }
                }
            }
        }

        if (!empty($entityManyToMany = $this->dbMapping->getManyToMany($entityName))) {
            foreach ($entityManyToMany as $property => $manyToMany) {
                // Validate that manyToMany is the correct type
                if ($manyToMany instanceof MtManyToMany) {
                    $result = $this->loadManyToMany($entity, $manyToMany, $property);
                    $entitiesToLoad[] = $result;
                }
            }
        }

        return $entitiesToLoad;
    }

    /**
     * @param object $entity
     * @param MtOneToOne|MtManyToOne $relation
     * @param string $property
     * @param array<string, mixed> $entityData
     * @return object|null
     * @throws ReflectionException
     */
    private function loadSingleRelation(
        object $entity,
        MtOneToOne|MtManyToOne $relation,
        string $property,
        array $entityData
    ): ?object {
        $setter = 'set' . ucfirst($property);

        // Always reload relations for fresh data, don't check if already set
        // This is important for cases where foreign keys might have been updated

        $column = $this->getColumnName(get_class($entity), $property);
        $relatedEntity = null;

        if (isset($entityData[$column]) && method_exists($entity, $setter)) {
            /** @var class-string $targetEntity */
            $targetEntity = $this->getTargetEntity(get_class($entity), $relation, $property);

            try {
                $foundEntity = $this->entityManager->find(
                    $targetEntity,
                    $entityData[$column]
                );

                // Only set if we actually found an entity
                if ($foundEntity !== null) {
                    $relatedEntity = $foundEntity;
                    $entity->$setter($relatedEntity);
                } elseif ($this->setterAcceptsNull($entity, $setter)) {
                    $entity->$setter(null);
                }
            } catch (Exception) {
                // If loading fails, only set to null if the setter accepts it
                if ($this->setterAcceptsNull($entity, $setter)) {
                    $entity->$setter(null);
                }
            }
        } elseif (method_exists($entity, $setter) && $this->setterAcceptsNull($entity, $setter)) {
            $entity->$setter(null);
        }

        return $relatedEntity;
    }

    /**
     * @param object $entity
     * @param MtOneToMany $oneToMany
     * @param string $property
     * @return Collection<int, object>
     * @throws ReflectionException
     */
    private function loadOneToMany(object $entity, MtOneToMany $oneToMany, string $property): Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $setter = 'set' . ucfirst($property);
        if ($entityId === null) {
            // If entity has no ID, it cannot have related OneToMany entities from DB
            // Set an empty collection if setter exists
            if (method_exists($entity, $setter)) {
                // Ensure type compatibility, assuming setter accepts Collection
                $entity->{$setter}(new DatabaseCollection());
            }
            return new DatabaseCollection();
        }

        /** @var class-string $entityClass */
        $entityClass = get_class($entity);
        /** @var class-string $targetEntity */
        // Get target entity class from the relation attribute
        $targetEntity = $this->getTargetEntity($entityClass, $oneToMany, $property);

        // 'mappedBy' on MtOneToMany is the property name on the target entity
        $mappedByProperty = $oneToMany->inverseJoinProperty;
        if (empty($mappedByProperty)) {
            throw new RuntimeException(sprintf(
                'The "mappedBy" attribute is not defined for the OneToMany relation "%s" on entity "%s".',
                $property,
                $entityClass
            ));
        }
        $mappedByColumn = $this->getColumnName($targetEntity, $mappedByProperty);

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->select('*')
            ->from($this->getTableName($targetEntity))
            ->where($mappedByColumn, $entityId);

        $result = $this->entityManager->getEmEngine()->getQueryBuilderListResult($queryBuilder, $targetEntity);

        $collection = new DatabaseCollection(); // Default to empty collection
        if ($result !== null) {
            $collection = new DatabaseCollection($result);
        }

        if (method_exists($entity, $setter)) {
            $entity->{$setter}($collection);
        }

        return $collection;
    }

    /**
     * @param object $entity
     * @param MtManyToMany $relation
     * @param string $property
     * @return Collection<int, object>
     * @throws ReflectionException
     */
    private function loadManyToMany(object $entity, MtManyToMany $relation, string $property): Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        $setter = 'set' . ucfirst($property);
        if ($entityId === null) {
            // Set empty collection if no ID
            if (method_exists($entity, $setter)) {
                $collection = new DatabaseCollection();
                $collection->synchronizeInitialState(); // Important: synchronize empty state
                $entity->{$setter}($collection);
            }
            return new DatabaseCollection();
        }

        // For ManyToMany: ALWAYS reload from database
        // to ensure state reflects changes

        /** @var class-string $mappedBy */
        $mappedBy = $this->getMappedBy(get_class($entity), $relation, $property);
        $pivotTable = $this->getTableName($mappedBy);
        /** @var class-string $targetEntity */
        $targetEntity = $this->getTargetEntity(get_class($entity), $relation, $property);
        $targetTable = $this->getTableName($targetEntity);

        $joinColumn = $this->getColumnName(
            $mappedBy,
            $this->getJoinProperty(get_class($entity), $relation, $property)
        );
        $inverseJoinColumn = $this->getColumnName(
            $mappedBy,
            $this->getInverseJoinProperty(get_class($entity), $relation, $property)
        );

        // Use a direct PDO query to ensure we get fresh data
        $pdo = $this->entityManager->getPdm();
        $sql = "SELECT t.* FROM `$targetTable` t 
                INNER JOIN `$pivotTable` p ON t.id = p.`$inverseJoinColumn` 
                WHERE p.`$joinColumn` = ?";

        $statement = $pdo->prepare($sql);
        $statement->execute([$entityId]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $relation->entity = get_class($entity);

        if (!empty($results)) {
            // Create managed entities from the results
            $entities = [];
            foreach ($results as $entityData) {
                // Check if entity is already in identity map
                if (isset($entityData['id'])) {
                    $managedEntity = $this->entityManager->getEmEngine()->getIdentityMap()->get($targetEntity, $entityData['id']);
                    if ($managedEntity !== null) {
                        $entities[] = $managedEntity;
                        continue;
                    }
                }

                // Create new managed entity
                $relatedEntity = $this->entityManager->getEmEngine()->createManagedEntity($entityData, $targetEntity, false);
                $entities[] = $relatedEntity;
            }

            $collection = new DatabaseCollection($entities);
        } else {
            $collection = new DatabaseCollection([]);
        }

        // IMPORTANT: Synchronize the initial state after loading from database
        $collection->synchronizeInitialState();

        // Set the collection on the entity
        if (method_exists($entity, $setter)) {
            $entity->$setter($collection);
        }

        return $collection;
    }

    /**
     * @param class-string $entityName
     * @throws ReflectionException
     */
    private function getTableName(string $entityName): string
    {
        if (null === $tableName = $this->dbMapping->getTableName($entityName)) {
            throw new RuntimeException(
                sprintf(
                    'Table name is not defined for the class "%s". Please check the mapping configuration.',
                    $entityName
                )
            );
        }

        return $tableName;
    }

    /**
     * @param class-string $entityName
     * @throws ReflectionException
     */
    private function getColumnName(string $entityName, string $property): string
    {
        if (null === $columnName = $this->dbMapping->getColumnName($entityName, $property)) {
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
     * @param string $sourceEntityClass
     * @param MtOneToOne|MtManyToOne|MtOneToMany|MtManyToMany $relationAttribute
     * @param string $propertyName
     * @return class-string
     */
    private function getTargetEntity(
        string $sourceEntityClass, // Changed signature to match usage
        MtOneToOne|MtManyToOne|MtOneToMany|MtManyToMany $relationAttribute, // Changed signature
        string $propertyName // Changed signature
    ): string {
        /** @var class-string $target */
        $target = $relationAttribute->targetEntity;
        if (!class_exists($target)) {
            throw new RuntimeException(sprintf(
                'Target entity class "%s" for relation "%s" on entity "%s" does not exist.',
                $target,
                $propertyName,
                $sourceEntityClass
            ));
        }
        return $target;
    }

    /**
     * @param class-string $entity
     * @param MtOneToMany|MtManyToMany $relation
     * @param string $property
     * @return string
     */
    private function getInverseJoinProperty(string $entity, MtOneToMany|MtManyToMany $relation, string $property): string
    {
        if (null === $inverseJoinProperty = $relation->inverseJoinProperty) {
            throw new RuntimeException(
                sprintf(
                    'Inverse join property is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }

        return $inverseJoinProperty;
    }

    /**
     * @param class-string $entity
     * @param MtManyToMany $relation
     * @param string $property
     * @return class-string
     */
    private function getMappedBy(string $entity, MtManyToMany $relation, string $property): string
    {
        if (null === $mappedBy = $relation->mappedBy) {
            throw new RuntimeException(
                sprintf(
                    'Mapped by property is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }

        /** @var class-string $mappedBy */
        return $mappedBy;
    }

    /**
     * @param class-string $entity
     * @param MtManyToMany $relation
     * @param string $property
     * @return string
     */
    private function getJoinProperty(string $entity, MtManyToMany $relation, string $property): string
    {
        if (null === $joinProperty = $relation->joinProperty) {
            throw new RuntimeException(
                sprintf(
                    'Join property is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }

        return $joinProperty;
    }

    /**
     * Check if a setter method accepts null values
     *
     * @param object $entity
     * @param string $setterMethod
     * @return bool
     */
    private function setterAcceptsNull(object $entity, string $setterMethod): bool
    {
        $reflection = new ReflectionClass($entity);

        if (!$reflection->hasMethod($setterMethod)) {
            return false;
        }

        $method = $reflection->getMethod($setterMethod);
        $parameters = $method->getParameters();

        if (empty($parameters)) {
            return false;
        }

        $firstParameter = $parameters[0];
        $type = $firstParameter->getType();

        // If no type hint, assume it accepts null
        if ($type === null) {
            return true;
        }

        // Check if the parameter allows null
        return $type->allowsNull();
    }

    /**
     * @param class-string $entityClass
     * @param int|string $idOrWhere
     * @return object|null
     */
    private function loadRelatedEntity(string $entityClass, mixed $idOrWhere): ?object
    {
        // Validate that idOrWhere is the correct type
        if (!is_int($idOrWhere) && !is_string($idOrWhere)) {
            return null;
        }

        try {
            return $this->entityManager->find($entityClass, $idOrWhere);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param class-string $entityClass
     * @param array<int, array<string, mixed>> $entitiesData
     * @return array<int, object>
     */
    private function loadCollectionEntities(string $entityClass, array $entitiesData): array
    {
        $entities = [];

        foreach ($entitiesData as $entityData) {
            // Validate that entityData is an array and has an id
            if (!is_array($entityData) || !isset($entityData['id'])) {
                continue;
            }

            $entityId = $entityData['id'];
            if (!is_int($entityId) && !is_string($entityId)) {
                continue;
            }

            // Check if entity is already in identity map
            $managed = $this->entityManager->getEmEngine()->getIdentityMap()->get($entityClass, $entityId);
            if ($managed !== null) {
                $entities[] = $managed;
                continue;
            }

            // Create and manage new entity
            if (is_array($entityData)) {
                $entity = $this->entityManager->getEmEngine()->createManagedEntity($entityData, $entityClass, false);
                $entities[] = $entity;
            }
        }

        return $entities;
    }
}
