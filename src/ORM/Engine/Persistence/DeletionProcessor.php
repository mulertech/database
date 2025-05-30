<?php

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use ReflectionException;
use RuntimeException;

/**
 * Specialized processor for entity deletions
 *
 * @package MulerTech\Database\ORM\Engine\Persistence
 * @author Sébastien Muler
 */
class DeletionProcessor
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param DbMappingInterface $dbMapping
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DbMappingInterface $dbMapping
    ) {
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function execute(object $entity): void
    {
        $queryBuilder = $this->buildDeleteQuery($entity);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $pdoStatement->closeCursor();
    }

    /**
     * @param array<object> $entities
     * @return void
     * @throws ReflectionException
     */
    public function executeBatch(array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $entitiesByType = $this->groupEntitiesByType($entities);

        foreach ($entitiesByType as $entityClass => $typeEntities) {
            $this->executeBatchForType($entityClass, $typeEntities);
        }
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $criteria
     * @return int
     * @throws ReflectionException
     */
    public function deleteByCriteria(string $entityClass, array $criteria): int
    {
        $tableName = $this->getTableName($entityClass);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->delete($tableName);

        $this->applyCriteria($queryBuilder, $entityClass, $criteria);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $rowCount = $pdoStatement->rowCount();
        $pdoStatement->closeCursor();

        return $rowCount;
    }

    /**
     * @param class-string $entityClass
     * @param array<int|string> $ids
     * @return int
     * @throws ReflectionException
     */
    public function deleteByIds(string $entityClass, array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $tableName = $this->getTableName($entityClass);
        $idsPlaceholder = str_repeat('?,', count($ids) - 1) . '?';

        $sql = sprintf('DELETE FROM `%s` WHERE id IN (%s)', $tableName, $idsPlaceholder);

        $statement = $this->entityManager->getPdm()->prepare($sql);
        $statement->execute($ids);
        $rowCount = $statement->rowCount();
        $statement->closeCursor();

        return $rowCount;
    }

    /**
     * @param class-string $entityClass
     * @return int
     * @throws ReflectionException
     */
    public function truncate(string $entityClass): int
    {
        $tableName = $this->getTableName($entityClass);

        // TRUNCATE is faster but cannot be in a transaction
        // and does not trigger triggers. We use DELETE for more safety
        $sql = sprintf('DELETE FROM `%s`', $tableName);

        return $this->entityManager->getPdm()->exec($sql);
    }

    /**
     * @param object $entity
     * @return QueryBuilder
     * @throws ReflectionException
     */
    private function buildDeleteQuery(object $entity): QueryBuilder
    {
        $tableName = $this->getTableName($entity::class);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());

        $queryBuilder->delete($tableName);
        $queryBuilder->where(
            SqlOperations::equal('id', $queryBuilder->addNamedParameter($this->getId($entity)))
        );

        return $queryBuilder;
    }

    /**
     * @param class-string $entityClass
     * @param array<object> $entities
     * @return void
     * @throws ReflectionException
     */
    private function executeBatchForType(string $entityClass, array $entities): void
    {
        $ids = array_map(fn ($entity) => $this->getId($entity), $entities);
        $this->deleteByIds($entityClass, $ids);
    }

    /**
     * @param array<object> $entities
     * @return array<class-string, array<object>>
     */
    private function groupEntitiesByType(array $entities): array
    {
        $grouped = [];

        foreach ($entities as $entity) {
            $entityClass = $entity::class;
            if (!isset($grouped[$entityClass])) {
                $grouped[$entityClass] = [];
            }
            $grouped[$entityClass][] = $entity;
        }

        return $grouped;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityClass
     * @param array<string, mixed> $criteria
     * @return void
     * @throws ReflectionException
     */
    private function applyCriteria(QueryBuilder $queryBuilder, string $entityClass, array $criteria): void
    {
        $first = true;

        foreach ($criteria as $property => $value) {
            $column = $this->getColumnName($entityClass, $property);
            $condition = SqlOperations::equal($column, $queryBuilder->addDynamicParameter($value));

            if ($first) {
                $queryBuilder->where($condition);
                $first = false;
            } else {
                $queryBuilder->andWhere($condition);
            }
        }
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
     * @param class-string $entityName
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    private function getColumnName(string $entityName, string $property): string
    {
        $columnName = $this->dbMapping->getColumnName($entityName, $property);

        if ($columnName === null) {
            throw new RuntimeException(
                sprintf('Column name not found for property %s in entity %s', $property, $entityName)
            );
        }

        return $columnName;
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

    /**
     * @param object $entity
     * @return bool
     * @throws ReflectionException
     */
    public function canDelete(object $entity): bool
    {
        // Checks if there are foreign key constraints that prevent deletion
        $entityClass = $entity::class;
        $entityId = $this->getId($entity);

        if ($entityId === null) {
            return true; // The entity is not yet persisted
        }

        // Here we could add business constraint checks
        // For now, we return true
        return true;
    }

    /**
     * @param object $entity
     * @return array<string>
     * @throws ReflectionException
     */
    public function getDeletionWarnings(object $entity): array
    {
        $warnings = [];

        // This method can be extended to check relations
        // and warn the user about the consequences of deletion

        return $warnings;
    }
}
