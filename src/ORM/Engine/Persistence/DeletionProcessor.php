<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Mapping\MetadataRegistry;
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
     * @param MetadataRegistry $metadataRegistry
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MetadataRegistry $metadataRegistry
    ) {
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException|Exception
     */
    public function process(object $entity): void
    {
        $this->execute($entity);
    }

    /**
     * @param object $entity
     * @return void
     * @throws Exception
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
     * @throws Exception
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
     * @throws Exception
     */
    private function getTableName(string $entityName): string
    {
        return $this->metadataRegistry->getEntityMetadata($entityName)->tableName;
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
