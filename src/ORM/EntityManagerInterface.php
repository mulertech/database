<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\EventManager\EventManager;
use ReflectionException;

/**
 * Interface EntityManagerInterface
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
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
     * @return DbMappingInterface
     */
    public function getDbMapping(): DbMappingInterface;

    /**
     * @param class-string $entity
     * @return EntityRepository
     */
    public function getRepository(string $entity): EntityRepository;

    /**
     * @return EventManager|null
     */
    public function getEventManager(): ?EventManager;

    /**
     * @param class-string $entity
     * @param string|int $idOrWhere
     * @return Object|null
     */
    public function find(string $entity, string|int $idOrWhere): ?object;

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
    ): bool;

    /**
     * @param Object $entity
     */
    public function persist(Object $entity): void;

    /**
     * @param Object $entity
     */
    public function remove(Object $entity): void;

    /**
     *
     */
    public function flush(): void;
}
