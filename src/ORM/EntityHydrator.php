<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use Exception;
use MulerTech\Database\Mapping\ColumnMapping;
use MulerTech\Database\Mapping\EntityMetadata;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\ORM\Exception\HydrationException;
use MulerTech\Database\ORM\ValueProcessor\EntityHydratorInterface;
use MulerTech\Database\ORM\ValueProcessor\ValueProcessorManager;

/**
 * @author Sébastien Muler
 */
class EntityHydrator implements EntityHydratorInterface
{
    private ValueProcessorManager $valueProcessorManager;
    private ColumnMapping $columnMapping;

    public function __construct(private readonly MetadataRegistry $metadataRegistry)
    {
        $this->valueProcessorManager = new ValueProcessorManager();
        $this->columnMapping = new ColumnMapping();
    }

    public function getMetadataRegistry(): MetadataRegistry
    {
        return $this->metadataRegistry;
    }

    /**
     * @param array<string, bool|float|int|string|null> $data
     * @param class-string                              $entityName
     *
     * @throws \ReflectionException
     * @throws \JsonException
     */
    public function hydrate(array $data, string $entityName): object
    {
        $entity = new $entityName();
        $metadata = $this->metadataRegistry->getEntityMetadata($entityName);

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
            if (null === $processedValue && !$this->isPropertyNullable($metadata, $property)) {
                throw HydrationException::propertyCannotBeNull($property, $entityName);
            }

            $setter = $metadata->getSetter($property);
            if (null === $setter) {
                throw new HydrationException("No setter defined for property '$property' in entity '$entityName'.");
            }

            $entity->$setter($processedValue);
        }

        return $entity;
    }

    /**
     * Extract data from an entity (reverse of hydration).
     *
     * @return array<string, mixed>
     *
     * @throws \ReflectionException
     */
    public function extract(object $entity): array
    {
        $entityClass = $entity::class;
        $metadata = $this->metadataRegistry->getEntityMetadata($entityClass);
        $data = [];

        foreach ($metadata->getPropertiesColumns() as $propertyName => $columnName) {
            $getter = $metadata->getGetter($propertyName);
            if (null === $getter) {
                throw new HydrationException("No getter defined for property '$propertyName' in entity '$entityClass'.");
            }
            $data[$columnName] = $entity->$getter();
        }

        return $data;
    }

    private function getColumnType(EntityMetadata $metadata, string $propertyName): ?ColumnType
    {
        return $metadata->getColumnType($propertyName);
    }

    /**
     * Check if a property represents a relation (OneToOne, ManyToOne, etc.).
     */
    private function isRelationProperty(EntityMetadata $metadata, string $propertyName): bool
    {
        return $metadata->hasRelation($propertyName);
    }

    /**
     * Check if a property is nullable based on metadata.
     *
     * @throws \ReflectionException
     */
    private function isPropertyNullable(EntityMetadata $metadata, string $propertyName): bool
    {
        return $this->columnMapping->isNullable($metadata->className, $propertyName) ?? true;
    }

    /**
     * @throws \JsonException
     * @throws \ReflectionException
     */
    public function processValue(
        EntityMetadata $metadata,
        string $propertyName,
        bool|float|int|string|null $value,
    ): mixed {
        if (null === $value) {
            return null;
        }

        $columnType = $this->getColumnType($metadata, $propertyName);

        return $this->valueProcessorManager->processValue(
            $value,
            $columnType,
            $metadata,
            $propertyName
        );
    }
}
