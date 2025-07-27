<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionException;

/**
 * Handles column-related mapping operations
 */
class ColumnMapping
{
    /**
     * @param class-string $entityName
     * @return array<string, MtColumn>
     * @throws ReflectionException
     */
    public function getMtColumns(string $entityName): array
    {
        $columns = Php::getInstanceOfPropertiesAttributesNamed($entityName, MtColumn::class);
        return array_filter($columns, static fn ($column) => $column instanceof MtColumn);
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return ColumnType|null
     * @throws ReflectionException
     */
    public function getColumnType(string $entityName, string $property): ?ColumnType
    {
        return $this->getMtColumns($entityName)[$property]->columnType ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return int|null
     * @throws ReflectionException
     */
    public function getColumnLength(string $entityName, string $property): ?int
    {
        return $this->getMtColumns($entityName)[$property]->length ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnTypeDefinition(string $entityName, string $property): ?string
    {
        $mtColumn = $this->getMtColumns($entityName)[$property] ?? null;

        if (!$mtColumn || !$mtColumn->columnType) {
            return null;
        }

        return $mtColumn->columnType->toSqlDefinition(
            $mtColumn->length,
            $mtColumn->scale,
            $mtColumn->isUnsigned ?? false,
            $mtColumn->choices
        );
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return bool|null
     * @throws ReflectionException
     */
    public function isNullable(string $entityName, string $property): ?bool
    {
        return $this->getMtColumns($entityName)[$property]->isNullable ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getExtra(string $entityName, string $property): ?string
    {
        return $this->getMtColumns($entityName)[$property]->extra ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnDefault(string $entityName, string $property): ?string
    {
        return $this->getMtColumns($entityName)[$property]->columnDefault ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnKey(string $entityName, string $property): ?string
    {
        return $this->getMtColumns($entityName)[$property]->columnKey->value ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return bool
     * @throws ReflectionException
     */
    public function isUnsigned(string $entityName, string $property): bool
    {
        return $this->getMtColumns($entityName)[$property]->isUnsigned ?? false;
    }
}
