<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use ReflectionClass;
use ReflectionProperty;

/**
 * Processes entity classes and extracts metadata
 */
final class EntityProcessor
{
    /**
     * Process entity class and extract metadata
     *
     * @param ReflectionClass<object> $reflection
     * @return EntityMetadata
     */
    public function processEntity(ReflectionClass $reflection): EntityMetadata
    {
        $className = $reflection->getName();

        $metadata = new EntityMetadata();
        $metadata->className = $className;

        // Extract table name
        $metadata->tableName = $this->extractTableName($reflection);

        // Extract column mappings
        $metadata->columns = $this->extractColumnMappings($reflection);

        // Extract foreign keys
        $metadata->foreignKeys = $this->extractForeignKeys($reflection);

        // Extract relationships
        $metadata->relationships = $this->extractRelationships($reflection);

        return $metadata;
    }

    /**
     * Extract table name from entity
     * @param ReflectionClass<object> $reflection
     */
    private function extractTableName(ReflectionClass $reflection): string
    {
        $entityAttrs = $reflection->getAttributes(MtEntity::class);

        if (!empty($entityAttrs)) {
            $entityAttr = $entityAttrs[0]->newInstance();
            return $entityAttr->tableName ?? $this->classNameToTableName($reflection->getName());
        }

        return $this->classNameToTableName($reflection->getName());
    }

    /**
     * Extract column mappings from entity properties
     * @param ReflectionClass<object> $reflection
     * @return array<string, string>
     */
    private function extractColumnMappings(ReflectionClass $reflection): array
    {
        $columns = [];

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            $columnAttrs = $property->getAttributes(MtColumn::class);

            if (!empty($columnAttrs)) {
                $columnAttr = $columnAttrs[0]->newInstance();
                $columnName = $columnAttr->columnName ?? $propertyName;
                $columns[$propertyName] = $columnName;
            }
        }

        return $columns;
    }

    /**
     * Extract foreign key relationships
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    private function extractForeignKeys(ReflectionClass $reflection): array
    {
        $foreignKeys = [];

        foreach ($reflection->getProperties() as $property) {
            $foreignKeyAttrs = $property->getAttributes(MtFk::class);

            if (!empty($foreignKeyAttrs)) {
                $foreignKeyAttr = $foreignKeyAttrs[0]->newInstance();
                $foreignKeys[$property->getName()] = $foreignKeyAttr;
            }
        }

        return $foreignKeys;
    }

    /**
     * Extract relationship mappings
     * @param ReflectionClass<object> $reflection
     * @return array<string, array<string, mixed>>
     */
    private function extractRelationships(ReflectionClass $reflection): array
    {
        $relationships = [
            'oneToOne' => $this->extractOneToOneRelations($reflection),
            'oneToMany' => $this->extractOneToManyRelations($reflection),
            'manyToOne' => $this->extractManyToOneRelations($reflection),
            'manyToMany' => $this->extractManyToManyRelations($reflection),
        ];

        return array_filter($relationships);
    }

    /**
     * Extract OneToOne relationships
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    private function extractOneToOneRelations(ReflectionClass $reflection): array
    {
        return $this->extractRelationsByAttribute($reflection, MtOneToOne::class);
    }

    /**
     * Extract OneToMany relationships
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    private function extractOneToManyRelations(ReflectionClass $reflection): array
    {
        return $this->extractRelationsByAttribute($reflection, MtOneToMany::class);
    }

    /**
     * Extract ManyToOne relationships
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    private function extractManyToOneRelations(ReflectionClass $reflection): array
    {
        return $this->extractRelationsByAttribute($reflection, MtManyToOne::class);
    }

    /**
     * Extract ManyToMany relationships
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    private function extractManyToManyRelations(ReflectionClass $reflection): array
    {
        return $this->extractRelationsByAttribute($reflection, MtManyToMany::class);
    }

    /**
     * Generic method to extract relations by attribute type
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    private function extractRelationsByAttribute(ReflectionClass $reflection, string $attributeClass): array
    {
        $relations = [];

        foreach ($reflection->getProperties() as $property) {
            $relationAttrs = $property->getAttributes($attributeClass);

            if (!empty($relationAttrs)) {
                $relationAttr = $relationAttrs[0]->newInstance();
                $relations[$property->getName()] = $relationAttr;
            }
        }

        return $relations;
    }

    /**
     * Convert class name to table name
     */
    private function classNameToTableName(string $className): string
    {
        $className = basename(str_replace('\\', '/', $className));
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className) ?? '');
    }
}
