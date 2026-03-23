<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\EventManager\EventManager;

/**
 * Class EntityManager.
 *
 * Main entity manager implementation for ORM operations.
 *
 * @author Sébastien Muler
 */
class EntityManager implements EntityManagerInterface
{
    private EmEngine $emEngine;

    private EntityHydrator $hydrator;

    public function __construct(
        private readonly PhpDatabaseInterface $pdm,
        private readonly MetadataRegistry $metadataRegistry,
        private readonly ?EventManager $eventManager = null,
    ) {
        $this->emEngine = new EmEngine($this, $metadataRegistry);
        $this->hydrator = new EntityHydrator($metadataRegistry);
    }

    public function getEmEngine(): EmEngine
    {
        return $this->emEngine;
    }

    public function getPdm(): PhpDatabaseInterface
    {
        return $this->pdm;
    }

    public function getMetadataRegistry(): MetadataRegistry
    {
        return $this->metadataRegistry;
    }

    /**
     * @param class-string $entity
     *
     * @throws \ReflectionException
     */
    public function getRepository(string $entity): EntityRepository
    {
        $metadata = $this->metadataRegistry->getEntityMetadata($entity);
        $repository = $metadata->getRepository();
        if (null === $repository) {
            throw new \InvalidArgumentException("No repository found for entity '$entity'. Ensure MtEntity attribute specifies a repository.");
        }
        /** @var EntityRepository $repoInstance */
        $repoInstance = new $repository($this);

        return $repoInstance;
    }

    public function getEventManager(): ?EventManager
    {
        return $this->eventManager;
    }

    public function getHydrator(): EntityHydrator
    {
        return $this->hydrator;
    }

    /**
     * @param class-string $entity
     *
     * @throws \ReflectionException
     */
    public function find(string $entity, string|int $idOrWhere): ?object
    {
        $result = $this->emEngine->find($entity, $idOrWhere);

        return $result ?: null;
    }

    /**
     * @param class-string $entityName
     *
     * @throws \ReflectionException
     */
    public function rowCount(string $entityName, ?string $where = null): int
    {
        return $this->emEngine->rowCount($entityName, $where);
    }

    /**
     * Checks if a property value is unique for an entity type, with option to exclude one entity by ID.
     *
     * @param class-string $entity
     *
     * @throws \ReflectionException
     */
    public function isUnique(
        string $entity,
        string $property,
        int|string $search,
        int|string|null $id = null,
        bool $matchCase = false,
    ): bool {
        $metadata = $this->metadataRegistry->getEntityMetadata($entity);
        $column = $metadata->getColumnName($property);
        $tableName = $metadata->tableName;

        if (null === $column) {
            throw new \InvalidArgumentException("Entity '$entity' does not have a valid table or column mapping.");
        }

        $binaryClause = $matchCase ? 'BINARY' : '';
        $queryBuilder = new QueryBuilder($this->emEngine)
            ->select('*')
            ->from($tableName)
            ->whereRaw($binaryClause.' `'.$column.'` = :param0', [':param0' => $search]);

        $results = $this->emEngine->getQueryBuilderListResult($queryBuilder, $entity);

        if (empty($results)) {
            return true;
        }

        if (count($results) > 1) {
            return false;
        }

        $firstResult = current($results);
        if (!method_exists($firstResult, 'getId')) {
            return false;
        }

        return null !== $id && $firstResult->getId() == $id;
    }

    public function persist(object $entity): void
    {
        $this->emEngine->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->emEngine->remove($entity);
    }

    /**
     * @throws \ReflectionException
     */
    public function flush(): void
    {
        $this->emEngine->flush();
    }

    public function merge(object $entity): object
    {
        return $this->emEngine->merge($entity);
    }

    public function detach(object $entity): void
    {
        $this->emEngine->detach($entity);
    }

    public function refresh(object $entity): void
    {
        $this->emEngine->refresh($entity);
    }

    public function clear(): void
    {
        $this->emEngine->clear();
    }
}
