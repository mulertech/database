<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use Error;
use Exception;
use JsonException;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\ORM\Exception\HydrationException;
use MulerTech\Database\ORM\ValueProcessor\ValueProcessorManager;
use MulerTech\Database\ORM\ValueProcessor\EntityHydratorInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Class EntityHydrator
 *
 * EntityHydrator with metadata caching for improved performance
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityHydrator implements EntityHydratorInterface
{
    private MetadataCache $metadataCache;
    private ReflectionService $reflectionService;
    private ValueProcessorManager $valueProcessorManager;

    /**
     * @param DbMappingInterface $dbMapping
     * @param MetadataCache|null $metadataCache
     * @param ReflectionService|null $reflectionService
     */
    public function __construct(
        private readonly DbMappingInterface $dbMapping,
        ?MetadataCache $metadataCache = null,
        ?ReflectionService $reflectionService = null
    ) {
        $this->metadataCache = $metadataCache ?? new MetadataCache();
        $this->reflectionService = $reflectionService ?? new ReflectionService();
        $this->valueProcessorManager = new ValueProcessorManager($this);
    }


    /**
     * @param array<string, mixed> $data
     * @param class-string $entityName
     * @return object
     * @throws ReflectionException
     */
    public function hydrate(array $data, string $entityName): object
    {
        $entity = new $entityName();
        $reflection = new ReflectionClass($entityName);

        try {
            $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityName);

            foreach ($propertiesColumns as $property => $column) {
                if (!isset($data[$column]) || $this->isRelationProperty($entityName, $property)) {
                    continue;
                }

                $value = $data[$column];
                $processedValue = $this->processValue($entityName, $property, $value);

                // Validate nullable constraints
                if ($processedValue === null && !$this->isPropertyNullable($entityName, $property)) {
                    throw HydrationException::propertyCannotBeNull($property, $entityName);
                }

                $reflectionProperty = $reflection->getProperty($property);
                $reflectionProperty->setValue($entity, $processedValue);
            }
        } catch (Exception $e) {
            throw HydrationException::failedToHydrateEntity($entityName, $e);
        }

        return $entity;
    }

    /**
     * Extract data from an entity (reverse of hydration)
     *
     * @param object $entity
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public function extract(object $entity): array
    {
        $entityClass = $entity::class;
        $properties = $this->reflectionService->getNonStaticProperties($entityClass);
        $data = [];

        foreach ($properties as $propertyName => $property) {
            try {
                $data[$propertyName] = $property->getValue($entity);
            } catch (Error) {
                // Handle uninitialized properties
                $data[$propertyName] = null;
            }
        }

        return $data;
    }

    /**
     * @param class-string $entityClass
     * @param string $propertyName
     * @return ColumnType|null
     * @throws ReflectionException
     */
    private function getCachedColumnType(string $entityClass, string $propertyName): ?ColumnType
    {
        $cacheKey = 'column_type:' . $entityClass . ':' . $propertyName;

        $cached = $this->metadataCache->getPropertyMetadata($entityClass, $cacheKey);
        if ($cached instanceof ColumnType) {
            return $cached;
        }

        $columnType = $this->dbMapping->getColumnType($entityClass, $propertyName);

        if ($columnType !== null) {
            $this->metadataCache->setPropertyMetadata($entityClass, $cacheKey, $columnType);
        }

        return $columnType;
    }

    /**
     * Check if a property represents a relation (OneToOne, ManyToOne, etc.)
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     * @throws ReflectionException
     */
    private function isRelationProperty(string $entityClass, string $propertyName): bool
    {
        // Check if it's a OneToOne relation
        $oneToOneList = $this->dbMapping->getOneToOne($entityClass);
        if (!empty($oneToOneList) && isset($oneToOneList[$propertyName])) {
            return true;
        }

        // Check if it's a ManyToOne relation
        $manyToOneList = $this->dbMapping->getManyToOne($entityClass);
        return !empty($manyToOneList) && isset($manyToOneList[$propertyName]);
    }

    /**
     * Check if a property is nullable based on mapping or reflection
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    private function isPropertyNullable(string $entityClass, string $propertyName): bool
    {
        // First check DbMapping if available
        try {
            $nullable = $this->dbMapping->isNullable($entityClass, $propertyName);
            if ($nullable !== null) {
                return $nullable;
            }
        } catch (Exception) {
            // Fall through to reflection check
        }

        // Fall back to reflection-based check
        return $this->reflectionService->isPropertyNullable($entityClass, $propertyName);
    }

    /**
     * Process a value according to its type (simplified version)
     *
     * @param class-string $entityClass
     * @param string $propertyName
     * @param mixed $value
     * @return mixed
     * @throws JsonException
     */
    public function processValue(string $entityClass, string $propertyName, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        try {
            $columnType = $this->getCachedColumnType($entityClass, $propertyName);
            $property = $this->reflectionService->getProperty($entityClass, $propertyName);

            return $this->valueProcessorManager->processValue(
                $value,
                $property,
                $columnType
            );
        } catch (ReflectionException) {
            return $value;
        }
    }
}
