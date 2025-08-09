<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use InvalidArgumentException;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\EventManager\EventManager;
use ReflectionException;

/**
 * Class EntityManager
 *
 * Main entity manager implementation for ORM operations.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityManager implements EntityManagerInterface
{
    /**
     * @var EmEngine
     */
    private EmEngine $emEngine;

    private EntityHydrator $hydrator;

    /**
     * @param PhpDatabaseInterface $pdm
     * @param MetadataCache $metadataCache
     * @param EventManager|null $eventManager
     */
    public function __construct(
        private readonly PhpDatabaseInterface $pdm,
        private readonly MetadataCache $metadataCache,
        private readonly ?EventManager $eventManager = null
    ) {
        $this->emEngine = new EmEngine($this, $metadataCache);
        $this->hydrator = new EntityHydrator($metadataCache);
    }

    /**
     * @return EmEngine
     */
    public function getEmEngine(): EmEngine
    {
        return $this->emEngine;
    }

    /**
     * @return PhpDatabaseInterface
     */
    public function getPdm(): PhpDatabaseInterface
    {
        return $this->pdm;
    }

    /**
     * @return MetadataCache
     */
    public function getMetadataCache(): MetadataCache
    {
        return $this->metadataCache;
    }


    /**
     * @param class-string $entity
     * @return EntityRepository
     * @throws ReflectionException
     */
    public function getRepository(string $entity): EntityRepository
    {
        $metadata = $this->metadataCache->getEntityMetadata($entity);
        $repository = $metadata->getRepository();
        if ($repository === null) {
            throw new InvalidArgumentException("No repository found for entity '$entity'. Ensure MtEntity attribute specifies a repository.");
        }
        /** @var EntityRepository $repoInstance */
        $repoInstance = new $repository($this);
        return $repoInstance;
    }

    /**
     * @return EventManager|null
     */
    public function getEventManager(): ?EventManager
    {
        return $this->eventManager;
    }

    /**
     * @return EntityHydrator
     */
    public function getHydrator(): EntityHydrator
    {
        return $this->hydrator;
    }

    /**
     * @param class-string $entity
     * @param string|int $idOrWhere
     * @return object|null
     * @throws ReflectionException
     */
    public function find(string $entity, string|int $idOrWhere): ?object
    {
        $result = $this->emEngine->find($entity, $idOrWhere);
        return $result ?: null;
    }

    /**
     * @param class-string $entityName
     * @param string|null $where
     * @return int
     * @throws ReflectionException
     */
    public function rowCount(string $entityName, ?string $where = null): int
    {
        return $this->emEngine->rowCount($entityName, $where);
    }

    /**
     * Checks if a property value is unique for an entity type, with option to exclude one entity by ID.
     *
     * @param class-string $entity
     * @param string $property
     * @param int|string $search
     * @param int|string|null $id
     * @param bool $matchCase
     * @return bool
     * @throws ReflectionException
     */
    public function isUnique(
        string $entity,
        string $property,
        int|string $search,
        int|string|null $id = null,
        bool $matchCase = false
    ): bool {
        $metadata = $this->metadataCache->getEntityMetadata($entity);
        $column = $metadata->getColumnName($property);
        $tableName = $metadata->tableName;

        if ($column === null) {
            throw new InvalidArgumentException("Entity '$entity' does not have a valid table or column mapping.");
        }

        $queryBuilder = new QueryBuilder($this->emEngine)
            ->select('*')
            ->from($tableName)
            ->whereRaw('BINARY `' . $column . '` = :param0', [':param0' => $search]);

        $results = $this->emEngine->getQueryBuilderListResult($queryBuilder, $entity);

        if (empty($results)) {
            return true;
        }

        $getter = $metadata->getGetter($property) ?? 'get' . ucfirst($property);
        $matchingResults = array_filter($results, static function ($item) use ($getter, $search) {
            $value = $item->$getter();
            return !(is_numeric($value) && is_numeric($search) && $value != $search);
        });

        if (count($matchingResults) > 1) {
            return false;
        }

        if (!method_exists(current($matchingResults), 'getId')) {
            return false;
        }

        return !($id === null) && current($matchingResults)->getId() == $id;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function persist(object $entity): void
    {
        $this->emEngine->persist($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function remove(object $entity): void
    {
        $this->emEngine->remove($entity);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function flush(): void
    {
        $this->emEngine->flush();
    }

    /**
     * @param object $entity
     * @return object
     */
    public function merge(object $entity): object
    {
        return $this->emEngine->merge($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $this->emEngine->detach($entity);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function refresh(object $entity): void
    {
        $this->emEngine->refresh($entity);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->emEngine->clear();
    }
}
