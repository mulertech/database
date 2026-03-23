<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Query\Builder\QueryBuilder;
use stdClass;

/**
 * @author Sébastien Muler
 */
class EntityRepository
{
    protected EntityManagerInterface $entityManager;
    /** @var class-string */
    private string $entityName;

    /**
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
        /* @var class-string */
        return $this->entityName;
    }

    protected function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->entityManager->getEmEngine());
    }

    /**
     * @throws \ReflectionException
     */
    protected function getTableName(): string
    {
        return $this->entityManager->getMetadataRegistry()->getEntityMetadata($this->entityName)->tableName;
    }

    public function find(string|int $id): ?object
    {
        return $this->entityManager->find($this->entityName, $id);
    }

    /**
     * @param array<string, bool|float|int|string|null> $criteria
     * @param array<string, string>|null                $orderBy
     *
     * @return array<object>
     *
     * @throws \ReflectionException
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select('*')
            ->from($this->getTableName());

        // Add WHERE conditions for criteria
        foreach ($criteria as $field => $value) {
            $queryBuilder->where($field, $value);
        }

        // Add ORDER BY clauses
        if (null !== $orderBy) {
            foreach ($orderBy as $field => $direction) {
                $queryBuilder->orderBy($field, $direction);
            }
        }

        // Handle OFFSET and LIMIT - if offset is specified without limit, use a large limit
        if (null !== $offset && null === $limit) {
            $limit = PHP_INT_MAX;
        }

        // Add LIMIT
        if (null !== $limit) {
            $queryBuilder->limit($limit);
        }

        // Add OFFSET
        if (null !== $offset) {
            $queryBuilder->offset($offset);
        }

        $results = $queryBuilder->fetchAll();

        // Convert raw data to entities using EntityHydrator
        $entities = [];
        $hydrator = $this->entityManager->getHydrator();

        foreach ($results as $row) {
            // Convert stdClass to array if needed
            $data = $row instanceof \stdClass ? (array) $row : $row;
            /** @var array<string, bool|float|int|string|null> $data */
            $entity = $hydrator->hydrate($data, $this->entityName);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * @param array<string, bool|float|int|string|null> $criteria
     * @param array<string, string>|null                $orderBy
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
     * @param array<string, bool|float|int|string|null> $criteria
     */
    public function count(array $criteria = []): int
    {
        $queryBuilder = $this->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->getTableName());

        // Add WHERE conditions for criteria
        foreach ($criteria as $field => $value) {
            $queryBuilder->where($field, $value);
        }

        $result = $queryBuilder->fetchScalar();

        return is_numeric($result) ? (int) $result : 0;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * @param array<mixed> $arguments
     *
     * @return array<object>|object|null
     */
    public function __call(string $method, array $arguments): array|object|null
    {
        // Handle magic methods like findByUsername, findOneByEmail, etc.
        if (str_starts_with($method, 'findBy')) {
            $field = lcfirst(substr($method, 6));
            /** @var bool|float|int|string|null $criteriaValue */
            $criteriaValue = $arguments[0] ?? null;

            return $this->findBy([$field => $criteriaValue]);
        }

        if (str_starts_with($method, 'findOneBy')) {
            $field = lcfirst(substr($method, 9));
            /** @var bool|float|int|string|null $criteriaValue */
            $criteriaValue = $arguments[0] ?? null;

            return $this->findOneBy([$field => $criteriaValue]);
        }

        throw new \BadMethodCallException("Method $method does not exist");
    }
}
