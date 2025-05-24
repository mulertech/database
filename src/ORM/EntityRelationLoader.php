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
     * @param array $entityData
     * @return array
     * @throws ReflectionException
     */
    public function loadRelations(object $entity, array $entityData): array
    {
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
                if ($result !== null) {
                    $entitiesToLoad[] = $result;
                }
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
                if ($result !== null) {
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

        if (!is_null($entity->$getter())) {
            return null;
        }

        $column = $this->getColumnName(get_class($entity), $property);

        if (isset($entityData[$column]) && method_exists($entity, $setter)) {
            $relatedEntity = $this->entityManager->find(
                $this->getTargetEntity(get_class($entity), $relation, $property),
                $entityData[$column]
            );
            $entity->$setter($relatedEntity);
        }

        return $relatedEntity ?? null;
    }

    /**
     * @param object $entity
     * @param MtOneToMany $oneToMany
     * @param string $property
     * @return Collection|null
     * @throws ReflectionException
     */
    private function loadOneToMany(object $entity, MtOneToMany $oneToMany, string $property): ?Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (is_null($entityId)) {
            return null;
        }

        $getter = 'get' . ucfirst($property);

        // If the relation is already loaded, return it
        $actualRelation = $entity->$getter();
        if ($actualRelation instanceof Collection && $actualRelation->count() > 0) {
            return $actualRelation;
        }

        $mappedByColumn = $this->getColumnName(
            get_class($entity),
            $this->getInverseJoinProperty(get_class($entity), $oneToMany, $property)
        );
        $targetEntity = $this->getTargetEntity(get_class($entity), $oneToMany, $property);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->select()
            ->from($this->getTableName($targetEntity))
            ->where(SqlOperations::equal($mappedByColumn, $entityId));

        $result = $this->entityManager->getEmEngine()->getQueryBuilderListResult(
            $queryBuilder,
            $targetEntity,
            false
        );

        if ($result === null) {
            return null;
        }

        $oneToMany->entity = get_class($entity);
        $collection = new DatabaseCollection($this->entityManager, $oneToMany, $result);

        $setter = 'set' . ucfirst($property);
        $entity->$setter($collection);

        return $collection;
    }

    /**
     * @param class-string $entityName
     * @return string
     */
    private function getTableName(string $entityName): string
    {
        if (null === $tableName = $this->dbMapping->getTableName($entityName)) {
            throw new RuntimeException(
                sprintf(
                    'TableName not define for %s class',
                    $entityName
                )
            );
        }

        return $tableName;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string
     */
    private function getColumnName(string $entityName, string $property): string
    {
        if (null === $columnName = $this->dbMapping->getColumnName($entityName, $property)) {
            throw new RuntimeException(
                sprintf(
                    'ColumnName not define for %s class and property %s',
                    $entityName,
                    $property
                )
            );
        }

        return $columnName;
    }

    /**
     * @param object $entity
     * @param MtManyToMany $relation
     * @param string $property
     * @return Collection|null
     * @throws ReflectionException
     */
    private function loadManyToMany(object $entity, MtManyToMany $relation, string $property): ?Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (is_null($entityId)) {
            return null;
        }
        $getter = 'get' . ucfirst($property);

        // If the relation is already loaded, return it
        $actualRelation = $entity->$getter();
        if ($actualRelation instanceof Collection && $actualRelation->count() > 0) {
            return $actualRelation;
        }

        $table = $this->getTableName(get_class($entity));
        $pivotTable = $this->getTableName($this->getMappedBy(get_class($entity), $relation, $property));

        $joinColumn = $this->getColumnName(
            $this->getMappedBy(get_class($entity), $relation, $property),
            $this->getJoinProperty(get_class($entity), $relation, $property)
        );

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->select('t.*')
            ->from($table, 't')
            ->innerJoin($table . ' t', $pivotTable . ' j', SqlOperations::equal('j.' . $joinColumn, 't.id'));

        $result = $this->entityManager->getEmEngine()->getQueryBuilderListKeyByIdResult(
            $queryBuilder,
            $this->getTargetEntity(get_class($entity), $relation, $property),
            false
        );

        if ($result === null) {
            return null;
        }

        $relation->entity = get_class($entity);
        $collection = new DatabaseCollection($this->entityManager, $relation, $result);

        $setter = 'set' . ucfirst($property);
        if (method_exists($entity, $setter)) {
            $entity->$setter($collection);
        }

        return $collection;
    }

    /**
     * @param class-string $entity
     * @param MtOneToOne|MtManyToOne|MtOneToMany|MtManyToMany $relation
     * @param string $property
     * @return class-string
     */
    private function getTargetEntity(
        string $entity,
        MtOneToOne|MtManyToOne|MtOneToMany|MtManyToMany $relation,
        string $property
    ): string {
        if (null === $targetEntity = $relation->targetEntity) {
            throw new RuntimeException(
                sprintf(
                    'MtEntity->targetEntity not define for %s class and property %s',
                    $entity,
                    $property
                )
            );
        }

        return $targetEntity;
    }

    /**
     * @param class-string $entity
     * @param MtOneToMany $relation
     * @param string $property
     * @return string
     */
    private function getInverseJoinProperty(string $entity, MtOneToMany $relation, string $property): string
    {
        if (null === $inverseJoinProperty = $relation->inverseJoinProperty) {
            throw new RuntimeException(
                sprintf(
                    'MtEntity->inverseJoinProperty not define for %s class and property %s',
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
                    'MtEntity->mappedBy not define for %s class and property %s',
                    $entity,
                    $property
                )
            );
        }

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
                    'MtEntity->joinProperty not define for %s class and property %s',
                    $entity,
                    $property
                )
            );
        }

        return $joinProperty;
    }
}
