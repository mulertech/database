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
    protected EntityManagerInterface $entityManager;
    /**
     * @var class-string $entityName
     */
    private string $entityName;

    /**
     * @param EntityManagerInterface $entityManager
     * @param class-string $entityName
     */
    public function __construct(EntityManagerInterface $entityManager, string $entityName)
    {
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
    }

    /**
     * @return class-string
     */
    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->entityManager->getEmEngine()))->from($this->entityName);
    }

    /**
     * @param string $id
     * @return Entity|null
     */
    public function find(string $id): ?Entity
    {
        return $this->entityManager->find($this->entityName, $id);
    }
}