<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Database\Interface\PhpDatabaseInterface;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\EventManager\EventManager;

/**
 * Interface EntityManagerInterface.
 *
 * @author Sébastien Muler
 */
interface EntityManagerInterface
{
    public function getEmEngine(): EmEngine;

    public function getPdm(): PhpDatabaseInterface;

    public function getMetadataRegistry(): MetadataRegistry;

    /**
     * @param class-string $entity
     */
    public function getRepository(string $entity): EntityRepository;

    public function getEventManager(): ?EventManager;

    /**
     * @param class-string $entity
     */
    public function find(string $entity, string|int $idOrWhere): ?object;

    /**
     * Checks if a property value is unique for an entity type, with option to exclude one entity by ID.
     *
     * @param class-string    $entity
     * @param string          $property  Property to check for uniqueness
     * @param int|string      $search    Value to search for
     * @param int|string|null $id        ID of entity to exclude (for update scenarios)
     * @param bool            $matchCase Whether to perform case-sensitive comparison
     *
     * @return bool True if the property value is unique
     *
     * @throws \ReflectionException
     */
    public function isUnique(
        string $entity,
        string $property,
        int|string $search,
        int|string|null $id = null,
        bool $matchCase = false,
    ): bool;

    public function persist(object $entity): void;

    public function remove(object $entity): void;

    public function flush(): void;

    public function merge(object $entity): object;

    public function detach(object $entity): void;

    public function refresh(object $entity): void;

    /**
     * Clear all entities from the entity manager.
     */
    public function clear(): void;

    /**
     * Get row count for an entity type.
     *
     * @param class-string $entityName
     */
    public function rowCount(string $entityName, ?string $where = null): int;

    public function getHydrator(): EntityHydrator;
}
