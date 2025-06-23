<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

use Exception;
use MulerTech\Database\Mapping\DbMappingInterface;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use ReflectionException;
use RuntimeException;

/**
 * Class UpdateProcessor
 *
 * Specialized processor for entity updates
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
readonly class UpdateProcessor
{
    /**
     * @param EntityManagerInterface $entityManager
     * @param DbMappingInterface $dbMapping
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DbMappingInterface $dbMapping
    ) {
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return void
     */
    public function process(object $entity, array $changes): void
    {
        $this->execute($entity, $changes);
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return void
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
            if (!$this->hasValidValues($entity, $changes)) {
                return;
            }

            $pdoStatement = $queryBuilder->getResult();

            $pdoStatement->execute();
            $pdoStatement->closeCursor();
        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to update entity %s: %s', $entity::class, $e->getMessage()),
                0,
                $e
            );
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
        } catch (Exception) {
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
                foreach ($allPropertiesColumns as $col) {
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
                } catch (Exception) {
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
                foreach ($allPropertiesColumns as $col) {
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
                } catch (Exception) {
                    // Continue with convention-based name
                }

                // If not found in explicit mappings, return the convention-based name
                return $foreignKeyColumn;
            }
        } catch (Exception) {
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
                $value = $extractedId ?? null;
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
                        $value = $extractedId ?? null;
                    }

                    $queryBuilder->setValue($foreignKeyColumn, $value);
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
        } catch (Exception) {
            // If we can't check, assume it exists to avoid silent failures
            return true;
        }
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $changes
     * @return bool
     * @throws ReflectionException
     */
    private function hasValidValues(object $entity, array $changes): bool
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
