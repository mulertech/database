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
    /**
     * @var DbMappingInterface
     */
    private DbMappingInterface $dbMapping;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->dbMapping = $entityManager->getDbMapping();
    }

    /**
     * Todo: delete this method if not used
     * @throws ReflectionException
     */
    public function loadEntities(array $entities, int $level = 0): void
    {
        if ($level > 10) {
            return;
        }

        $entitiesToLoad = [];

        foreach ($entities as $entity) {
            $entitiesToLoad = $this->loadRelations($entity);
        }

        if (!empty($entitiesToLoad)) {
            $this->loadEntities($entitiesToLoad, ++$level);
        }
    }

    /**
     * @param object $entity
     * @return array
     * @throws ReflectionException
     */
    public function loadRelations(object $entity): array
    {
        $entityName = get_class($entity);

        $entitiesToLoad = [];

        foreach ($this->dbMapping->getOneToOne($entityName) as $property => $oneToOne) {
            $result = $this->loadOneToOne($entity, $oneToOne, $property);
            if ($result !== null) {
                $entitiesToLoad[] = $result;
            }
        }
        foreach ($this->dbMapping->getOneToMany($entityName) as $property => $oneToMany) {
            $result = $this->loadOneToMany($entity, $oneToMany, $property);
            if ($result !== null) {
                $entitiesToLoad[] = $result;
            }
        }
        foreach ($this->dbMapping->getManyToOne($entityName) as $property => $manyToOne) {
            $result = $this->loadManyToOne($entity, $manyToOne, $property);
            if ($result !== null) {
                $entitiesToLoad[] = $result;
            }
        }
        foreach ($this->dbMapping->getManyToMany($entityName) as $property => $manyToMany) {
            $result = $this->loadManyToMany($entity, $manyToMany, $property);
            if ($result !== null) {
                $entitiesToLoad[] = $result;
            }
        }

        return $entitiesToLoad;
    }

    /**
     * @param object $entity
     * @param MtOneToOne $oneToOne
     * @param string $property
     * @return object|null
     */
    private function loadOneToOne(object $entity, MtOneToOne $oneToOne, string $property): ?object
    {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        if (!is_null($entity->$getter())) {
            return null;
        }

        $column = $this->dbMapping->getColumnName(get_class($entity), $property);

        if (!is_null($entity->$column) && method_exists($entity, $setter)) {
            $relatedEntity = $this->entityManager->find($oneToOne->targetEntity, $entity->$column);
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
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        // If the relation is already loaded, return it
        $actualRelation = $entity->$getter();
        if ($actualRelation instanceof Collection && $actualRelation->count() > 0) {
            return $actualRelation;
        }

        $mappedByColumn = $this->dbMapping->getColumnName(get_class($entity), $oneToMany->mappedBy);

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder
            ->select()
            ->from($this->dbMapping->getTableName($oneToMany->targetEntity))
            ->where(SqlOperations::equal($mappedByColumn, $entity->getId()));

        $manyRelationResult = $this->entityManager->getEmEngine()->getQueryBuilderListResult(
            $queryBuilder,
            $oneToMany->targetEntity,
            false
        );

        if ($manyRelationResult === null) {
            return null;
        }

        $oneToMany->entity = get_class($entity);
        $databaseCollection = new DatabaseCollection($this->entityManager, $oneToMany, $manyRelationResult);

        $entity->$setter($databaseCollection);

        return $databaseCollection;
    }

    /**
     * @param object $entity
     * @param MtManyToOne $manyToOne
     * @param string $property
     * @return object|null
     */
    private function loadManyToOne(object $entity, MtManyToOne $manyToOne, string $property): ?object
    {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        if (!is_null($entity->$getter())) {
            return null;
        }

        $column = $this->dbMapping->getColumnName(get_class($entity), $property);

        if (!is_null($entity->$column) && method_exists($entity, $setter)) {
            $relatedEntity = $this->entityManager->find($manyToOne->targetEntity, $entity->$column);
            $entity->$setter($relatedEntity);
        }

        return $relatedEntity ?? null;
    }

    /**
     * @param object $entity
     * @param MtManyToMany $manyToMany
     * @param string $property
     * @return Collection|null
     * @throws ReflectionException
     */
    private function loadManyToMany(object $entity, MtManyToMany $manyToMany, string $property): ?Collection
    {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        // If the relation is already loaded, return it
        $actualRelation = $entity->$getter();
        if ($actualRelation instanceof Collection && $actualRelation->count() > 0) {
            return $actualRelation;
        }

        $entityId = $entity->getId();

        if (is_null($entityId)) {
            return null;
        }
        $table = $this->dbMapping->getTableName(get_class($entity));
        if ($table === null) {
            throw new RuntimeException(
                sprintf('MtEntity->tableName not define for %s class', get_class($entity))
            );
        }
        $pivotTable = $this->dbMapping->getTableName($manyToMany->mappedBy);
        if ($pivotTable === null) {
            throw new RuntimeException(
                sprintf('MtEntity->tableName not define for %s class', $manyToMany->mappedBy)
            );
        }
        if ($manyToMany->inverseJoinProperty === null) {
            throw new RuntimeException(
                sprintf('MtManyToMany->inverseJoinProperty not define for %s class', get_class($entity))
            );
        }
        if ($manyToMany->joinProperty === null) {
            throw new RuntimeException(
                sprintf('MtManyToMany->joinProperty not define for %s class', get_class($entity))
            );
        }

        $joinColumn = $this->dbMapping->getColumnName($manyToMany->mappedBy, $manyToMany->joinProperty);

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder
            ->select('t.*')
            ->from($table, 't')
            ->innerJoin(
                $table . ' t',
                $pivotTable . ' j',
                SqlOperations::equal('j.' . $joinColumn, 't.id')
            );

        $manyToManyResult = $this->entityManager->getEmEngine()->getQueryBuilderListKeyByIdResult(
            $queryBuilder,
            $manyToMany->targetEntity,
            false
        );

        if ($manyToManyResult === null) {
            return null;
        }

        $manyToMany->entity = get_class($entity);
        $databaseCollection = new DatabaseCollection($this->entityManager, $manyToMany, $manyToManyResult);

        if (method_exists($entity, $setter)) {
            $entity->$setter($databaseCollection);
        }

        return $databaseCollection;
    }
}