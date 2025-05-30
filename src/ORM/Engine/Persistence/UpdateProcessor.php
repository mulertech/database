<?php

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use ReflectionException;
use RuntimeException;

/**
 * Specialized processor for entity updates
 *
 * @package MulerTech\Database\ORM\Engine\Persistence
 * @author Sébastien Muler
 */
class UpdateProcessor
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
     * @param array<string, array<int, mixed>> $changes
     * @return void
     * @throws ReflectionException
     */
    public function execute(object $entity, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        $queryBuilder = $this->buildUpdateQuery($entity, $changes);

        // Verify that the query has actual SET clauses
        if (!$this->hasValidValues($queryBuilder, $entity, $changes)) {
            return;
        }

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $pdoStatement->closeCursor();
    }

    /**
     * @param array<object> $entities
     * @param array<int, array<string, array<int, mixed>>> $allChanges
     * @return void
     * @throws ReflectionException
     */
    public function executeBatch(array $entities, array $allChanges): void
    {
        if (empty($entities)) {
            return;
        }

        $entitiesByType = $this->groupEntitiesByType($entities);

        foreach ($entitiesByType as $entityClass => $typeEntities) {
            $this->executeBatchForType($entityClass, $typeEntities, $allChanges);
        }
    }

    /**
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return QueryBuilder
     * @throws ReflectionException
     */
    private function buildUpdateQuery(object $entity, array $changes): QueryBuilder
    {
        $tableName = $this->getTableName($entity::class);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->update($tableName);

        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);

        foreach ($propertiesColumns as $property => $column) {
            if (!isset($changes[$property][1])) {
                continue;
            }

            $value = $changes[$property][1];

            // If it's an object (relation), we retrieve its ID
            if (is_object($value)) {
                $value = $this->getId($value);
            }

            $queryBuilder->setValue($column, $queryBuilder->addDynamicParameter($value));
        }

        $queryBuilder->where(
            SqlOperations::equal('id', $queryBuilder->addDynamicParameter($this->getId($entity)))
        );

        return $queryBuilder;
    }

    /**
     * @param class-string $entityClass
     * @param array<object> $entities
     * @param array<int, array<string, array<int, mixed>>> $allChanges
     * @return void
     * @throws ReflectionException
     */
    private function executeBatchForType(string $entityClass, array $entities, array $allChanges): void
    {
        $tableName = $this->getTableName($entityClass);
        $propertiesColumns = $this->getPropertiesColumns($entityClass, false);

        if (empty($propertiesColumns)) {
            return;
        }

        // For batch updates, we can use CASE WHEN or individual updates
        // Here we choose individual updates grouped in a transaction
        foreach ($entities as $entity) {
            $entityId = spl_object_id($entity);
            $changes = $allChanges[$entityId] ?? [];

            if (!empty($changes)) {
                $this->execute($entity, $changes);
            }
        }
    }

    /**
     * @param class-string $entityClass
     * @param array<object> $entities
     * @param string $property
     * @param mixed $value
     * @return void
     * @throws ReflectionException
     */
    public function bulkUpdate(string $entityClass, array $entities, string $property, mixed $value): void
    {
        if (empty($entities)) {
            return;
        }

        $tableName = $this->getTableName($entityClass);
        $column = $this->getColumnName($entityClass, $property);

        $ids = array_map(fn ($entity) => $this->getId($entity), $entities);
        $idsPlaceholder = str_repeat('?,', count($ids) - 1) . '?';

        $sql = sprintf(
            'UPDATE `%s` SET `%s` = ? WHERE id IN (%s)',
            $tableName,
            $column,
            $idsPlaceholder
        );

        $parameters = [$value, ...$ids];

        $statement = $this->entityManager->getPdm()->prepare($sql);
        $statement->execute($parameters);
        $statement->closeCursor();
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $updates
     * @return int
     * @throws ReflectionException
     */
    public function updateByCriteria(string $entityClass, array $criteria, array $updates): int
    {
        $tableName = $this->getTableName($entityClass);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->update($tableName);

        foreach ($updates as $property => $value) {
            $column = $this->getColumnName($entityClass, $property);
            $queryBuilder->setValue($column, $queryBuilder->addDynamicParameter($value));
        }

        $this->applyCriteria($queryBuilder, $entityClass, $criteria);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $rowCount = $pdoStatement->rowCount();
        $pdoStatement->closeCursor();

        return $rowCount;
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
     * @param bool $keepId
     * @return array<string, string>
     * @throws ReflectionException
     */
    private function getPropertiesColumns(string $entityName, bool $keepId = true): array
    {
        $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityName);

        if (!$keepId && isset($propertiesColumns['id'])) {
            unset($propertiesColumns['id']);
        }

        return $propertiesColumns;
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
     * @param QueryBuilder $queryBuilder
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return bool
     * @throws ReflectionException
     */
    private function hasValidValues(QueryBuilder $queryBuilder, object $entity, array $changes): bool
    {
        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);
        $hasValues = false;

        foreach ($propertiesColumns as $property => $column) {
            if (isset($changes[$property][1])) {
                $hasValues = true;
                break;
            }
        }

        return $hasValues;
    }
}
