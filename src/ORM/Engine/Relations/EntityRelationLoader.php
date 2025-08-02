<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use Exception;
use MulerTech\Collections\Collection;
use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;
use PDO;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

readonly class EntityRelationLoader
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
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
        $metadata = $this->entityManager->getMetadataCache()->getEntityMetadata($entityName);

        if (!empty($entityOneToOne = $metadata->getRelationsByType('OneToOne'))) {
            foreach ($entityOneToOne as $property => $oneToOne) {
                if (!is_array($oneToOne)) {
                    continue;
                }
                /** @var array<string, mixed> $oneToOne */
                $result = $this->loadSingleRelation($entity, $oneToOne, $property, $entityData);
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityOneToMany = $metadata->getRelationsByType('OneToMany'))) {
            foreach ($entityOneToMany as $property => $oneToMany) {
                if (!is_array($oneToMany)) {
                    continue;
                }
                /** @var array<string, mixed> $oneToMany */
                $result = $this->loadOneToMany($entity, $oneToMany, $property);
                $entitiesToLoad[] = $result;
            }
        }

        if (!empty($entityManyToOne = $metadata->getRelationsByType('ManyToOne'))) {
            foreach ($entityManyToOne as $property => $manyToOne) {
                if (!is_array($manyToOne)) {
                    continue;
                }
                /** @var array<string, mixed> $manyToOne */
                $result = $this->loadSingleRelation($entity, $manyToOne, $property, $entityData);
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityManyToMany = $metadata->getRelationsByType('ManyToMany'))) {
            foreach ($entityManyToMany as $property => $manyToMany) {
                if (!is_array($manyToMany)) {
                    continue;
                }
                /** @var array<string, mixed> $manyToMany */
                $result = $this->loadManyToMany($entity, $manyToMany, $property);
                $entitiesToLoad[] = $result;
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
        $setter = 'set' . ucfirst($property);

        // Always reload relations for fresh data, don't check if already set
        // This is important for cases where foreign keys might have been updated

        $column = $this->getColumnName(get_class($entity), $property);
        $relatedEntity = null;

        if (isset($entityData[$column]) && method_exists($entity, $setter)) {
            /** @var class-string $targetEntity */
            $targetEntity = $this->getTargetEntity(get_class($entity), $relation, $property);

            try {
                $entityId = $entityData[$column];
                if (is_int($entityId) || is_string($entityId)) {
                    $foundEntity = $this->entityManager->find(
                        $targetEntity,
                        $entityId
                    );

                    // Only set if we actually found an entity
                    $relatedEntity = $foundEntity;
                    if ($foundEntity !== null || $this->setterAcceptsNull($entity, $setter)) {
                        $entity->$setter($relatedEntity);
                    }
                }
            } catch (Exception) {
                // If loading fails, only set to null if the setter accepts it
                if ($this->setterAcceptsNull($entity, $setter)) {
                    $entity->$setter(null);
                }
            }

            return $relatedEntity;
        }

        if (method_exists($entity, $setter) && $this->setterAcceptsNull($entity, $setter)) {
            $entity->$setter(null);
        }

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
        $mappedByProperty = $oneToMany['inverseJoinProperty'] ?? null;
        if (empty($mappedByProperty)) {
            throw new RuntimeException(sprintf(
                'The "mappedBy" attribute is not defined for the OneToMany relation "%s" on entity "%s".',
                $property,
                $entityClass
            ));
        }
        if (!is_string($mappedByProperty)) {
            throw new RuntimeException(sprintf(
                'The "mappedBy" attribute must be a string for the OneToMany relation "%s" on entity "%s".',
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
     * @param array<string, mixed> $relation
     * @param string $property
     * @return Collection<int, object>
     * @throws ReflectionException
     */
    private function loadManyToMany(object $entity, array $relation, string $property): Collection
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

        $relation['entity'] = get_class($entity);

        if (!empty($results)) {
            // Create managed entities from the results
            $entities = [];
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
                if (is_array($entityData)) {
                    $validatedEntityData = [];
                    foreach ($entityData as $key => $value) {
                        $stringKey = (string)$key;
                        // Accept only scalar or null values
                        if (is_scalar($value) || $value === null) {
                            $validatedEntityData[$stringKey] = $value;
                        } else {
                            $validatedEntityData[$stringKey] = null;
                        }
                    }
                    // Create new managed entity
                    $relatedEntity = $this->entityManager->getEmEngine()->createManagedEntity($validatedEntityData, $targetEntity, false);
                    $entities[] = $relatedEntity;
                }
            } // End of outer foreach loop

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
     * @throws Exception
     */
    private function getTableName(string $entityName): string
    {
        return $this->entityManager->getMetadataCache()->getTableName($entityName);
    }

    /**
     * @param class-string $entityName
     * @throws ReflectionException
     */
    private function getColumnName(string $entityName, string $property): string
    {
        $metadata = $this->entityManager->getMetadataCache()->getEntityMetadata($entityName);
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
     * @param string $sourceEntityClass
     * @param array<string, mixed> $relationData
     * @param string $propertyName
     * @return class-string
     */
    private function getTargetEntity(
        string $sourceEntityClass,
        array $relationData,
        string $propertyName
    ): string {
        $target = $relationData['targetEntity'] ?? '';
        if (!is_string($target) || $target === '' || !class_exists($target)) {
            $targetStr = is_string($target) ? $target : gettype($target);
            throw new RuntimeException(sprintf(
                'Target entity class "%s" for relation "%s" on entity "%s" does not exist.',
                $targetStr,
                $propertyName,
                $sourceEntityClass
            ));
        }
        return $target;
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $relation
     * @param string $property
     * @return string
     */
    private function getInverseJoinProperty(string $entity, array $relation, string $property): string
    {
        $inverseJoinProperty = $relation['inverseJoinProperty'] ?? null;
        if ($inverseJoinProperty === null || $inverseJoinProperty === '') {
            throw new RuntimeException(
                sprintf(
                    'Inverse join property is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }

        if (!is_string($inverseJoinProperty)) {
            throw new RuntimeException(
                sprintf(
                    'Inverse join property must be a string for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }
        return $inverseJoinProperty;
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $relation
     * @param string $property
     * @return class-string
     */
    private function getMappedBy(string $entity, array $relation, string $property): string
    {
        if (null === $mappedBy = $relation['mappedBy'] ?? null) {
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
     * @param array<string, mixed> $relation
     * @param string $property
     * @return string
     */
    private function getJoinProperty(string $entity, array $relation, string $property): string
    {
        $joinProperty = $relation['joinProperty'] ?? null;
        if ($joinProperty === null || $joinProperty === '') {
            throw new RuntimeException(
                sprintf(
                    'Join property is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }

        if (!is_string($joinProperty)) {
            throw new RuntimeException(
                sprintf(
                    'Join property must be a string for the class "%s" and property "%s". Please check the mapping configuration.',
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
}
