<?php

namespace mtphp\Database\ORM;

use mtphp\Database\Mapping\DbMappingInterface;
use mtphp\Database\PhpInterface\PhpDatabaseInterface;
use mtphp\Entity\Entity;
use mtphp\EventManager\EventManagerInterface;
use PDOStatement;

interface EntityManagerInterface
{
    /**
     * @return EmEngine
     */
    public function getEmEngine(): EmEngine;

    /**
     * @return PhpDatabaseInterface
     */
    public function getPdm(): PhpDatabaseInterface;

    /**
     * @param Entity $entity
     * @return EntityRepository
     */
    public function getRepository(Entity $entity): EntityRepository;

    /**
     * @return EventManagerInterface|null
     */
    public function getEventManager(): ?EventManagerInterface;

    /**
     * @return DbMappingInterface
     */
    public function getDbMapping(): DbMappingInterface;

    /**
     * @param $entity
     * @param string|null $idorwhere
     * @return Entity|null
     */
    public function find($entity, ?string $idorwhere = null): ?Entity;

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
    );

    /**
     * @param Entity $entity
     */
    public function persist(Entity $entity): void;

    /**
     * @param Entity $entity
     */
    public function remove(Entity $entity): void;

    /**
     *
     */
    public function flush(): void;
}