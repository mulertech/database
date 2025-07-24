<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

use MulerTech\Collections\Collection;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\State\StateManagerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Processor for OneToMany relations
 */
class OneToManyProcessor
{
    /**
     * @var array<class-string, array<string, mixed>|false> Cache for OneToMany mappings
     */
    private array $mappingCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StateManagerInterface $stateManager
    ) {
    }

    /**
     * Process OneToMany relations for an entity
     * @template T of object
     * @param object $entity The entity to process
     * @param ReflectionClass<T> $entityReflection Reflection of the entity class
     * @throws ReflectionException
     */
    public function process(object $entity, ReflectionClass $entityReflection): void
    {
        $entityName = $entity::class;
        $oneToManyList = $this->getOneToManyMapping($entityName);

        if ($oneToManyList === false) {
            return;
        }

        foreach ($oneToManyList as $property => $oneToMany) {
            $this->processProperty($entity, $entityReflection, $property);
        }
    }

    /**
     * Process a specific OneToMany property
     * @template T of object
     * @param object $entity
     * @param ReflectionClass<T> $entityReflection
     * @param string $property
     */
    private function processProperty(object $entity, ReflectionClass $entityReflection, string $property): void
    {
        if (!$entityReflection->hasProperty($property)) {
            return;
        }

        $reflectionProperty = $entityReflection->getProperty($property);
        if (!$reflectionProperty->isInitialized($entity)) {
            return;
        }

        $entities = $reflectionProperty->getValue($entity);

        if (!$entities instanceof Collection) {
            return;
        }

        foreach ($entities->items() as $relatedEntity) {
            if (is_object($relatedEntity) && $this->getId($relatedEntity) === null) {
                $this->stateManager->scheduleForInsertion($relatedEntity);
                $this->stateManager->addInsertionDependency($relatedEntity, $entity);
            }
        }
    }

    /**
     * Get entity ID
     */
    private function getId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }

        return null;
    }

    /**
     * Get OneToMany mapping for entity class
     * @param class-string $entityName
     * @return array<string, mixed>|false
     * @throws ReflectionException
     */
    private function getOneToManyMapping(string $entityName): array|false
    {
        if (!isset($this->mappingCache[$entityName])) {
            $mapping = $this->entityManager->getDbMapping()->getOneToMany($entityName);
            $this->mappingCache[$entityName] = is_array($mapping) ? $mapping : false;
        }

        return $this->mappingCache[$entityName];
    }

    /**
     * Clear caches
     */
    public function clear(): void
    {
        $this->mappingCache = [];
    }

    /**
     * Start new flush cycle
     */
    public function startFlushCycle(): void
    {
        $this->mappingCache = [];
    }
}
