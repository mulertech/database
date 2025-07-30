<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Handles entity loading and processing operations
 */
class EntityProcessor
{
    /** @var array<class-string, string> $tables */
    private array $tables = [];
    /** @var array<string, array<string, string>> $columns */
    private array $columns = [];

    /**
     * Load entities from a given path
     * @param string $entitiesPath
     * @return void
     */
    public function loadEntities(string $entitiesPath): void
    {
        $classNames = Php::getClassNames($entitiesPath, true);

        foreach ($classNames as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $this->processEntityClass($reflection);
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return void
     */
    public function processEntityClass(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();

        // Get table name from MtEntity attribute
        $entityAttrs = $reflection->getAttributes(MtEntity::class);
        if (!empty($entityAttrs)) {
            $entityAttr = $entityAttrs[0]->newInstance();
            $tableName = $entityAttr->tableName ?? $this->classNameToTableName($className);
            $this->tables[$className] = $tableName;

            // Process properties for column mappings
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();

                // Get column mapping from MtColumn attribute
                $columnAttrs = $property->getAttributes(MtColumn::class);
                if (!empty($columnAttrs)) {
                    $columnAttr = $columnAttrs[0]->newInstance();
                    $columnName = $columnAttr->columnName ?? $propertyName;
                    $this->columns[$className][$propertyName] = $columnName;
                }
            }
        }
    }

    /**
     * Convert class name to table name (basic implementation)
     * @param string $className
     * @return string
     */
    public function classNameToTableName(string $className): string
    {
        /** @var class-string $className */
        $reflection = new ReflectionClass($className);
        $shortName = $reflection->getShortName();

        // Convert CamelCase to snake_case
        $converted = preg_replace('/([a-z])([A-Z])/', '$1_$2', $shortName);
        return strtolower($converted ?: $shortName);
    }

    /**
     * @param class-string $entityName
     * @return MtEntity|null
     * @throws ReflectionException
     */
    public function getMtEntity(string $entityName): ?MtEntity
    {
        $entity = Php::getInstanceOfClassAttributeNamed($entityName, MtEntity::class);
        return $entity instanceof MtEntity ? $entity : null;
    }

    /**
     * @param class-string $entityName
     * @return class-string|null
     * @throws ReflectionException
     */
    public function getRepository(string $entityName): ?string
    {
        $mtEntity = $this->getMtEntity($entityName);

        if (is_null($mtEntity)) {
            throw new RuntimeException("The MtEntity mapping is not implemented into the $entityName class.");
        }

        return $mtEntity->repository;
    }

    /**
     * @param class-string $entityName
     * @return int|null
     * @throws ReflectionException
     */
    public function getAutoIncrement(string $entityName): ?int
    {
        return $this->getMtEntity($entityName)?->autoIncrement;
    }

    /**
     * @return array<class-string, string>
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getColumnsMapping(): array
    {
        return $this->columns;
    }

    /**
     * @param class-string $entityName
     * @return string|null
     */
    public function getTableName(string $entityName): ?string
    {
        return $this->tables[$entityName] ?? null;
    }

    /**
     * Initialize columns for a specific entity if not already done
     * @param class-string $entityName
     * @param ColumnMapping $columnMapping
     * @return void
     * @throws ReflectionException
     */
    public function initializeColumns(string $entityName, ColumnMapping $columnMapping): void
    {
        if (!isset($this->columns[$entityName])) {
            $result = [];
            foreach ($columnMapping->getMtColumns($entityName) as $property => $mtColumn) {
                $result[$property] = $mtColumn->columnName ?? $property;
            }

            $this->columns[$entityName] = $result;
        }
    }
}
