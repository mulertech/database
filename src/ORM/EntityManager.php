<?php

namespace MulerTech\Database\ORM;

use _config\UpdateDatabaseMysql;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\PhpInterface\PhpDatabaseInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\EventManager\EventManager;
use PDOStatement;
use ReflectionException;

class EntityManager implements EntityManagerInterface
{
    /**
     * @var EmEngine Entity manager Engine
     */
    private EmEngine $emEngine;

    /**
     * @param PhpDatabaseInterface $pdm
     * @param DbMappingInterface $dbMapping
     * @param EventManager|null $eventManager
     */
    public function __construct(
        private PhpDatabaseInterface $pdm,
        private DbMappingInterface $dbMapping,
        private ?EventManager $eventManager = null
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
     * @param string|int|null $idOrWhere
     * @return Object|null
     * @throws ReflectionException
     */
    public function find(string $entity, string|int|null $idOrWhere = null): ?Object
    {
        return $this->emEngine->find($entity, $idOrWhere);
    }

    /**
     * Count the result of the request with the table $table and the $where conditions
     * @param class-string $entityName
     * @param string|null $where
     * @return int
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
        $searchValue = is_int($search) ? $search : "'$search'";

        // Build query condition with case sensitivity option
        $whereCondition = $matchCase ? "BINARY $column = $searchValue" : "$column = $searchValue";

        // Create and execute query
        $queryBuilder = new QueryBuilder($this->emEngine);
        $queryBuilder->select('*')
                    ->from($this->dbMapping->getTableName($entity))
                    ->where($whereCondition);

        $results = $this->emEngine->getQueryBuilderListResult($queryBuilder, $entity);

        // No results means the value is unique
        if (empty($results)) {
            return true;
        }

        // Filter results to handle MySQL numeric comparison edge cases
        $getter = 'get' . ucfirst($property);
        $matchingResults = array_filter($results, function($item) use ($getter, $search) {
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
        return !($id === null) && current($matchingResults)->getId() == $id;
    }

    /**
     * @param Object $entity
     */
    public function persist(Object $entity): void
    {
        $this->emEngine->persist($entity);
    }

    /**
     * @param Object $entity
     */
    public function remove(Object $entity): void
    {
        $this->emEngine->remove($entity);
    }

    /**
     * @throws ReflectionException
     */
    public function flush(): void
    {
        $this->emEngine->flush();
    }

}
