<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use InvalidArgumentException;
use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Mapping\DbMappingInterface;
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
     * @var EmEngine Entity manager Engine
     */
    private EmEngine $emEngine;

    /**
     * EntityManager constructor.
     *
     * @param PhpDatabaseInterface $pdm
     * @param DbMappingInterface $dbMapping
     * @param EventManager|null $eventManager
     */
    public function __construct(
        private readonly PhpDatabaseInterface $pdm,
        private readonly DbMappingInterface $dbMapping,
        private readonly ?EventManager $eventManager = null
    ) {
        $this->emEngine = new EmEngine($this);
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
     * @return DbMappingInterface
     */
    public function getDbMapping(): DbMappingInterface
    {
        return $this->dbMapping;
    }

    /**
     * @param class-string $entity
     * @return EntityRepository
     * @throws ReflectionException
     */
    public function getRepository(string $entity): EntityRepository
    {
        $repository = $this->dbMapping->getRepository($entity);
        return new $repository($this);
    }

    /**
     * @return EventManager|null
     */
    public function getEventManager(): ?EventManager
    {
        return $this->eventManager;
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
        // Ensure we never return false, only null or object
        return $result ?: null;
    }

    /**
     * Count the result of the request with the table $table and the $where conditions
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
     * @param class-string $entity
     * @param string $property Property to check for uniqueness
     * @param int|string $search Value to search for
     * @param int|string|null $id ID of entity to exclude (for update scenarios)
     * @param bool $matchCase Whether to perform case-sensitive comparison
     * @return bool True if the property value is unique
     * @throws ReflectionException
     */
    public function isUnique(
        string $entity,
        string $property,
        int|string $search,
        int|string|null $id = null,
        bool $matchCase = false
    ): bool {
        // Get column name and prepare search value
        $column = $this->dbMapping->getColumnName($entity, $property);

        // Create and execute query
        $tableName = $this->dbMapping->getTableName($entity);
        if ($tableName === null) {
            throw new InvalidArgumentException("Entity '$entity' does not have a valid table mapping.");
        }

        $queryBuilder = new QueryBuilder($this->emEngine)
            ->select('*')
            ->from($tableName)
            ->whereRaw('BINARY `' . $column . '` = :param0', [':param0' => $search]);

        $results = $this->emEngine->getQueryBuilderListResult($queryBuilder, $entity);

        // No results means the value is unique
        if (empty($results)) {
            return true;
        }

        // Filter results to handle MySQL numeric comparison edge cases
        $getter = 'get' . ucfirst($property);
        $matchingResults = array_filter($results, static function ($item) use ($getter, $search) {
            $value = $item->$getter();
            return !(is_numeric($value) && is_numeric($search) && $value != $search);
        });

        // If no matching results after filtering, it's unique
        if (empty($matchingResults)) {
            return true;
        }

        // If multiple matches, not unique
        if (count($matchingResults) > 1) {
            return false;
        }

        // One match with the same ID is still considered unique (update case)
        if (!method_exists(current($matchingResults), 'getId')) {
            return false; // No ID method means we can't check uniqueness by ID
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

}
