<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class EntityProcessor
{
    /**
     * @var array<class-string, string>
     */
    private array $tables = [];

    /**
     * @var array<class-string, array<string, string>>
     */
    private array $columns = [];

    /**
     * Load entities from a given path and store EntityMetadata
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

            // Only process classes that have MtEntity attribute
            $entityAttrs = $reflection->getAttributes(MtEntity::class);
            if (empty($entityAttrs)) {
                // Ignore classes without MtEntity attribute
                continue;
            }

            $this->buildEntityMetadata($reflection);
        }
    }

    /**
     * Process a single entity class and store its metadata
     * @param ReflectionClass<object> $reflection
     * @return bool True if the entity was processed, false if ignored
     */
    public function processEntityClass(ReflectionClass $reflection): bool
    {
        // Only process classes that have MtEntity attribute
        $entityAttrs = $reflection->getAttributes(MtEntity::class);
        if (empty($entityAttrs)) {
            // Ignore classes without MtEntity attribute
            return false;
        }

        $metadata = $this->buildEntityMetadata($reflection);
        return $metadata !== null;
    }

    /**
     * Build EntityMetadata for a class by name
     * @param class-string $className
     * @return EntityMetadata
     * @throws ReflectionException
     */
    public function buildEntityMetadataForClass(string $className): EntityMetadata
    {
        $reflection = new ReflectionClass($className);
        $metadata = $this->buildEntityMetadata($reflection);

        if ($metadata === null) {
            throw new RuntimeException(
                sprintf('Entity %s does not have MtEntity attribute', $className)
            );
        }

        return $metadata;
    }

    /**
     * Build EntityMetadata from ReflectionClass
     * @param ReflectionClass<object> $reflection
     * @return EntityMetadata|null
     */
    private function buildEntityMetadata(ReflectionClass $reflection): ?EntityMetadata
    {
        $className = $reflection->getName();

        // Get table name, repository and autoIncrement from MtEntity attribute
        $entityAttrs = $reflection->getAttributes(MtEntity::class);
        if (!empty($entityAttrs)) {
            $entityAttr = $entityAttrs[0]->newInstance();
            $tableName = $entityAttr->tableName ?? $this->classNameToTableName($className);
            $repository = $entityAttr->repository;
            $autoIncrement = $entityAttr->autoIncrement;
        } else {
            // Ignore entities without MtEntity attribute
            return null;
        }

        // Store table mapping
        $this->tables[$className] = $tableName;

        // Process properties for column mappings, relations, and foreign keys
        $columns = [];
        $foreignKeys = [];
        $relationships = [
            'OneToOne' => [],
            'ManyToOne' => [],
            'OneToMany' => [],
            'ManyToMany' => [],
        ];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            // Get column mapping from MtColumn attribute
            $columnAttrs = $property->getAttributes(MtColumn::class);
            if (!empty($columnAttrs)) {
                $columnAttr = $columnAttrs[0]->newInstance();
                $columnName = $columnAttr->columnName ?? $propertyName;
                $columns[$propertyName] = $columnName;
            }

            // Get foreign key mapping from MtFk attribute
            $fkAttrs = $property->getAttributes(MtFk::class);
            if (!empty($fkAttrs)) {
                $fkAttr = $fkAttrs[0]->newInstance();
                $foreignKeys[$propertyName] = [
                    'constraintName' => $fkAttr->constraintName,
                    'column' => $fkAttr->column ?? $propertyName,
                    'referencedTable' => $fkAttr->referencedTable,
                    'referencedColumn' => $fkAttr->referencedColumn,
                    'deleteRule' => $fkAttr->deleteRule,
                    'updateRule' => $fkAttr->updateRule,
                ];
            }

            // Get relationship mappings from Mt* relation attributes
            $oneToOneAttrs = $property->getAttributes(MtOneToOne::class);
            if (!empty($oneToOneAttrs)) {
                $relationAttr = $oneToOneAttrs[0]->newInstance();
                $relationships['OneToOne'][$propertyName] = [
                    'targetEntity' => $relationAttr->targetEntity,
                ];
            }

            $manyToOneAttrs = $property->getAttributes(MtManyToOne::class);
            if (!empty($manyToOneAttrs)) {
                $relationAttr = $manyToOneAttrs[0]->newInstance();
                $relationships['ManyToOne'][$propertyName] = [
                    'targetEntity' => $relationAttr->targetEntity,
                ];
            }

            $oneToManyAttrs = $property->getAttributes(MtOneToMany::class);
            if (!empty($oneToManyAttrs)) {
                $relationAttr = $oneToManyAttrs[0]->newInstance();
                $relationships['OneToMany'][$propertyName] = [
                    'targetEntity' => $relationAttr->targetEntity,
                    'inverseJoinProperty' => $relationAttr->inverseJoinProperty ?? null,
                ];
            }

            $manyToManyAttrs = $property->getAttributes(MtManyToMany::class);
            if (!empty($manyToManyAttrs)) {
                $relationAttr = $manyToManyAttrs[0]->newInstance();
                $relationships['ManyToMany'][$propertyName] = [
                    'targetEntity' => $relationAttr->targetEntity,
                    'mappedBy' => $relationAttr->mappedBy ?? null,
                    'joinProperty' => $relationAttr->joinProperty ?? null,
                    'inverseJoinProperty' => $relationAttr->inverseJoinProperty ?? null,
                ];
            }
        }

        // Always define columns for the table, even if empty
        $this->columns[$className] = $columns;

        return new EntityMetadata(
            className: $reflection->getName(),
            tableName: $tableName,
            properties: $reflection->getProperties(),
            getters: array_filter($reflection->getMethods(), static fn ($m) => str_starts_with($m->getName(), 'get') || str_starts_with($m->getName(), 'is') || str_starts_with($m->getName(), 'has')),
            setters: array_filter($reflection->getMethods(), static fn ($m) => str_starts_with($m->getName(), 'set')),
            columns: $columns,
            foreignKeys: $foreignKeys,
            relationships: $relationships,
            repository: $repository,
            autoIncrement: $autoIncrement
        );
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
        return $this->tables ?? [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getColumnsMapping(): array
    {
        return $this->columns ?? [];
    }

    /**
     * @param class-string $entityName
     * @return string|null
     */
    public function getTableName(string $entityName): ?string
    {
        return $this->tables[$entityName] ?? null;
    }
}
