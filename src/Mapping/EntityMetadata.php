<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use DateTime;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Types\ColumnType;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class EntityMetadata
{
    /**
     * @param class-string $className
     * @param string $tableName
     * @param MtEntity|null $entity
     * @param array<ReflectionProperty> $properties
     * @param array<ReflectionMethod> $getters
     * @param array<ReflectionMethod> $setters
     * @param array<string, MtColumn> $columns
     * @param array<string, MtFk> $foreignKeys
     * @param array<string, MtOneToMany> $oneToManyRelations
     * @param array<string, MtManyToOne> $manyToOneRelations
     * @param array<string, MtOneToOne> $oneToOneRelations
     * @param array<string, MtManyToMany> $manyToManyRelations
     * @param class-string|null $repository
     * @param int|null $autoIncrement
     */
    public function __construct(
        public string $className,
        public string $tableName,
        public ?MtEntity $entity = null,
        public array $properties = [],
        public array $getters = [],
        public array $setters = [],
        public array $columns = [],
        public array $foreignKeys = [],
        public array $oneToManyRelations = [],
        public array $manyToOneRelations = [],
        public array $oneToOneRelations = [],
        public array $manyToManyRelations = [],
        public ?string $repository = null,
        public ?int $autoIncrement = null
    ) {
    }

    /**
     * @param string $property
     * @return string|null
     */
    public function getColumnName(string $property): ?string
    {
        $column = $this->columns[$property] ?? null;
        if ($column === null) {
            return null;
        }
        return $column->columnName ?? $property;
    }

    /**
     * @return array<string, string>
     */
    public function getPropertiesColumns(): array
    {
        $result = [];
        foreach ($this->columns as $property => $column) {
            $result[$property] = $column->columnName ?? $property;
        }
        return $result;
    }

    /**
     * @param string $property
     * @return bool
     */
    public function hasForeignKey(string $property): bool
    {
        return isset($this->foreignKeys[$property]);
    }

    /**
     * @param string $property
     * @return MtFk|null
     */
    public function getForeignKey(string $property): ?MtFk
    {
        return $this->foreignKeys[$property] ?? null;
    }

    /**
     * @param string $property
     * @return MtOneToMany|null
     */
    public function getOneToManyRelation(string $property): ?MtOneToMany
    {
        return $this->oneToManyRelations[$property] ?? null;
    }

    /**
     * @param string $property
     * @return MtManyToOne|null
     */
    public function getManyToOneRelation(string $property): ?MtManyToOne
    {
        return $this->manyToOneRelations[$property] ?? null;
    }

    /**
     * @param string $property
     * @return MtOneToOne|null
     */
    public function getOneToOneRelation(string $property): ?MtOneToOne
    {
        return $this->oneToOneRelations[$property] ?? null;
    }

    /**
     * @param string $property
     * @return MtManyToMany|null
     */
    public function getManyToManyRelation(string $property): ?MtManyToMany
    {
        return $this->manyToManyRelations[$property] ?? null;
    }

    /**
     * @return array<string, MtOneToMany>
     */
    public function getOneToManyRelations(): array
    {
        return $this->oneToManyRelations;
    }

    /**
     * @return array<string, MtManyToOne>
     */
    public function getManyToOneRelations(): array
    {
        return $this->manyToOneRelations;
    }

    /**
     * @return array<string, MtOneToOne>
     */
    public function getOneToOneRelations(): array
    {
        return $this->oneToOneRelations;
    }

    /**
     * @return array<string, MtManyToMany>
     */
    public function getManyToManyRelations(): array
    {
        return $this->manyToManyRelations;
    }

    /**
     * Check if a property has any type of relation
     * @param string $property
     * @return bool
     */
    public function hasRelation(string $property): bool
    {
        return isset($this->oneToManyRelations[$property]) ||
               isset($this->manyToOneRelations[$property]) ||
               isset($this->oneToOneRelations[$property]) ||
               isset($this->manyToManyRelations[$property]);
    }

    /**
     * Get relations by type (backward compatibility method)
     * @param string $type
     * @return array<string, mixed>
     * @deprecated Use specific methods like getOneToManyRelations() instead
     */
    public function getRelationsByType(string $type): array
    {
        return match($type) {
            'OneToMany' => $this->oneToManyRelations,
            'ManyToOne' => $this->manyToOneRelations,
            'OneToOne' => $this->oneToOneRelations,
            'ManyToMany' => $this->manyToManyRelations,
            default => []
        };
    }

    /**
     * @param string $property
     * @return string|null
     */
    public function getGetter(string $property): ?string
    {
        foreach ($this->getters as $getter) {
            $name = $getter->getName();
            if (preg_match('/^(get|is|has)' . ucfirst($property) . '$/', $name)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Get getter method name, throwing exception if not found
     * @param string $property
     * @return string
     * @throws RuntimeException
     */
    public function getRequiredGetter(string $property): string
    {
        $getter = $this->getGetter($property);
        if ($getter === null) {
            throw new RuntimeException(
                sprintf('No getter found for property "%s" in entity "%s"', $property, $this->className)
            );
        }
        return $getter;
    }

    /**
     * @param string $property
     * @return string|null
     */
    public function getSetter(string $property): ?string
    {
        foreach ($this->setters as $setter) {
            $name = $setter->getName();
            if ($name === 'set' . ucfirst($property)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Get setter method name, throwing exception if not found
     * @param string $property
     * @return string
     * @throws RuntimeException
     */
    public function getRequiredSetter(string $property): string
    {
        $setter = $this->getSetter($property);
        if ($setter === null) {
            throw new RuntimeException(
                sprintf('No setter found for property "%s" in entity "%s"', $property, $this->className)
            );
        }
        return $setter;
    }

    /**
     * Check if both getter and setter exist for a property
     * @param string $property
     * @return bool
     */
    public function hasGetterAndSetter(string $property): bool
    {
        return $this->getGetter($property) !== null && $this->getSetter($property) !== null;
    }

    /**
     * Get all properties that have both getter and setter
     * @return array<string>
     */
    public function getPropertiesWithGettersAndSetters(): array
    {
        $properties = [];
        foreach ($this->properties as $property) {
            $propertyName = $property->getName();
            if ($this->hasGetterAndSetter($propertyName)) {
                $properties[] = $propertyName;
            }
        }
        return $properties;
    }

    /**
     * Get mapping of property names to getter method names
     * @return array<string, string>
     */
    public function getPropertyGetterMapping(): array
    {
        $mapping = [];
        foreach ($this->properties as $property) {
            $propertyName = $property->getName();
            $getter = $this->getGetter($propertyName);
            if ($getter !== null) {
                $mapping[$propertyName] = $getter;
            }
        }
        return $mapping;
    }

    /**
     * @return string|null
     */
    public function getRepository(): ?string
    {
        return $this->entity->repository ?? $this->repository;
    }

    /**
     * Get MtEntity attribute if available
     * @return MtEntity|null
     */
    public function getEntity(): ?MtEntity
    {
        return $this->entity;
    }

    /**
     * Get column definition for a property
     * @param string $property
     * @return MtColumn|null
     */
    public function getColumn(string $property): ?MtColumn
    {
        return $this->columns[$property] ?? null;
    }

    /**
     * Get all columns
     * @return array<string, MtColumn>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the column type for a given property.
     *
     * @param string $property
     * @return ColumnType|null
     */
    public function getColumnType(string $property): ?ColumnType
    {
        return $this->getColumn($property)?->columnType;
    }
}
