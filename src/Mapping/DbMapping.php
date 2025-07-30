<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\Database\Mapping\Types\FkRule;
use ReflectionException;

/**
 * Class DbMapping
 *
 * Main implementation of database mapping functionality.
 * Refactored to use composition and delegate responsibilities to specialized classes.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DbMapping implements DbMappingInterface
{
    private EntityProcessor $entityProcessor;
    private ColumnMapping $columnMapping;
    private RelationMapping $relationMapping;
    private ForeignKeyMapping $foreignKeyMapping;
    /**
     * @var array<class-string, array<string, true>>
     */
    private array $relationPropertiesCache = [];


    /**
     * @param string|null $entitiesPath
     */
    public function __construct(?string $entitiesPath = null)
    {
        $this->entityProcessor = new EntityProcessor();
        $this->columnMapping = new ColumnMapping();
        $this->relationMapping = new RelationMapping();
        $this->foreignKeyMapping = new ForeignKeyMapping($this);

        if ($entitiesPath !== null) {
            $this->entityProcessor->loadEntities($entitiesPath);
        }
    }

    /**
     * @param class-string $entityName
     * @return string|null
     */
    public function getTableName(string $entityName): ?string
    {
        return $this->entityProcessor->getTableName($entityName);
    }

    /**
     * @return array<string>
     */
    public function getTables(): array
    {
        $tables = $this->entityProcessor->getTables();
        sort($tables);
        return $tables;
    }

    /**
     * @return array<class-string>
     */
    public function getEntities(): array
    {
        $entities = array_keys($this->entityProcessor->getTables());
        sort($entities);
        return $entities;
    }

    /**
     * @param class-string $entityName
     * @return class-string|null
     * @throws ReflectionException
     */
    public function getRepository(string $entityName): ?string
    {
        return $this->entityProcessor->getRepository($entityName);
    }

    /**
     * @param class-string $entityName
     * @return int|null
     * @throws ReflectionException
     */
    public function getAutoIncrement(string $entityName): ?int
    {
        return $this->entityProcessor->getAutoIncrement($entityName);
    }

    /**
     * @param class-string $entityName
     * @return array<string>
     * @throws ReflectionException
     */
    public function getColumns(string $entityName): array
    {
        $this->entityProcessor->initializeColumns($entityName, $this->columnMapping);
        $columns = $this->entityProcessor->getColumnsMapping()[$entityName] ?? [];
        return array_values($columns);
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entityName): array
    {
        $this->entityProcessor->initializeColumns($entityName, $this->columnMapping);
        return $this->entityProcessor->getColumnsMapping()[$entityName] ?? [];
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnName(string $entityName, string $property): ?string
    {
        $columns = $this->getPropertiesColumns($entityName);
        if (isset($columns[$property])) {
            return $columns[$property];
        }

        // Check if it's a relation property
        $oneToOneList = $this->relationMapping->getOneToOne($entityName);
        if (!empty($oneToOneList) && isset($oneToOneList[$property])) {
            return $this->relationMapping->getRelationColumnName($property, $columns);
        }

        $manyToOneList = $this->relationMapping->getManyToOne($entityName);
        if (!empty($manyToOneList) && isset($manyToOneList[$property])) {
            return $this->relationMapping->getRelationColumnName($property, $columns);
        }

        return null;
    }

    // Column-related methods delegated to ColumnMapping
    public function getColumnType(string $entityName, string $property): ?ColumnType
    {
        return $this->columnMapping->getColumnType($entityName, $property);
    }

    public function getColumnLength(string $entityName, string $property): ?int
    {
        return $this->columnMapping->getColumnLength($entityName, $property);
    }

    public function getColumnTypeDefinition(string $entityName, string $property): ?string
    {
        return $this->columnMapping->getColumnTypeDefinition($entityName, $property);
    }

    public function isNullable(string $entityName, string $property): ?bool
    {
        return $this->columnMapping->isNullable($entityName, $property);
    }

    public function getExtra(string $entityName, string $property): ?string
    {
        return $this->columnMapping->getExtra($entityName, $property);
    }

    public function getColumnDefault(string $entityName, string $property): ?string
    {
        return $this->columnMapping->getColumnDefault($entityName, $property);
    }

    public function getColumnKey(string $entityName, string $property): ?string
    {
        return $this->columnMapping->getColumnKey($entityName, $property);
    }

    public function isUnsigned(string $entityName, string $property): bool
    {
        return $this->columnMapping->isUnsigned($entityName, $property);
    }

    // Foreign key methods delegated to ForeignKeyMapping
    public function getForeignKey(string $entityName, string $property): ?MtFk
    {
        return $this->foreignKeyMapping->getForeignKey($entityName, $property);
    }

    /**
     * @throws ReflectionException
     */
    public function getConstraintName(string $entityName, string $property): ?string
    {
        return $this->foreignKeyMapping->getConstraintName($entityName, $property);
    }

    /**
     * @throws ReflectionException
     */
    public function getReferencedTable(string $entityName, string $property): ?string
    {
        return $this->foreignKeyMapping->getReferencedTable($entityName, $property);
    }

    /**
     * @throws ReflectionException
     */
    public function getReferencedColumn(string $entityName, string $property): ?string
    {
        return $this->foreignKeyMapping->getReferencedColumn($entityName, $property);
    }

    /**
     * @throws ReflectionException
     */
    public function getDeleteRule(string $entityName, string $property): ?FkRule
    {
        return $this->foreignKeyMapping->getDeleteRule($entityName, $property);
    }

    /**
     * @throws ReflectionException
     */
    public function getUpdateRule(string $entityName, string $property): ?FkRule
    {
        return $this->foreignKeyMapping->getUpdateRule($entityName, $property);
    }

    // Relation methods delegated to RelationMapping

    /**
     * @param class-string $entityName
     * @return array<string, MtOneToOne>
     */
    public function getOneToOne(string $entityName): array
    {
        return $this->relationMapping->getOneToOne($entityName);
    }

    /**
     * @param class-string $entityName
     * @return array<string, MtManyToOne>
     */
    public function getManyToOne(string $entityName): array
    {
        return $this->relationMapping->getManyToOne($entityName);
    }

    /**
     * @param class-string $entityName
     * @return array<string, MtOneToMany>
     */
    public function getOneToMany(string $entityName): array
    {
        return $this->relationMapping->getOneToMany($entityName);
    }

    /**
     * @param class-string $entityName
     * @return array<string, MtManyToMany>
     */
    public function getManyToMany(string $entityName): array
    {
        return $this->relationMapping->getManyToMany($entityName);
    }

    /**
     * Check if a property is a relation property (OneToOne, ManyToOne, OneToMany, ManyToMany)
     * Uses an internal cache for performance.
     *
     * @param class-string $entityName
     * @param string $property
     * @return bool
     */
    public function isRelationProperty(string $entityName, string $property): bool
    {
        if (!isset($this->relationPropertiesCache[$entityName])) {
            $relationProps = array_map(static function () {
                return true;
            }, $this->getOneToOne($entityName));
            $relationProps += array_map(static function () {
                return true;
            }, $this->getManyToOne($entityName));
            $relationProps += array_map(static function () {
                return true;
            }, $this->getOneToMany($entityName));
            $relationProps += array_map(static function () {
                return true;
            }, $this->getManyToMany($entityName));
            $this->relationPropertiesCache[$entityName] = $relationProps;
        }
        return isset($this->relationPropertiesCache[$entityName][$property]);
    }
}
