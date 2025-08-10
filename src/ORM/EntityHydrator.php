<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use Error;
use Exception;
use JsonException;
use MulerTech\Database\Core\Cache\MetadataCache;
use MulerTech\Database\Mapping\ColumnMapping;
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
    private ColumnMapping $columnMapping;

    /**
     * @param MetadataCache $metadataCache
     */
    public function __construct(private readonly MetadataCache $metadataCache)
    {
        $this->valueProcessorManager = new ValueProcessorManager($this);
        $this->columnMapping = new ColumnMapping();
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
     * @throws JsonException
     */
    public function hydrate(array $data, string $entityName): object
    {
        $entity = new $entityName();
        $metadata = $this->metadataCache->getEntityMetadata($entityName);

        foreach ($metadata->getPropertiesColumns() as $property => $column) {
            if ($this->isRelationProperty($metadata, $property)) {
                continue;
            }

            // Skip properties not in the data array entirely (they weren't provided)
            if (!array_key_exists($column, $data)) {
                continue;
            }

            $value = $data[$column];
            $processedValue = $this->processValue($metadata, $property, $value);

            // Validate nullable constraints - let this exception bubble up directly
            if ($processedValue === null && !$this->isPropertyNullable($metadata, $property)) {
                throw HydrationException::propertyCannotBeNull($property, $entityName);
            }

            $setter = $metadata->getSetter($property);
            if ($setter === null) {
                throw new HydrationException(
                    "No setter defined for property '$property' in entity '$entityName'."
                );
            }

            $entity->$setter($processedValue);
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
            $getter = $metadata->getGetter($propertyName);
            if ($getter === null) {
                throw new HydrationException(
                    "No getter defined for property '$propertyName' in entity '$entityClass'."
                );
            }
            $data[$columnName] = $entity->$getter();
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
     * @throws ReflectionException
     */
    private function isPropertyNullable(EntityMetadata $metadata, string $propertyName): bool
    {
        return $this->columnMapping->isNullable($metadata->className, $propertyName) ?? true;
    }

    /**
     * @param EntityMetadata $metadata
     * @param string $propertyName
     * @param bool|float|int|string|null $value
     * @return mixed
     * @throws JsonException
     * @throws ReflectionException
     */
    public function processValue(
        EntityMetadata $metadata,
        string $propertyName,
        bool|float|int|string|null $value
    ): mixed {
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
