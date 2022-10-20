<?php

namespace mtphp\Database\ORM;

use _config\UpdateDatabaseMysql;
use Exception;
use mtphp\Database\Mapping\DbMappingInterface;
use mtphp\Database\PhpInterface\PhpDatabaseInterface;
use mtphp\Entity\Entity;
use mtphp\EventManager\EventManagerInterface;
use mtphp\HttpRequest\Session\Session;
use PDOStatement;

class EntityManager implements EntityManagerInterface
{

    /**
     * @var PhpDatabaseInterface PhpDatabaseManager
     */
    private $pdm;
    /**
     * @var DbMappingInterface DB Mapping
     */
    private $dbMapping;
    /**
     * @var EventManagerInterface|null Event Manager
     */
    private $eventManager;
    /**
     * @var EmEngine Entity manager Engine
     */
    private $emEngine;

    /**
     * @param PhpDatabaseInterface $pdm
     * @param DbMappingInterface $dbMapping
     * @param EventManagerInterface|null $eventManager
     */
    public function __construct(
        PhpDatabaseInterface $pdm,
        DbMappingInterface $dbMapping,
        EventManagerInterface $eventManager = null
    ) {
        $this->pdm = $pdm;
        $this->dbMapping = $dbMapping;
        $this->eventManager = $eventManager;
        $this->emEngine = new EmEngine($this, new UpdateDatabaseMysql($this, new Session()));
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
     * @param Entity $entity
     * @return EntityRepository
     */
    public function getRepository(Entity $entity): EntityRepository
    {
        $repository = $this->dbMapping->getRepository($entity);
        return new $repository($this);
    }

    /**
     * @return EventManagerInterface|null
     */
    public function getEventManager(): ?EventManagerInterface
    {
        return $this->eventManager;
    }

    /**
     * @param $entity
     * @param string|null $idorwhere
     * @return Entity|null
     */
    public function find($entity, ?string $idorwhere = null): ?Entity
    {
        return $this->emEngine->find($entity, $idorwhere);
    }

    /**
     * @param string $table
     * @param array|null $cells
     * @param string|null $orderfor
     * @param string|null $orderby
     * @param string|null $idorwhere
     * @param int|null $limit
     * @param int|null $page
     * @param string $sort
     * @param string|null $request
     * @param string|null $join
     * @return array|bool|mixed|PDOStatement|string|null
     */
    public function read(
        string $table,
        ?array $cells = null,
        ?string $orderfor = null,
        ?string $orderby = null,
        ?string $idorwhere = null,
        ?int $limit = null,
        ?int $page = null,
        string $sort = "default",
        ?string $request = null,
        ?string $join = null
    ) {
        return $this->emEngine->read(
            $table,
            $cells,
            $orderfor,
            $orderby,
            $idorwhere,
            $limit,
            $page,
            $sort,
            $request,
            $join
        );
    }

    /**
     * Count the result of the request with the table $table and the $where conditions
     * @param string $table
     * @param string|null $where
     * @return int
     */
    public function rowsCount(string $table, ?string $where = null): int
    {
        return $this->read($table, null, null, null, $where, null, null, 'count');
    }

    /**
     * Check if this item exists, with this $entity, $column and WHERE $search.
     * The search must exclude itself with its $id (if given).
     * @param string $entity
     * @param string $column
     * @param string|int $search
     * @param int|string|null $id
     * @param bool $matchCase
     * @return bool
     */
    public function isUnique(string $entity, string $column, $search, $id = null, bool $matchCase = false): bool
    {
        //search
        if (is_numeric($search)) {
            $item = $this->find($entity, "$column=$search");
        } else {
            $item = $this->find($entity, $column . '=\'' . $search . '\'');
        }
        if (is_null($item)) {
            return true;
        }
        if ($id === null) {
            //Sometimes Mysql sends a record which is not exactly the same as the search (for Mysql 898709919412651836=898709919412651837)...
            //A PHP comparison is required ($item->$column() !== $search)
            if ($matchCase || is_int($item->$column())) {
                return $item->$column() !== $search;
            }
            return strtolower($item->$column()) !== strtolower($search);
        }

        //ID can be an integer OR a UUID
        return $item->id() == $id;
    }

    /**
     * @param Entity $entity
     */
    public function persist(Entity $entity): void
    {
        $this->emEngine->persist($entity);
    }

    /**
     * @param Entity $entity
     */
    public function remove(Entity $entity): void
    {
        $this->emEngine->remove($entity);
    }

    /**
     *
     */
    public function flush(): void
    {
        $this->emEngine->flush();
    }

}