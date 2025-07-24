<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Collections\Collection;
use MulerTech\Database\Mapping\DbMappingInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Handles collection initialization for entities
 */
readonly class CollectionInitializer
{
    public function __construct(private DbMappingInterface $dbMapping)
    {
    }

    /**
     * Initialize collections for an entity
     *
     * @param object $entity
     * @param ReflectionClass<object> $reflection
     * @return void
     * @throws ReflectionException
     */
    public function initializeCollections(object $entity, ReflectionClass $reflection): void
    {
        $entityName = $entity::class;

        // Initialize OneToMany collections
        $oneToManyList = $this->dbMapping->getOneToMany($entityName);
        if (!empty($oneToManyList)) {
            foreach ($oneToManyList as $property => $oneToMany) {
                $this->initializeCollectionProperty($entity, $reflection, $property);
            }
        }

        // Initialize ManyToMany collections
        $manyToManyList = $this->dbMapping->getManyToMany($entityName);
        if (!empty($manyToManyList)) {
            foreach ($manyToManyList as $property => $manyToMany) {
                $this->initializeCollectionProperty($entity, $reflection, $property);
            }
        }
    }

    /**
     * Initialize a single collection property
     *
     * @param object $entity
     * @param ReflectionClass<object> $reflection
     * @param string $property
     * @return void
     */
    private function initializeCollectionProperty(object $entity, ReflectionClass $reflection, string $property): void
    {
        try {
            $reflectionProperty = $reflection->getProperty($property);

            if (!$reflectionProperty->isInitialized($entity)) {
                $reflectionProperty->setValue($entity, new DatabaseCollection());
                return;
            }

            $currentValue = $reflectionProperty->getValue($entity);
            if ($currentValue instanceof Collection && !($currentValue instanceof DatabaseCollection)) {
                $items = $currentValue->items();
                /** @var array<object> $objectItems */
                $objectItems = array_filter($items, static fn ($item): bool => is_object($item));
                $reflectionProperty->setValue($entity, new DatabaseCollection($objectItems));
                return;
            }

            if (!($currentValue instanceof DatabaseCollection)) {
                $reflectionProperty->setValue($entity, new DatabaseCollection());
            }
        } catch (ReflectionException) {
            // Property doesn't exist, ignore
        }
    }
}
