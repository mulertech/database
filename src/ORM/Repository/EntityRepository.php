<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Repository;

use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;

/**
 * Class EntityRepository
 *
 * Base repository class for entity data access operations.
 *
 * @package MulerTech\Database
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
        return new QueryBuilder($this->entityManager->getEmEngine());
    }

    /**
     * @param string $id
     * @return Object|null
     */
    public function find(string $id): ?Object
    {
        return $this->entityManager->find($this->entityName, $id);
    }
}
