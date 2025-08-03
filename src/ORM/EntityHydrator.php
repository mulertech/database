<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use Error;
use Exception;
use JsonException;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\ORM\Exception\HydrationException;
use MulerTech\Database\ORM\ValueProcessor\ValueProcessorManager;
use MulerTech\Database\ORM\ValueProcessor\EntityHydratorInterface;
use ReflectionException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityHydrator implements EntityHydratorInterface
{
    private ValueProcessorManager $valueProcessorManager;

    /**
     * @param MetadataCache $metadataCache
     */
    public function __construct(private readonly MetadataCache $metadataCache)
    {
        $this->valueProcessorManager = new ValueProcessorManager($this);
    }

    /**
     * @return MetadataCache
     */
    public function getMetadataCache(): MetadataCache
    {
        return $this->metadataCache;
    }

    /**
     * @param array<string, bool|float|int|string|null> $data
     * @param class-string $entityName
     * @return object
     * @throws ReflectionException
     */
    public function hydrate(array $data, string $entityName): object
    {
        $entity = new $entityName();
        $metadata = $this->metadataCache->getEntityMetadata($entityName);

        try {
            foreach ($metadata->getPropertiesColumns() as $property => $column) {
                if (!isset($data[$column]) || $this->isRelationProperty($metadata, $property)) {
                    continue;
                }

                $value = $data[$column];
                $processedValue = $this->processValue($metadata, $property, $value);

                // Validate nullable constraints
                if ($processedValue === null && !$this->isPropertyNullable($metadata, $property)) {
                    throw HydrationException::propertyCannotBeNull($property, $entityName);
                }

                $setter = $metadata->getSetter($property);
                if ($setter !== null) {
                    $entity->$setter($processedValue);
                    continue;
                }

                $entity->$property = $processedValue;
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
        $metadata = $this->metadataCache->getEntityMetadata($entityClass);
        $data = [];

        foreach ($metadata->getPropertiesColumns() as $propertyName => $columnName) {
            try {
                $getter = $metadata->getGetter($propertyName);
                if ($getter !== null) {
                    $data[$columnName] = $entity->$getter();
                    continue;
                }

                $data[$columnName] = $entity->$propertyName ?? null;
            } catch (Error|Exception) {
                $data[$columnName] = null;
            }
        }

        return $data;
    }

    /**
     * @param EntityMetadata $metadata
     * @param string $propertyName
     * @return ColumnType|null
     */
    private function getCachedColumnType(EntityMetadata $metadata, string $propertyName): ?ColumnType
    {
        $cacheKey = 'column_type:' . $metadata->className . ':' . $propertyName;
        $cached = $this->metadataCache->getPropertyMetadata($metadata->className, $cacheKey);
        if ($cached instanceof ColumnType) {
            return $cached;
        }

        $columnType = $metadata->getColumnType($propertyName);

        if ($columnType !== null) {
            $this->metadataCache->setPropertyMetadata($metadata->className, $cacheKey, $columnType);
        }

        return $columnType;
    }

    /**
     * Check if a property represents a relation (OneToOne, ManyToOne, etc.)
     *
     * @param EntityMetadata $metadata
     * @param string $propertyName
     * @return bool
     */
    private function isRelationProperty(EntityMetadata $metadata, string $propertyName): bool
    {
        return array_any(
            ['OneToOne', 'ManyToOne'],
            static fn ($type) => isset($metadata->getRelationsByType($type)[$propertyName])
        );
    }

    /**
     * Check if a property is nullable based on metadata
     *
     * @param EntityMetadata $metadata
     * @param string $propertyName
     * @return bool
     */
    private function isPropertyNullable(EntityMetadata $metadata, string $propertyName): bool
    {
        // Defensive: check if property exists and has nullable info
        $property = $metadata->getProperty($propertyName);
        if ($property && $property->getType() !== null) {
            return $property->getType()->allowsNull();
        }
        return true;
    }

    /**
     * @param EntityMetadata $metadata
     * @param string $propertyName
     * @param bool|float|int|string|null $value
     * @return array<mixed>|bool|float|int|object|string|null
     * @throws JsonException
     */
    public function processValue(
        EntityMetadata $metadata,
        string $propertyName,
        bool|float|int|string|null $value
    ): array|bool|float|int|object|string|null {
        if ($value === null) {
            return null;
        }

        $columnType = $this->getCachedColumnType($metadata, $propertyName);
        $property = $metadata->getProperty($propertyName);

        return $this->valueProcessorManager->processValue(
            $value,
            $property,
            $columnType
        );
    }
}
