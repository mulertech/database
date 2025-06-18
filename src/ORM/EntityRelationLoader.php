<?php

namespace MulerTech\Database\ORM;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtManyToOne;
use MulerTech\Database\Mapping\MtOneToMany;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use ReflectionException;
use RuntimeException;

class EntityRelationLoader
{
    private DbMappingInterface $dbMapping;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->dbMapping = $entityManager->getDbMapping();
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $entityData
     * @return array<int, object|Collection<int, object>|null>
     */
    public function loadRelations(object $entity, array $entityData): array
    {
        /** @var class-string $entityName */
        $entityName = get_class($entity);
        $entitiesToLoad = [];

        if (!empty($entityOneToOne = $this->dbMapping->getOneToOne($entityName))) {
            foreach ($entityOneToOne as $property => $oneToOne) {
                $result = $this->loadSingleRelation($entity, $oneToOne, $property, $entityData);
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityOneToMany = $this->dbMapping->getOneToMany($entityName))) {
            foreach ($entityOneToMany as $property => $oneToMany) {
                $result = $this->loadOneToMany($entity, $oneToMany, $property);
                $entitiesToLoad[] = $result;
            }
        }

        if (!empty($entityManyToOne = $this->dbMapping->getManyToOne($entityName))) {
            foreach ($entityManyToOne as $property => $manyToOne) {
                $result = $this->loadSingleRelation($entity, $manyToOne, $property, $entityData);
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
            }
        }

        if (!empty($entityManyToMany = $this->dbMapping->getManyToMany($entityName))) {
            foreach ($entityManyToMany as $property => $manyToMany) {
                $result = $this->loadManyToMany($entity, $manyToMany, $property);
                $entitiesToLoad[] = $result;
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
     */
    private function loadSingleRelation(
        object $entity,
        MtOneToOne|MtManyToOne $relation,
        string $property,
        array $entityData
    ): ?object {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        // Always reload relations for fresh data, don't check if already set
        // This is important for cases where foreign keys might have been updated

        $column = $this->getColumnName(get_class($entity), $property);
        $relatedEntity = null;

        if (isset($entityData[$column]) && $entityData[$column] !== null && method_exists($entity, $setter)) {
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
                } else {
                    // Only set to null if the setter accepts null values
                    if ($this->setterAcceptsNull($entity, $setter)) {
                        $entity->$setter(null);
                    }
                }
            } catch (\Exception $e) {
                // If loading fails, only set to null if the setter accepts it
                if ($this->setterAcceptsNull($entity, $setter)) {
                    $entity->$setter(null);
                }
            }
        } else {
            // If no foreign key value exists, set to null if acceptable
            if (method_exists($entity, $setter) && $this->setterAcceptsNull($entity, $setter)) {
                $entity->$setter(null);
            }
        }

        return $relatedEntity;
    }

    /**
     * @param object $entity
     * @param MtOneToMany $oneToMany
     * @param string $property
     * @return Collection<int, object>
     */
    private function loadOneToMany(object $entity, MtOneToMany $oneToMany, string $property): Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($entityId === null) {
            // If entity has no ID, it cannot have related OneToMany entities from DB
            // Set an empty collection if setter exists
            $setter = 'set' . ucfirst($property);
            if (method_exists($entity, $setter)) {
                // Ensure type compatibility, assuming setter accepts Collection
                $entity->{$setter}(new DatabaseCollection());
            }
            return new DatabaseCollection();
        }

        $setter = 'set' . ucfirst($property);

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

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->select('*')
            ->from($this->getTableName($targetEntity))
            ->where(SqlOperations::equal(
                $mappedByColumn,
                $queryBuilder->addNamedParameter($entityId)
            ));

        $result = $this->entityManager->getEmEngine()->getQueryBuilderListResult(
            $queryBuilder, // Pass the QueryBuilder instance
            $targetEntity  // Pass the target entity class string
        );

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
     */
    private function loadManyToMany(object $entity, MtManyToMany $relation, string $property): Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($entityId === null) {
            // Set empty collection if no ID
            $setter = 'set' . ucfirst($property);
            if (method_exists($entity, $setter)) {
                $collection = new DatabaseCollection();
                $collection->synchronizeInitialState(); // Important: synchronize empty state
                $entity->{$setter}($collection);
            }
            return new DatabaseCollection();
        }

        $setter = 'set' . ucfirst($property);

        // Pour ManyToMany: TOUJOURS recharger depuis la base de données
        // pour s'assurer que l'état reflète les changements

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
        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
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
     * Check if a property is initialized (regardless of content)
     *
     * @param object $entity
     * @param string $property
     * @return bool
     */
    private function isPropertyInitialized(object $entity, string $property): bool
    {
        try {
            $reflection = new \ReflectionClass($entity);
            $reflectionProperty = $reflection->getProperty($property);

            return $reflectionProperty->isInitialized($entity);
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Check if a property is initialized and not null
     *
     * @param object $entity
     * @param string $property
     * @param string $getter
     * @return bool
     */
    private function isPropertyInitializedAndNotNull(object $entity, string $property, string $getter): bool
    {
        try {
            $reflection = new \ReflectionClass($entity);
            $reflectionProperty = $reflection->getProperty($property);

            // Check if property is initialized
            if (!$reflectionProperty->isInitialized($entity)) {
                return false;
            }

            // Check if value is not null
            if (!method_exists($entity, $getter)) {
                return false;
            }

            return $entity->$getter() !== null;
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * Check if a property is initialized and not empty (for collections)
     *
     * @param object $entity
     * @param string $property
     * @param string $getter
     * @return bool
     */
    private function isPropertyInitializedAndNotEmpty(object $entity, string $property, string $getter): bool
    {
        try {
            $reflection = new \ReflectionClass($entity);
            $reflectionProperty = $reflection->getProperty($property);

            // Check if property is initialized
            if (!$reflectionProperty->isInitialized($entity)) {
                return false;
            }

            // Check if getter method exists
            if (!method_exists($entity, $getter)) {
                return false;
            }

            $value = $entity->$getter();

            // Check if it's a collection and has content
            if ($value instanceof Collection) {
                return $value->count() > 0;
            }

            return $value !== null;
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * @param class-string $entityName
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
        try {
            $reflection = new \ReflectionClass($entity);

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
        } catch (\ReflectionException $e) {
            return false;
        }
    }
}
