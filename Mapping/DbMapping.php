<?php

namespace MulerTech\Database\Mapping;

use MulerTech\Database\NonRelational\DocumentStore\FileContent\AttributeReader;
use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use MulerTech\Database\NonRelational\DocumentStore\PathManipulation;
use ReflectionException;
use RuntimeException;

class DbMapping implements DbMappingInterface
{
    /**
     * @var FileManipulation $fileManipulation
     */
    private FileManipulation $fileManipulation;

    public function __construct(
        private readonly AttributeReader $attributeReader,
        private readonly string $entitiesPath
    ) {
        $this->fileManipulation = new FileManipulation();
    }

    /**
     * @param string $entity
     * @return string|null
     * @throws ReflectionException
     */
    public function getTableName(string $entity): ?string
    {
        $mtEntity = $this->attributeReader->getInstanceOfClassAttributeNamed($entity, MtEntity::class);

        if (!is_null($mtEntity) && !is_null($mtEntity->tableName)) {
            return $mtEntity->tableName;
        }

        return ($pos = strrpos($entity, '\\')) ? strtolower(substr($entity, $pos + 1)) : strtolower($entity);
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getTables(): array
    {
        $fileList = PathManipulation::fileList($this->entitiesPath);

        $tableList = array_map(
            function ($entity) {
                return $this->getTableName($this->fileManipulation->fileClassName($entity));
            },
            $fileList
        );

        return array_values(array_filter($tableList, static function ($value) {
            return !is_null($value);
        }));
    }

    /**
     * @return array
     */
    public function getEntities(): array
    {
        $fileList = PathManipulation::fileList($this->entitiesPath);

        $entityList = array_map(
            function ($entity) {
                return $this->fileManipulation->fileClassName($entity);
            },
            $fileList
        );

        return array_values(array_filter($entityList, static function ($value) {
            return !is_null($value);
        }));
    }

    /**
     * @param string $entity
     * @return string|null
     * @throws ReflectionException
     */
    public function getRepository(string $entity): ?string
    {
        $mtEntity = $this->attributeReader->getInstanceOfClassAttributeNamed($entity, MtEntity::class);

        if (is_null($mtEntity)) {
            throw new RuntimeException(
                sprintf('The MtEntity mapping is not implemented into the %s class.', $entity)
            );
        }

        return $mtEntity->repository;
    }

    /**
     * @param string $entity
     * @return int|null
     * @throws ReflectionException
     */
    public function getAutoIncrement(string $entity): ?int
    {
        $mtEntity = $this->attributeReader->getInstanceOfClassAttributeNamed($entity, MtEntity::class);

        if (is_null($mtEntity)) {
            throw new RuntimeException(sprintf('The MtEntity mapping is not implemented into the %s class.', $entity));
        }

        return $mtEntity->autoIncrement;
    }

    /**
     * @param string $entity
     * @return array<string>
     * @throws ReflectionException
     */
    public function getColumns(string $entity): array
    {
        $mtColumns = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtColumn::class);

        $result = [];
        foreach ($mtColumns as $property => $mtColumn) {
            if (!$mtColumn instanceof MtColumn) {
                continue;
            }

            $result[] = $mtColumn->columnName ?? $property;
        }

        return $result;
    }

    /**
     * @param string $entity
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entity): array
    {
        $mtColumns = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtColumn::class);

        $result = [];
        foreach ($mtColumns as $property => $mtColumn) {
            if (!$mtColumn instanceof MtColumn) {
                continue;
            }

            $result[$property] = $mtColumn->columnName ?? $property;
        }

        return $result;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnName(string $entity, string $property): ?string
    {
        return $this->getPropertiesColumns($entity)[$property] ?? $property;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnType(string $entity, string $property): ?string
    {
        $mtColumns = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtColumn::class);

        return $mtColumns[$property]?->columnType ?? null;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return bool
     * @throws ReflectionException
     */
    public function isNullable(string $entity, string $property): bool
    {
        $mtColumns = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtColumn::class);

        return $mtColumns[$property]?->isNullable ?? true;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getExtra(string $entity, string $property): ?string
    {
        $mtColumns = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtColumn::class);

        return $mtColumns[$property]?->extra ?? null;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnKey(string $entity, string $property): ?string
    {
        $mtColumns = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtColumn::class);

        return $mtColumns[$property]?->columnKey ?? null;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return MtFk|null
     * @throws ReflectionException
     */
    public function getForeignKey(string $entity, string $property): ?MtFk
    {
        $mtFk = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtFk::class);

        return $mtFk[$property] ?? null;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getConstraintName(string $entity, string $property): ?string
    {
        $mtFk = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtFk::class);

        return $mtFk[$property]?->getConstraintName(
            $this->getTableName($entity),
            $this->getColumnName($entity, $property)
        );
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getReferencedTable(string $entity, string $property): string
    {
        $mtFk = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtFk::class);

        return $mtFk[$property]?->referencedTable;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getReferencedColumn(string $entity, string $property): string
    {
        $mtFk = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtFk::class);

        return $mtFk[$property]?->referencedColumn;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getDeleteRule(string $entity, string $property): string
    {
        $mtFk = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtFk::class);

        return $mtFk[$property]?->deleteRule;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getUpdateRule(string $entity, string $property): string
    {
        $mtFk = $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entity, MtFk::class);

        return $mtFk[$property]?->updateRule;
    }

}