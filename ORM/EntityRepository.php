<?php

namespace MulerTech\Database\ORM;

use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Entity\Entity;

/**
 * Class EntityRepository
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
class EntityRepository
{

    /**
     * @var EntityManagerInterface $entityManager
     */
    protected $entityManager;
    /**
     * @var string $entity
     */
    private $entity;

    public function __construct(EntityManagerInterface $entityManager, string $entity)
    {
        $this->entityManager = $entityManager;
        $this->entity = $entity;
    }

    /**
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->entityManager->getEmEngine()))->from($this->entity);
    }

    /**
     * @param string $id
     * @return Entity|null
     */
    public function find(string $id): ?Entity
    {
        return $this->entityManager->find($this->entity, $id);
    }
}