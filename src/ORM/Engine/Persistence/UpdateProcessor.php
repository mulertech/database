<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use ReflectionException;
use RuntimeException;

/**
 * Specialized processor for entity updates
 *
 * @package MulerTech\Database\ORM\Engine\Persistence
 * @author Sébastien Muler
 */
class UpdateProcessor
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param DbMappingInterface $dbMapping
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DbMappingInterface $dbMapping
    ) {
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return void
     * @throws ReflectionException
     */
    public function process(object $entity, array $changes): void
    {
        $this->execute($entity, $changes);
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return void
     * @throws ReflectionException
     */
    public function execute(object $entity, array $changes): void
    {
        if (empty($changes)) {
            return;
        }

        // Verify entity has an ID before attempting update
        $entityId = $this->getId($entity);
        if ($entityId === null) {
            return;
        }

        // Verify entity exists in database before updating
        if (!$this->entityExists($entity)) {
            return;
        }

        try {
            $queryBuilder = $this->buildUpdateQuery($entity, $changes);

            // Verify that the query has actual SET clauses
            if (!$this->hasValidValues($queryBuilder, $entity, $changes)) {
                return;
            }

            $pdoStatement = $queryBuilder->getResult();

            $success = $pdoStatement->execute();
            $rowsAffected = $pdoStatement->rowCount();
            $pdoStatement->closeCursor();
        } catch (\Exception $e) {
            // Log error but don't fail - entity might have been deleted
        }
    }

    /**
     * @param array<object> $entities
     * @param array<int, array<string, mixed>> $allChanges
     * @return void
     * @throws ReflectionException
     * @todo Is this method necessary?
     */
    public function executeBatch(array $entities, array $allChanges): void
    {
        if (empty($entities)) {
            return;
        }

        $entitiesByType = $this->groupEntitiesByType($entities);

        foreach ($entitiesByType as $entityClass => $typeEntities) {
            $this->executeBatchForType($entityClass, $typeEntities, $allChanges);
        }
    }

    /**
     * Get the foreign key column name for a relation property
     *
     * @param class-string $entityClass
     * @param string $property
     * @return string|null
     */
    private function getForeignKeyColumn(string $entityClass, string $property): ?string
    {
        try {
            // First try direct column mapping (works for properties that map directly to columns)
            $directColumn = $this->dbMapping->getColumnName($entityClass, $property);
            if ($directColumn !== null) {
                return $directColumn;
            }
        } catch (\Exception $e) {
            // If direct mapping fails, try relation-based mapping
        }

        try {
            // CRITICAL FIX: Check if the property has a direct column mapping in getPropertiesColumns
            $allPropertiesColumns = $this->dbMapping->getPropertiesColumns($entityClass);

            if (isset($allPropertiesColumns[$property])) {
                return $allPropertiesColumns[$property];
            }

            // Check if it's a ManyToOne relation that should have a foreign key column
            $manyToOneList = $this->dbMapping->getManyToOne($entityClass);
            if (is_array($manyToOneList) && isset($manyToOneList[$property])) {
                // For ManyToOne, the foreign key column is typically property_id
                $foreignKeyColumn = $property . '_id';

                // Verify this column exists in the entity's column mappings
                foreach ($allPropertiesColumns as $prop => $col) {
                    if ($col === $foreignKeyColumn) {
                        return $col;
                    }
                }

                // Check if the property itself maps to a column (direct FK mapping)
                try {
                    $mappedColumn = $this->dbMapping->getColumnName($entityClass, $property);
                    if ($mappedColumn !== null) {
                        return $mappedColumn;
                    }
                } catch (\Exception $e) {
                    // Continue with convention-based name
                }

                // If not found in explicit mappings, return the convention-based name
                // This handles cases where the FK column isn't explicitly mapped to a property
                return $foreignKeyColumn;
            }

            $oneToOneList = $this->dbMapping->getOneToOne($entityClass);
            if (is_array($oneToOneList) && isset($oneToOneList[$property])) {
                // For OneToOne, the foreign key column is typically property_id
                $foreignKeyColumn = $property . '_id';

                // Verify this column exists in the entity's column mappings
                foreach ($allPropertiesColumns as $prop => $col) {
                    if ($col === $foreignKeyColumn) {
                        return $col;
                    }
                }

                // Check if the property itself maps to a column (direct FK mapping)
                try {
                    $mappedColumn = $this->dbMapping->getColumnName($entityClass, $property);
                    if ($mappedColumn !== null) {
                        return $mappedColumn;
                    }
                } catch (\Exception $e) {
                    // Continue with convention-based name
                }

                // If not found in explicit mappings, return the convention-based name
                return $foreignKeyColumn;
            }
        } catch (\Exception $e) {
            // If mapping fails, return null
            return null;
        }

        return null;
    }

    /**
     * @param object $entity
     * @return int|null
     */
    private function getId(object $entity): ?int
    {
        if (!method_exists($entity, 'getId')) {
            throw new RuntimeException(
                sprintf('The entity %s must have a getId method', $entity::class)
            );
        }

        return $entity->getId();
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return QueryBuilder
     * @throws ReflectionException
     */
    private function buildUpdateQuery(object $entity, array $changes): QueryBuilder
    {
        $tableName = $this->getTableName($entity::class);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->update($tableName);

        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);
        $hasUpdates = false;

        foreach ($propertiesColumns as $property => $column) {
            if (!isset($changes[$property])) {
                continue;
            }

            // Handle both array format [old, new] and [key => old, key => new] format
            $changeData = $changes[$property];
            $value = null;

            if (is_array($changeData)) {
                if (isset($changeData[1])) {
                    // Format: [old, new]
                    $value = $changeData[1];
                } elseif (isset($changeData['new'])) {
                    // Format: ['old' => old, 'new' => new]
                    $value = $changeData['new'];
                } else {
                    // Fallback: take the last value
                    $value = end($changeData);
                }
            } else {
                $value = $changeData;
            }

            // CRITICAL FIX: Handle serialized arrays from ChangeDetector
            if (is_array($value) && isset($value['__entity__'], $value['__id__'])) {
                // This is a serialized entity reference from ChangeDetector
                $value = $value['__id__'];
            } elseif (is_object($value)) {
                // This is an actual object, extract its ID
                $extractedId = $this->getId($value);
                if ($extractedId !== null) {
                    $value = $extractedId;
                } else {
                    $value = null;
                }
            }

            $queryBuilder->setValue($column, $value);
            $hasUpdates = true;
        }

        if (!$hasUpdates) {
            // Check for relation property changes that map to foreign key columns
            foreach ($changes as $property => $change) {
                // Skip if this property is already handled above
                if (isset($propertiesColumns[$property])) {
                    continue;
                }

                // Try to find a foreign key column for this relation property
                $foreignKeyColumn = $this->getForeignKeyColumn($entity::class, $property);
                if ($foreignKeyColumn !== null) {
                    // Handle both array format [old, new] and [key => old, key => new] format
                    $changeData = $change;
                    $value = null;

                    if (is_array($changeData)) {
                        if (isset($changeData[1])) {
                            // Format: [old, new]
                            $value = $changeData[1];
                        } elseif (isset($changeData['new'])) {
                            // Format: ['old' => old, 'new' => new]
                            $value = $changeData['new'];
                        } else {
                            // Fallback: take the last value
                            $value = end($changeData);
                        }
                    } else {
                        $value = $changeData;
                    }

                    // CRITICAL FIX: Handle serialized arrays from ChangeDetector
                    if (is_array($value) && isset($value['__entity__'], $value['__id__'])) {
                        // This is a serialized entity reference from ChangeDetector
                        $value = $value['__id__'];
                    } elseif (is_object($value)) {
                        // This is an actual object, extract its ID
                        $extractedId = $this->getId($value);
                        if ($extractedId !== null) {
                            $value = $extractedId;
                        } else {
                            $value = null;
                        }
                    } elseif ($value === null) {
                        // Keep null value
                    }

                    $queryBuilder->setValue($foreignKeyColumn, $value);
                    $hasUpdates = true;
                }
            }
        }

        $entityId = $this->getId($entity);
        if ($entityId === null) {
            throw new RuntimeException(
                sprintf('Cannot update entity %s without a valid ID', $entity::class)
            );
        }

        $queryBuilder->where(
            SqlOperations::equal('id', $queryBuilder->addNamedParameter($entityId))
        );

        return $queryBuilder;
    }

    /**
     * @param class-string $entityClass
     * @param array<object> $entities
     * @param array<int, array<string, mixed>> $allChanges
     * @return void
     * @throws ReflectionException
     */
    private function executeBatchForType(string $entityClass, array $entities, array $allChanges): void
    {
        $tableName = $this->getTableName($entityClass);
        $propertiesColumns = $this->getPropertiesColumns($entityClass, false);

        if (empty($propertiesColumns)) {
            return;
        }

        // For batch updates, we can use CASE WHEN or individual updates
        // Here we choose individual updates grouped in a transaction
        foreach ($entities as $entity) {
            $entityId = spl_object_id($entity);
            $changes = $allChanges[$entityId] ?? [];

            if (!empty($changes)) {
                $this->execute($entity, $changes);
            }
        }
    }

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $updates
     * @return int
     * @throws ReflectionException
     */
    public function updateByCriteria(string $entityClass, array $criteria, array $updates): int
    {
        $tableName = $this->getTableName($entityClass);
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder->update($tableName);

        foreach ($updates as $property => $value) {
            $column = $this->getColumnName($entityClass, $property);
            $queryBuilder->setValue($column, $queryBuilder->addNamedParameter($value));
        }

        $this->applyCriteria($queryBuilder, $entityClass, $criteria);

        $pdoStatement = $queryBuilder->getResult();
        $pdoStatement->execute();
        $rowCount = $pdoStatement->rowCount();
        $pdoStatement->closeCursor();

        return $rowCount;
    }

    /**
     * @param array<object> $entities
     * @return array<class-string, array<object>>
     */
    private function groupEntitiesByType(array $entities): array
    {
        $grouped = [];

        foreach ($entities as $entity) {
            $entityClass = $entity::class;
            if (!isset($grouped[$entityClass])) {
                $grouped[$entityClass] = [];
            }
            $grouped[$entityClass][] = $entity;
        }

        return $grouped;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param class-string $entityClass
     * @param array<string, mixed> $criteria
     * @return void
     * @throws ReflectionException
     */
    private function applyCriteria(QueryBuilder $queryBuilder, string $entityClass, array $criteria): void
    {
        $first = true;

        foreach ($criteria as $property => $value) {
            $column = $this->getColumnName($entityClass, $property);
            $condition = SqlOperations::equal($column, $queryBuilder->addNamedParameter($value));

            if ($first) {
                $queryBuilder->where($condition);
                $first = false;
            } else {
                $queryBuilder->andWhere($condition);
            }
        }
    }

    /**
     * @param class-string $entityName
     * @return string
     * @throws ReflectionException
     */
    private function getTableName(string $entityName): string
    {
        $tableName = $this->dbMapping->getTableName($entityName);

        if ($tableName === null) {
            throw new RuntimeException(
                sprintf('The entity %s is not mapped in the database', $entityName)
            );
        }

        return $tableName;
    }

    /**
     * @param class-string $entityName
     * @param bool $keepId
     * @return array<string, string>
     * @throws ReflectionException
     */
    private function getPropertiesColumns(string $entityName, bool $keepId = true): array
    {
        $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityName);

        if (!$keepId && isset($propertiesColumns['id'])) {
            unset($propertiesColumns['id']);
        }

        return $propertiesColumns;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    private function getColumnName(string $entityName, string $property): string
    {
        $columnName = $this->dbMapping->getColumnName($entityName, $property);

        if ($columnName === null) {
            throw new RuntimeException(
                sprintf('Column name not found for property %s in entity %s', $property, $entityName)
            );
        }

        return $columnName;
    }

    /**
     * Check if an entity exists in the database
     *
     * @param object $entity
     * @return bool
     */
    private function entityExists(object $entity): bool
    {
        try {
            $entityId = $this->getId($entity);
            if ($entityId === null) {
                return false;
            }

            $tableName = $this->getTableName($entity::class);

            $pdo = $this->entityManager->getPdm();
            $statement = $pdo->prepare("SELECT COUNT(*) FROM `$tableName` WHERE id = :id");
            $statement->execute(['id' => $entityId]);
            $count = (int) $statement->fetchColumn();
            $statement->closeCursor();

            return $count > 0;
        } catch (\Exception $e) {
            // If we can't check, assume it exists to avoid silent failures
            return true;
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return bool
     * @throws ReflectionException
     */
    private function hasValidValues(QueryBuilder $queryBuilder, object $entity, array $changes): bool
    {
        $propertiesColumns = $this->getPropertiesColumns($entity::class, false);
        $hasValues = false;

        // Check direct property to column mappings
        foreach ($propertiesColumns as $property => $column) {
            if (isset($changes[$property])) {
                $hasValues = true;
                break;
            }
        }

        // Check for relation properties that might have foreign key columns
        if (!$hasValues) {
            foreach ($changes as $property => $change) {
                // Skip if this property is already handled above
                if (isset($propertiesColumns[$property])) {
                    continue;
                }

                // Check if this is a relation property with a foreign key
                $foreignKeyColumn = $this->getForeignKeyColumn($entity::class, $property);
                if ($foreignKeyColumn !== null) {
                    $hasValues = true;
                    break;
                }
            }
        }

        return $hasValues;
    }
}
