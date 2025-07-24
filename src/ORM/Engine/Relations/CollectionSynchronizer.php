<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Database\ORM\DatabaseCollection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Manager for synchronizing database collections
 */
class CollectionSynchronizer
{
    /**
     * @var array<class-string, array<string, mixed>|false> Cache for OneToMany mappings
     */
    private array $oneToManyCache = [];

    /**
     * @var array<class-string, array<string, mixed>|false> Cache for ManyToMany mappings
     */
    private array $manyToManyCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager
    ) {
    }

    /**
     * Synchronize all DatabaseCollections after relation changes
     * @throws ReflectionException
     */
    public function synchronizeAllCollections(): void
    {
        $managedEntities = $this->stateManager->getManagedEntities();

        foreach ($managedEntities as $entity) {
            $this->synchronizeEntityCollections($entity);
        }
    }

    /**
     * Synchronize collections for a specific entity
     * @throws ReflectionException
     */
    public function synchronizeEntityCollections(object $entity): void
    {
        $entityName = $entity::class;
        $entityReflection = new ReflectionClass($entity);

        $this->synchronizeOneToManyCollections($entity, $entityReflection, $entityName);
        $this->synchronizeManyToManyCollections($entity, $entityReflection, $entityName);
    }

    /**
     * Synchronize OneToMany collections
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $entityName
     * @throws ReflectionException
     */
    private function synchronizeOneToManyCollections(
        object $entity,
        ReflectionClass $entityReflection,
        string $entityName
    ): void {
        /** @var class-string $entityName */
        $oneToManyList = $this->getOneToManyMapping($entityName);
        if ($oneToManyList === false) {
            return;
        }

        foreach ($oneToManyList as $property => $oneToMany) {
            $this->synchronizeCollectionProperty($entity, $entityReflection, $property);
        }
    }

    /**
     * Synchronize ManyToMany collections
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $entityName
     * @throws ReflectionException
     */
    private function synchronizeManyToManyCollections(
        object $entity,
        ReflectionClass $entityReflection,
        string $entityName
    ): void {
        /** @var class-string $entityName */
        $manyToManyList = $this->getManyToManyMapping($entityName);
        if ($manyToManyList === false) {
            return;
        }

        foreach ($manyToManyList as $property => $manyToMany) {
            $this->synchronizeCollectionProperty($entity, $entityReflection, $property);
        }
    }

    /**
     * Synchronize a specific collection property
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $property
     */
    private function synchronizeCollectionProperty(
        object $entity,
        ReflectionClass $entityReflection,
        string $property
    ): void {
        if (!$entityReflection->hasProperty($property)) {
            return;
        }

        $reflectionProperty = $entityReflection->getProperty($property);

        if (!$reflectionProperty->isInitialized($entity)) {
            return;
        }

        $collection = $reflectionProperty->getValue($entity);

        if ($collection instanceof DatabaseCollection) {
            $collection->synchronizeInitialState();
        }
    }

    /**
     * Get OneToMany mapping for entity class
     * @param class-string $entityName
     * @return array<string, mixed>|false
     * @throws ReflectionException
     */
    private function getOneToManyMapping(string $entityName): array|false
    {
        if (!isset($this->oneToManyCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getOneToMany($entityName);
            $this->oneToManyCache[$entityName] = is_array($mapping) ? $mapping : false;
        }

        return $this->oneToManyCache[$entityName];
    }

    /**
     * Get ManyToMany mapping for entity class
     * @param class-string $entityName
     * @return array<string, mixed>|false
     * @throws ReflectionException
     */
    private function getManyToManyMapping(string $entityName): array|false
    {
        if (!isset($this->manyToManyCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getManyToMany($entityName);
            $this->manyToManyCache[$entityName] = is_array($mapping) ? $mapping : false;
        }

        return $this->manyToManyCache[$entityName];
    }

    /**
     * Clear caches
     */
    public function clear(): void
    {
        $this->oneToManyCache = [];
        $this->manyToManyCache = [];
    }

    /**
     * Start new flush cycle
     */
    public function startFlushCycle(): void
    {
        $this->oneToManyCache = [];
        $this->manyToManyCache = [];
    }
}
