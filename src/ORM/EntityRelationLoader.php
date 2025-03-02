<?php

namespace MulerTech\Database\ORM;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\MtManyToMany;
use MulerTech\Database\Mapping\MtManyToOne;
use MulerTech\Database\Mapping\MtOneToMany;
use MulerTech\Database\Mapping\MtOneToOne;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use ReflectionException;

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
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function loadRelations(object $entity): void
    {
        $entityName = get_class($entity);

        foreach ($this->dbMapping->getOneToOne($entityName) as $property => $oneToOne) {
            $this->loadOneToOne($entity, $oneToOne, $property);
        }
        foreach ($this->dbMapping->getOneToMany($entityName) as $property => $oneToMany) {
            $this->loadOneToMany($entity, $oneToMany, $property);
        }
        foreach ($this->dbMapping->getManyToOne($entityName) as $property => $manyToOne) {
            $this->loadManyToOne($entity, $manyToOne, $property);
        }
        foreach ($this->dbMapping->getManyToMany($entityName) as $property => $manyToMany) {
            $this->loadManyToMany($entity, $manyToMany, $property);
        }
    }

    /**
     * @param object $entity
     * @param MtOneToOne $oneToOne
     * @param string $property
     * @return void
     */
    private function loadOneToOne(object $entity, MtOneToOne $oneToOne, string $property): void
    {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        if (!is_null($entity->$getter())) {
            return;
        }

        $column = $this->dbMapping->getColumnName(get_class($entity), $property);

        if (!is_null($entity->$column) && method_exists($entity, $setter)) {
            $relatedEntity = $this->entityManager->find($oneToOne->entity, $entity->$column);
            $entity->$setter($relatedEntity);
        }

    }

    /**
     * @param object $entity
     * @param MtOneToMany $oneToMany
     * @param string $property
     * @return void
     * @throws ReflectionException
     */
    private function loadOneToMany(object $entity, MtOneToMany $oneToMany, string $property): void
    {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        if (!is_null($entity->$getter())) {
            return;
        }

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder
            ->select()
            ->from($this->dbMapping->getTableName($oneToMany->entity))
            ->where(SqlOperations::equal($oneToMany->mappedBy, $entity->getId()));

        $manyRelationResult = $this->entityManager->getEmEngine()->getQueryBuilderResult(
            $queryBuilder,
            EntityManagerResultType::LIST,
            $oneToMany->entity,
            false
        );

        if ($manyRelationResult === null) {
            return;
        }

        $entity->$setter($manyRelationResult);
    }

    /**
     * @param object $entity
     * @param MtManyToOne $manyToOne
     * @param string $property
     * @return void
     */
    private function loadManyToOne(object $entity, MtManyToOne $manyToOne, string $property): void
    {
        $setter = 'set' . ucfirst($property);
        $getter = 'get' . ucfirst($property);

        if (!is_null($entity->$getter())) {
            return;
        }

        $column = $this->dbMapping->getColumnName(get_class($entity), $property);

        if (!is_null($entity->$column) && method_exists($entity, $setter)) {
            $relatedEntity = $this->entityManager->find($manyToOne->entity, $entity->$column);
            $entity->$setter($relatedEntity);
        }
    }

    /**
     * @param object $entity
     * @param MtManyToMany $manyToMany
     * @param string $property
     * @return void
     */
    private function loadManyToMany(object $entity, MtManyToMany $manyToMany, string $property): void
    {
        // TODO: Implémenter la logique de chargement pour ManyToMany
    }
}