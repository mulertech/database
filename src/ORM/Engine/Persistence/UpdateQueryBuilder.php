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

        $this->addPropertyUpdates($updateBuilder, $entity, $changes);
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
        if (empty($changes)) {
            return false;
        }

        $metadata = $this->metadataRegistry->getEntityMetadata($entity::class);
        $propertiesColumns = $metadata->getPropertiesColumns();

        // Since all relation properties also have MtColumn, we only need to check propertiesColumns
        foreach ($changes as $property => $propertyChange) {
            if (isset($propertiesColumns[$property])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add property updates to the query (handles both direct properties and relations)
     * Since all relation properties also have MtColumn attributes, this single method
     * handles all property updates including foreign keys.
     *
     * @param UpdateBuilder $updateBuilder
     * @param object $entity
     * @param array<string, PropertyChange> $changes
     * @throws ReflectionException
     */
    private function addPropertyUpdates(UpdateBuilder $updateBuilder, object $entity, array $changes): void
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
