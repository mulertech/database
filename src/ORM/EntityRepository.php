<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use BadMethodCallException;
use MulerTech\Database\Query\Builder\QueryBuilder;
use ReflectionException;
use stdClass;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityRepository
{
    protected EntityManagerInterface $entityManager;
    /** @var class-string */
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
        /** @var class-string */
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
     * @return string
     * @throws ReflectionException
     */
    protected function getTableName(): string
    {
        return $this->entityManager->getMetadataCache()->getEntityMetadata($this->entityName)->tableName;
    }

    /**
     * @param string|int $id
     * @return object|null
     */
    public function find(string|int $id): ?object
    {
        return $this->entityManager->find($this->entityName, $id);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     * @return array<object>
     * @throws ReflectionException
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select('*')
            ->from($this->getTableName());

        // Add WHERE conditions for criteria
        foreach ($criteria as $field => $value) {
            /** @var bool|float|int|string|null $value */
            $queryBuilder->where($field, $value);
        }

        // Add ORDER BY clauses
        if ($orderBy !== null) {
            foreach ($orderBy as $field => $direction) {
                $queryBuilder->orderBy($field, $direction);
            }
        }

        // Handle OFFSET and LIMIT - if offset is specified without limit, use a large limit
        if ($offset !== null && $limit === null) {
            $limit = PHP_INT_MAX;
        }

        // Add LIMIT
        if ($limit !== null) {
            $queryBuilder->limit($limit);
        }

        // Add OFFSET
        if ($offset !== null) {
            $queryBuilder->offset($offset);
        }

        $results = $queryBuilder->fetchAll();

        // Convert raw data to entities using EntityHydrator
        $entities = [];
        $hydrator = $this->entityManager->getHydrator();

        foreach ($results as $row) {
            // Convert stdClass to array if needed
            $data = $row instanceof stdClass ? (array) $row : $row;
            /** @var array<string, bool|float|int|string|null> $data */

            $entity = $hydrator->hydrate($data, $this->entityName);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @return object|null
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $results = $this->findBy($criteria, $orderBy, 1);
        return $results[0] ?? null;
    }

    /**
     * @return array<object>
     */
    public function findAll(): array
    {
        return $this->findBy([]);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return int
     */
    public function count(array $criteria = []): int
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->getTableName());

        // Add WHERE conditions for criteria
        foreach ($criteria as $field => $value) {
            /** @var bool|float|int|string|null $value */
            $queryBuilder->where($field, $value);
        }

        $result = $queryBuilder->fetchScalar();
        return is_numeric($result) ? (int) $result : 0;
    }

    /**
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @param string $method
     * @param array<mixed> $arguments
     * @return array<object>|object|null
     */
    public function __call(string $method, array $arguments): array|null|object
    {
        // Handle magic methods like findByUsername, findOneByEmail, etc.
        if (str_starts_with($method, 'findBy')) {
            $field = lcfirst(substr($method, 6));
            return $this->findBy([$field => $arguments[0] ?? null]);
        }

        if (str_starts_with($method, 'findOneBy')) {
            $field = lcfirst(substr($method, 9));
            return $this->findOneBy([$field => $arguments[0] ?? null]);
        }

        throw new BadMethodCallException("Method $method does not exist");
    }
}
