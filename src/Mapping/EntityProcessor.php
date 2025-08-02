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
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
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
        $entityConfig = $this->extractEntityConfiguration($reflection, $className);

        if ($entityConfig === null) {
            return null;
        }

        // Store table mapping
        $this->tables[$className] = $entityConfig['tableName'];

        // Process properties for column mappings, relations, and foreign keys
        $propertyMappings = $this->processProperties($reflection);

        // Always define columns for the table, even if empty
        $this->columns[$className] = $propertyMappings['columns'];

        return $this->createEntityMetadata(
            $reflection,
            $entityConfig,
            $propertyMappings
        );
    }

    /**
     * Extract entity configuration from MtEntity attribute
     * @param ReflectionClass<object> $reflection
     * @param string $className
     * @return array{tableName: string, repository: ?class-string, autoIncrement: ?int}|null
     */
    private function extractEntityConfiguration(ReflectionClass $reflection, string $className): ?array
    {
        $entityAttrs = $reflection->getAttributes(MtEntity::class);

        if (empty($entityAttrs)) {
            return null;
        }

        $entityAttr = $entityAttrs[0]->newInstance();
        return [
            'tableName' => $entityAttr->tableName ?? $this->classNameToTableName($className),
            'repository' => $entityAttr->repository,
            'autoIncrement' => $entityAttr->autoIncrement,
        ];
    }

    /**
     * Process all properties to extract mappings and relationships
     * @param ReflectionClass<object> $reflection
     * @return array{
     *     columns: array<string, string>,
     *     foreignKeys: array<string, array{
     *      constraintName: string|null,
     *      column: string,
     *      referencedTable: string|null,
     *      referencedColumn: string|null,
     *      deleteRule: FkRule|null,
     *      updateRule: FkRule|null
     *      }>,
     *     relationships: array<string, array<string, array<string, mixed>>>
     * }
     */
    private function processProperties(ReflectionClass $reflection): array
    {
        $columns = [];
        $foreignKeys = [];
        $relationships = $this->initializeRelationships();

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();

            $this->processColumnMapping($property, $propertyName, $columns);
            $this->processForeignKeyMapping($property, $propertyName, $foreignKeys);
            $this->processRelationshipMappings($property, $propertyName, $relationships);
        }

        return [
            'columns' => $columns,
            'foreignKeys' => $foreignKeys,
            'relationships' => $relationships,
        ];
    }

    /**
     * Initialize empty relationships array
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function initializeRelationships(): array
    {
        return [
            'OneToOne' => [],
            'ManyToOne' => [],
            'OneToMany' => [],
            'ManyToMany' => [],
        ];
    }

    /**
     * Process column mapping from MtColumn attribute
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, string> $columns
     * @return void
     */
    private function processColumnMapping(ReflectionProperty $property, string $propertyName, array &$columns): void
    {
        $columnAttrs = $property->getAttributes(MtColumn::class);

        if (empty($columnAttrs)) {
            return;
        }

        $columnAttr = $columnAttrs[0]->newInstance();
        $columns[$propertyName] = $columnAttr->columnName ?? $propertyName;
    }

    /**
     * Process foreign key mapping from MtFk attribute
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, array{
     *     constraintName: string|null,
     *     column: string,
     *     referencedTable: string|null,
     *     referencedColumn: string|null,
     *     deleteRule: FkRule|null,
     *     updateRule: FkRule|null
     *     }> $foreignKeys
     * @param-out array<string, array{
     *     constraintName: string|null,
     *     column: string,
     *     referencedTable: string|null,
     *     referencedColumn: string|null,
     *     deleteRule: FkRule|null,
     *     updateRule: FkRule|null
     *     }> $foreignKeys
     * @return void
     */
    private function processForeignKeyMapping(
        ReflectionProperty $property,
        string $propertyName,
        array &$foreignKeys
    ): void {
        $fkAttrs = $property->getAttributes(MtFk::class);

        if (empty($fkAttrs)) {
            return;
        }

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

    /**
     * Process relationship mappings from Mt* relation attributes
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, array<string, array<string, mixed>>> $relationships
     * @return void
     */
    private function processRelationshipMappings(
        ReflectionProperty $property,
        string $propertyName,
        array &$relationships
    ): void {
        $this->processOneToOneRelation($property, $propertyName, $relationships);
        $this->processManyToOneRelation($property, $propertyName, $relationships);
        $this->processOneToManyRelation($property, $propertyName, $relationships);
        $this->processManyToManyRelation($property, $propertyName, $relationships);
    }

    /**
     * Process OneToOne relationship
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, array<string, array<string, mixed>>> $relationships
     * @return void
     */
    private function processOneToOneRelation(
        ReflectionProperty $property,
        string $propertyName,
        array &$relationships
    ): void {
        $oneToOneAttrs = $property->getAttributes(MtOneToOne::class);

        if (empty($oneToOneAttrs)) {
            return;
        }

        $relationAttr = $oneToOneAttrs[0]->newInstance();
        $relationships['OneToOne'][$propertyName] = [
            'targetEntity' => $relationAttr->targetEntity,
        ];
    }

    /**
     * Process ManyToOne relationship
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, array<string, array<string, mixed>>> $relationships
     * @return void
     */
    private function processManyToOneRelation(
        ReflectionProperty $property,
        string $propertyName,
        array &$relationships
    ): void {
        $manyToOneAttrs = $property->getAttributes(MtManyToOne::class);

        if (empty($manyToOneAttrs)) {
            return;
        }

        $relationAttr = $manyToOneAttrs[0]->newInstance();
        $relationships['ManyToOne'][$propertyName] = [
            'targetEntity' => $relationAttr->targetEntity,
        ];
    }

    /**
     * Process OneToMany relationship
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, array<string, array<string, mixed>>> $relationships
     * @return void
     */
    private function processOneToManyRelation(
        ReflectionProperty $property,
        string $propertyName,
        array &$relationships
    ): void {
        $oneToManyAttrs = $property->getAttributes(MtOneToMany::class);

        if (empty($oneToManyAttrs)) {
            return;
        }

        $relationAttr = $oneToManyAttrs[0]->newInstance();
        $relationships['OneToMany'][$propertyName] = [
            'targetEntity' => $relationAttr->targetEntity,
            'inverseJoinProperty' => $relationAttr->inverseJoinProperty ?? null,
        ];
    }

    /**
     * Process ManyToMany relationship
     * @param ReflectionProperty $property
     * @param string $propertyName
     * @param array<string, array<string, array<string, mixed>>> $relationships
     * @return void
     */
    private function processManyToManyRelation(
        ReflectionProperty $property,
        string $propertyName,
        array &$relationships
    ): void {
        $manyToManyAttrs = $property->getAttributes(MtManyToMany::class);

        if (empty($manyToManyAttrs)) {
            return;
        }

        $relationAttr = $manyToManyAttrs[0]->newInstance();
        $relationships['ManyToMany'][$propertyName] = [
            'targetEntity' => $relationAttr->targetEntity,
            'mappedBy' => $relationAttr->mappedBy ?? null,
            'joinProperty' => $relationAttr->joinProperty ?? null,
            'inverseJoinProperty' => $relationAttr->inverseJoinProperty ?? null,
        ];
    }

    /**
     * Create EntityMetadata instance with processed data
     * @param ReflectionClass<object> $reflection
     * @param array{tableName: string, repository: ?class-string, autoIncrement: ?int} $entityConfig
     * @param array{
     *     columns: array<string, string>,
     *     foreignKeys: array<string, array{
     *      constraintName: string|null,
     *      column: string,
     *      referencedTable: string|null,
     *      referencedColumn: string|null,
     *      deleteRule: FkRule|null,
     *      updateRule: FkRule|null
     *      }>,
     *     relationships: array<string, array<string, array<string, mixed>>>
     * } $propertyMappings
     * @return EntityMetadata
     */
    private function createEntityMetadata(
        ReflectionClass $reflection,
        array $entityConfig,
        array $propertyMappings
    ): EntityMetadata {
        return new EntityMetadata(
            className: $reflection->getName(),
            tableName: $entityConfig['tableName'],
            properties: $reflection->getProperties(),
            getters: array_filter(
                $reflection->getMethods(),
                static fn ($m) => str_starts_with($m->getName(), 'get') ||
                                 str_starts_with($m->getName(), 'is') ||
                                 str_starts_with($m->getName(), 'has')
            ),
            setters: array_filter(
                $reflection->getMethods(),
                static fn ($m) => str_starts_with($m->getName(), 'set')
            ),
            columns: $propertyMappings['columns'],
            foreignKeys: $propertyMappings['foreignKeys'],
            relationships: $propertyMappings['relationships'],
            repository: $entityConfig['repository'],
            autoIncrement: $entityConfig['autoIncrement']
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
