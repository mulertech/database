<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use ReflectionClass;
use ReflectionException;

/**
 * Handles entity relationship mapping operations
 */
class RelationMapping
{
    /**
     * Get OneToOne relations for an entity
     *
     * @param class-string $entityName
     * @return array<string, MtOneToOne>
     */
    public function getOneToOne(string $entityName): array
    {
        try {
            $reflection = new ReflectionClass($entityName);
            $relations = [];

            foreach ($reflection->getProperties() as $property) {
                $oneToOneAttrs = $property->getAttributes(MtOneToOne::class);
                if (!empty($oneToOneAttrs)) {
                    $relations[$property->getName()] = $oneToOneAttrs[0]->newInstance();
                }
            }

            return $relations;
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * Get ManyToOne relations for an entity
     *
     * @param class-string $entityName
     * @return array<string, MtManyToOne>
     */
    public function getManyToOne(string $entityName): array
    {
        try {
            $reflection = new ReflectionClass($entityName);
            $relations = [];

            foreach ($reflection->getProperties() as $property) {
                $manyToOneAttrs = $property->getAttributes(MtManyToOne::class);
                if (!empty($manyToOneAttrs)) {
                    $relations[$property->getName()] = $manyToOneAttrs[0]->newInstance();
                }
            }

            return $relations;
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * Get OneToMany relations for an entity
     *
     * @param class-string $entityName
     * @return array<string, MtOneToMany>
     */
    public function getOneToMany(string $entityName): array
    {
        try {
            $reflection = new ReflectionClass($entityName);
            $relations = [];

            foreach ($reflection->getProperties() as $property) {
                $oneToManyAttrs = $property->getAttributes(MtOneToMany::class);
                if (!empty($oneToManyAttrs)) {
                    $relations[$property->getName()] = $oneToManyAttrs[0]->newInstance();
                }
            }

            return $relations;
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * Get ManyToMany relations for an entity
     *
     * @param class-string $entityName
     * @return array<string, MtManyToMany>
     */
    public function getManyToMany(string $entityName): array
    {
        try {
            $reflection = new ReflectionClass($entityName);
            $relations = [];

            foreach ($reflection->getProperties() as $property) {
                $manyToManyAttrs = $property->getAttributes(MtManyToMany::class);
                if (!empty($manyToManyAttrs)) {
                    $relations[$property->getName()] = $manyToManyAttrs[0]->newInstance();
                }
            }

            return $relations;
        } catch (ReflectionException) {
            return [];
        }
    }

    /**
     * Get foreign key column name for relation properties
     * @param string $property
     * @param array<string, string> $columns
     * @return string
     */
    public function getRelationColumnName(string $property, array $columns): string
    {
        $fkColumn = $property . '_id';

        // Check if this column exists in the mapped columns
        foreach ($columns as $col) {
            if ($col === $fkColumn) {
                return $col;
            }
        }

        // If not found in mapped columns, return the convention-based name
        return $fkColumn;
    }
}
