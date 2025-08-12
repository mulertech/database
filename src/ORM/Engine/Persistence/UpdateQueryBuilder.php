<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\ORM\PropertyChange;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Builder\UpdateBuilder;
use ReflectionException;
use RuntimeException;

/**
 * Builds UPDATE queries for entities
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class UpdateQueryBuilder
{
    private UpdateValueProcessor $valueProcessor;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MetadataRegistry $metadataRegistry,
        private UpdateEntityValidator $validator
    ) {
        $this->valueProcessor = new UpdateValueProcessor();
    }

    /**
     * Build UPDATE query for entity changes
     *
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @return UpdateBuilder
     * @throws Exception
     */
    public function buildQuery(object $entity, array $changes): UpdateBuilder
    {
        $tableName = $this->metadataRegistry->getEntityMetadata($entity::class)->tableName;
        $updateBuilder = new QueryBuilder($this->entityManager->getEmEngine())->update($tableName);

        $this->addDirectPropertyUpdates($updateBuilder, $entity, $changes);
        $this->addRelationPropertyUpdates($updateBuilder, $entity, $changes);
        $this->addWhereClause($updateBuilder, $entity);

        return $updateBuilder;
    }

    /**
     * Check if there are valid values to update
     *
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @return bool
     * @throws ReflectionException
     */
    public function hasValidUpdates(object $entity, array $changes): bool
    {
        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);

        // Check direct property mappings
        foreach ($propertiesColumns as $property => $column) {
            if (isset($changes[$property])) {
                return true;
            }
        }

        // Check relation properties with foreign keys
        foreach ($changes as $property => $propertyChange) {
            if (isset($propertiesColumns[$property])) {
                continue;
            }

            if ($this->getForeignKeyColumn($entity::class, $property) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add direct property to column updates
     *
     * @param UpdateBuilder $updateBuilder
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @throws ReflectionException
     */
    private function addDirectPropertyUpdates(UpdateBuilder $updateBuilder, object $entity, array $changes): void
    {
        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);

        foreach ($propertiesColumns as $property => $column) {
            if (!isset($changes[$property])) {
                continue;
            }

            $newValue = $changes[$property]->newValue;
            if ($this->valueProcessor->isProcessableValue($newValue)) {
                /** @var array<string, mixed>|object|string|null $newValue */
                $updateBuilder->set($column, $this->valueProcessor->extractForeignKeyId($newValue));
            }
        }
    }

    /**
     * Add relation property updates (foreign keys)
     *
     * @param UpdateBuilder $updateBuilder
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @throws ReflectionException
     */
    private function addRelationPropertyUpdates(UpdateBuilder $updateBuilder, object $entity, array $changes): void
    {
        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);

        foreach ($changes as $property => $propertyChange) {
            // Skip direct properties (already handled)
            if (isset($propertiesColumns[$property])) {
                continue;
            }

            $foreignKeyColumn = $this->getForeignKeyColumn($entity::class, $property);
            if ($foreignKeyColumn === null) {
                continue;
            }

            $newValue = $propertyChange->newValue;
            if ($this->valueProcessor->isProcessableValue($newValue)) {
                /** @var array<string, mixed>|object|string|null $newValue */
                $updateBuilder->set($foreignKeyColumn, $this->valueProcessor->extractForeignKeyId($newValue));
            }
        }
    }

    /**
     * Add WHERE clause with entity ID
     *
     * @param UpdateBuilder $updateBuilder
     * @param object $entity
     */
    private function addWhereClause(UpdateBuilder $updateBuilder, object $entity): void
    {
        $entityId = $this->validator->getEntityId($entity);
        if ($entityId === null) {
            throw new RuntimeException(
                sprintf('Cannot update entity %s without a valid ID', $entity::class)
            );
        }

        $updateBuilder->where('id', $entityId);
    }

    /**
     * Get foreign key column for a relation property
     *
     * @param class-string $entityClass
     * @param string $property
     * @return string|null
     */
    private function getForeignKeyColumn(string $entityClass, string $property): ?string
    {
        try {
            $metadata = $this->metadataRegistry->getEntityMetadata($entityClass);

            // Try direct column mapping first
            $directColumn = $metadata->getColumnName($property);
            if ($directColumn !== null) {
                return $directColumn;
            }

            // Check properties columns mapping
            $allPropertiesColumns = $this->metadataRegistry->getEntityMetadata($entityClass)->getPropertiesColumns();
            if (isset($allPropertiesColumns[$property])) {
                return $allPropertiesColumns[$property];
            }

            // Check ManyToOne relations
            $manyToOneList = $metadata->getRelationsByType('ManyToOne');
            if (isset($manyToOneList[$property])) {
                return $metadata->getColumnName($property);
            }

            // Check OneToOne relations
            $oneToOneList = $metadata->getRelationsByType('OneToOne');
            if (isset($oneToOneList[$property])) {
                return $metadata->getColumnName($property);
            }

        } catch (Exception) {
            return null;
        }

        return null;
    }

    /**
     * Get properties to columns mapping
     *
     * @param class-string $entityName
     * @param bool $keepId
     * @return array<string, string>
     * @throws ReflectionException|Exception
     */
    private function getPropertiesColumns(string $entityName, bool $keepId = true): array
    {
        $propertiesColumns = $this->metadataRegistry->getEntityMetadata($entityName)->getPropertiesColumns();

        if (!$keepId && isset($propertiesColumns['id'])) {
            unset($propertiesColumns['id']);
        }

        return $propertiesColumns;
    }
}
