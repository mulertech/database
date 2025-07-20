<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

/**
 * Holds metadata information for an entity
 */
final class EntityMetadata
{
    public string $className = '';
    public string $tableName = '';
    /** @var array<string, string> */
    public array $columns = [];
    /** @var array<string, mixed> */
    public array $foreignKeys = [];
    /** @var array<string, array<string, mixed>> */
    public array $relationships = [];

    /**
     * Get column name for property
     */
    public function getColumnName(string $property): ?string
    {
        return $this->columns[$property] ?? null;
    }

    /**
     * Get all properties mapped to columns
     * @return array<string, string>
     */
    public function getPropertiesColumns(): array
    {
        return $this->columns;
    }

    /**
     * Check if property has foreign key
     */
    public function hasForeignKey(string $property): bool
    {
        return isset($this->foreignKeys[$property]);
    }

    /**
     * Get foreign key for property
     */
    public function getForeignKey(string $property): mixed
    {
        return $this->foreignKeys[$property] ?? null;
    }

    /**
     * Get relationship by type and property
     */
    public function getRelation(string $type, string $property): mixed
    {
        return $this->relationships[$type][$property] ?? null;
    }

    /**
     * Get all relations of a specific type
     * @return array<string, mixed>
     */
    public function getRelationsByType(string $type): array
    {
        return $this->relationships[$type] ?? [];
    }
}
