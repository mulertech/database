<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\DeleteBuilder;
use MulerTech\Database\Query\Builder\QueryBuilder;
use ReflectionException;
use RuntimeException;

/**
 * Specialized processor for entity deletions
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class DeletionProcessor
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param DbMappingInterface $dbMapping
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DbMappingInterface $dbMapping
    ) {
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function process(object $entity): void
    {
        $this->execute($entity);
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function execute(object $entity): void
    {
        $deleteBuilder = $this->buildDeleteQuery($entity);

        $pdoStatement = $deleteBuilder->getResult();
        $pdoStatement->execute();
        $pdoStatement->closeCursor();
    }

    /**
     * @param object $entity
     * @return DeleteBuilder
     * @throws ReflectionException
     */
    private function buildDeleteQuery(object $entity): DeleteBuilder
    {
        $deleteBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->delete($this->getTableName($entity::class));

        $entityId = $this->getId($entity);
        if ($entityId === null) {
            throw new RuntimeException(
                sprintf('Cannot delete entity %s without a valid ID', $entity::class)
            );
        }

        $deleteBuilder->where('id', $entityId);

        return $deleteBuilder;
    }

    /**
     * @param class-string $entityName
     * @return string
     * @throws ReflectionException
     */
    private function getTableName(string $entityName): string
    {
        $tableName = $this->dbMapping->getTableName($entityName);

        if ($tableName === null) {
            throw new RuntimeException(
                sprintf('The entity %s is not mapped in the database', $entityName)
            );
        }

        return $tableName;
    }

    /**
     * @param object $entity
     * @return int|null
     */
    private function getId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            throw new RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
    }
}
