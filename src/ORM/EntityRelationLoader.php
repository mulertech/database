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

        if (!is_null($entity->$getter())) {
            return null;
        }

        $column = $this->getColumnName(get_class($entity), $property);
        $relatedEntity = null;

        if (isset($entityData[$column]) && method_exists($entity, $setter)) {
            /** @var class-string $targetEntity */
            $targetEntity = $this->getTargetEntity(get_class($entity), $relation, $property);
            $relatedEntity = $this->entityManager->find(
                $targetEntity,
                $entityData[$column]
            );
            $entity->$setter($relatedEntity);
        }

        return $relatedEntity;
    }

    /**
     * @param object $entity
     * @param MtOneToMany $oneToMany
     * @param string $property
     * @return Collection<int, object>|null
     */
    private function loadOneToMany(object $entity, MtOneToMany $oneToMany, string $property): ?Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($entityId === null) {
            return null;
        }

        $getter = 'get' . ucfirst($property);
        $actualRelation = $entity->$getter();

        if ($actualRelation instanceof Collection && $actualRelation->count() > 0) {
            /** @var Collection<int, object> $actualRelation */
            return $actualRelation;
        }

        $mappedByColumn = $this->getColumnName(
            get_class($entity),
            $this->getInverseJoinProperty(get_class($entity), $oneToMany, $property)
        );
        /** @var class-string $targetEntity */
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
        /** @var Collection<int, object> $collection */
        $collection = new DatabaseCollection($result);

        $setter = 'set' . ucfirst($property);
        $entity->$setter($collection);

        return $collection;
    }

    /**
     * @param object $entity
     * @param MtManyToMany $relation
     * @param string $property
     * @return Collection<int, object>|null
     */
    private function loadManyToMany(object $entity, MtManyToMany $relation, string $property): ?Collection
    {
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        if ($entityId === null) {
            return null;
        }

        $getter = 'get' . ucfirst($property);
        $actualRelation = $entity->$getter();

        if ($actualRelation instanceof Collection && $actualRelation->count() > 0) {
            /** @var Collection<int, object> $actualRelation */
            return $actualRelation;
        }

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

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->select('t.*')
            ->from($targetTable, 't')
            ->where(
                new SqlOperations()->in(
                    't.id',
                    new QueryBuilder($this->entityManager->getEmEngine())
                        ->select('j.' . $inverseJoinColumn)
                        ->from($pivotTable, 'j')
                        ->where(SqlOperations::equal('j.' . $joinColumn, $entityId))
                )
            );

        $result = $this->entityManager->getEmEngine()->getQueryBuilderListKeyByIdResult(
            $queryBuilder,
            $this->getTargetEntity(get_class($entity), $relation, $property),
            false
        );

        $collection = null;
        if ($result !== null) {
            $relation->entity = get_class($entity);
            /** @var Collection<int, object> $collection */
            $collection = new DatabaseCollection($result);

            $setter = 'set' . ucfirst($property);
            if (method_exists($entity, $setter)) {
                $entity->$setter($collection);
            }
        }

        return $collection;
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
                    'Target entity is not defined for the class "%s" and property "%s". Please check the mapping configuration.',
                    $entity,
                    $property
                )
            );
        }

        /** @var class-string $targetEntity */
        return $targetEntity;
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
}
