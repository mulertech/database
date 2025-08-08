<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use DateTime;
use MulerTech\Database\Mapping\Types\ColumnType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final readonly class EntityMetadata
{
    /**
     * @param class-string $className
     * @param string $tableName
     * @param array<ReflectionProperty> $properties
     * @param array<ReflectionMethod> $getters
     * @param array<ReflectionMethod> $setters
     * @param array<string, string> $columns
     * @param array<string, mixed> $foreignKeys
     * @param array<string, array<string, mixed>> $relationships
     * @param class-string|null $repository
     * @param int|null $autoIncrement
     */
    public function __construct(
        public string $className,
        public string $tableName,
        public array $properties = [],
        public array $getters = [],
        public array $setters = [],
        public array $columns = [],
        public array $foreignKeys = [],
        public array $relationships = [],
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
        return $this->columns[$property] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function getPropertiesColumns(): array
    {
        return $this->columns;
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
     * @return mixed
     */
    public function getForeignKey(string $property): mixed
    {
        return $this->foreignKeys[$property] ?? null;
    }

    /**
     * @param string $type
     * @param string $property
     * @return mixed
     */
    public function getRelation(string $type, string $property): mixed
    {
        return $this->relationships[$type][$property] ?? null;
    }

    /**
     * @param string $type
     * @return array<string, mixed>
     */
    public function getRelationsByType(string $type): array
    {
        return $this->relationships[$type] ?? [];
    }

    /**
     * @return array<ReflectionProperty>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param string $property
     * @return ReflectionProperty|null
     */
    public function getProperty(string $property): ?ReflectionProperty
    {
        foreach ($this->properties as $prop) {
            if ($prop->getName() === $property) {
                return $prop;
            }
        }
        return null;
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
     * @return string|null
     */
    public function getRepository(): ?string
    {
        return $this->repository;
    }

    /**
     * Get the column type for a given property.
     *
     * @param string $property
     * @return ColumnType|null
     */
    public function getColumnType(string $property): ?ColumnType
    {
        // Defensive: check if property exists and has a type
        $prop = $this->getProperty($property);
        if ($prop !== null && $prop->getType() !== null) {
            $reflectionType = $prop->getType();
            if ($reflectionType instanceof ReflectionNamedType) {
                $typeName = $reflectionType->getName();
                // Map PHP types to MySQL ColumnType
                return match($typeName) {
                    'int' => ColumnType::INT,
                    'string' => ColumnType::VARCHAR,
                    'float' => ColumnType::FLOAT,
                    'bool' => ColumnType::TINYINT,
                    'array' => ColumnType::JSON,
                    'DateTime', DateTime::class => ColumnType::DATETIME,
                    default => null
                };
            }
        }
        return null;
    }
}
