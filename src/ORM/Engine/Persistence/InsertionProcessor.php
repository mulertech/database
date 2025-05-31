<?php

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use ReflectionException;
use RuntimeException;

/**
 * Specialized processor for entity insertions
 *
 * @package MulerTech\Database\ORM\Engine\Persistence
 * @author Sébastien Muler
 */
class InsertionProcessor
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
        $queryBuilder = $this->buildInsertQuery($entity, $changes);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();

        $this->setGeneratedId($entity);

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
     * @param object $entity
     * @param array<string, array<int, mixed>> $changes
     * @return QueryBuilder
     * @throws ReflectionException
     */
    private function buildInsertQuery(object $entity, array $changes): QueryBuilder
    {
        $tableName = $this->getTableName($entity::class);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->insert($tableName);

        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);

        foreach ($propertiesColumns as $property => $column) {
            if (!isset($changes[$property][1])) {
                continue;
            }

            $value = $changes[$property][1];

            // If it's an object (relation), retrieve its ID
            if (is_object($value)) {
                $value = $this->getId($value);
            }

            $queryBuilder->setValue($column, $queryBuilder->addNamedParameter($value));
        }

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
        $tableName = $this->getTableName($entityClass);
        $propertiesColumns = $this->getPropertiesColumns($entityClass, false);

        if (empty($propertiesColumns)) {
            return;
        }

        $columns = array_values($propertiesColumns);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES %s',
            $tableName,
            implode('`, `', $columns),
            str_repeat("($placeholders),", count($entities) - 1) . "($placeholders)"
        );

        $parameters = [];
        foreach ($entities as $entity) {
            foreach ($propertiesColumns as $property => $column) {
                $value = $this->getPropertyValue($entity, $property);
                $parameters[] = is_object($value) ? $this->getId($value) : $value;
            }
        }

        $statement = $this->entityManager->getPdm()->prepare($sql);
        $statement->execute($parameters);

        // For batch inserts, we cannot easily retrieve generated IDs
        // This limitation is acceptable for performance optimizations
        $statement->closeCursor();
    }

    /**
     * @param object $entity
     * @return void
     */
    private function setGeneratedId(object $entity): void
    {
        $lastId = $this->entityManager->getPdm()->lastInsertId();

        if (!empty($lastId)) {
            if (!method_exists($entity, 'setId')) {
                throw new RuntimeException(
                    sprintf('The entity %s must have a setId method', $entity::class)
                );
            }

            $entity->setId((int)$lastId);
        }
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
     * @param object $entity
     * @param string $property
     * @return mixed
     * @throws ReflectionException
     */
    private function getPropertyValue(object $entity, string $property): mixed
    {
        $reflection = new \ReflectionClass($entity);
        return $reflection->getProperty($property)->getValue($entity);
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
