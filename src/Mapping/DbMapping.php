<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Attributes\MtManyToMany;
use MulerTech\Database\Mapping\Attributes\MtManyToOne;
use MulerTech\Database\Mapping\Attributes\MtOneToMany;
use MulerTech\Database\Mapping\Attributes\MtOneToOne;
use MulerTech\Database\Mapping\Types\ColumnType;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use MulerTech\Database\Mapping\Attributes\MtEntity;

/**
 * Class DbMapping
 *
 * Main implementation of database mapping functionality.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DbMapping implements DbMappingInterface
{
    /** @var array<class-string, string> $tables */
    private array $tables = [];
    /** @var array<string, array<string, string>> $columns */
    private array $columns = [];

    /** @var string|null $entitiesPath */
    private ?string $entitiesPath;

    /**
     * @param string|null $entitiesPath
     */
    public function __construct(?string $entitiesPath = null)
    {
        $this->entitiesPath = $entitiesPath;
        if ($entitiesPath !== null) {
            $this->loadEntities();
        }
    }

    /**
     * @return void
     */
    private function loadEntities(): void
    {
        if ($this->entitiesPath === null) {
            return;
        }

        $classNames = Php::getClassNames($this->entitiesPath, true);

        foreach ($classNames as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $this->processEntityClass($reflection);
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return void
     */
    private function processEntityClass(ReflectionClass $reflection): void
    {
        $className = $reflection->getName();

        // Get table name from MtEntity attribute
        $entityAttrs = $reflection->getAttributes(MtEntity::class);
        if (!empty($entityAttrs)) {
            $entityAttr = $entityAttrs[0]->newInstance();
            $tableName = $entityAttr->tableName ?? $this->classNameToTableName($className);
            $this->tables[$className] = $tableName;

            // Process properties for column mappings
            foreach ($reflection->getProperties() as $property) {
                $propertyName = $property->getName();

                // Get column mapping from MtColumn attribute
                $columnAttrs = $property->getAttributes(MtColumn::class);
                if (!empty($columnAttrs)) {
                    $columnAttr = $columnAttrs[0]->newInstance();
                    $columnName = $columnAttr->columnName ?? $propertyName;
                    $this->columns[$className][$propertyName] = $columnName;
                }
            }
        }
    }

    /**
     * Convert class name to table name (basic implementation)
     * @param string $className
     * @return string
     */
    private function classNameToTableName(string $className): string
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
     * @return string|null
     */
    public function getTableName(string $entityName): ?string
    {
        return $this->tables[$entityName] ?? null;
    }

    /**
     * @return array<string>
     * @throws ReflectionException
     */
    public function getTables(): array
    {
        $tables = $this->tables;
        sort($tables);
        return $tables;
    }

    /**
     * @return array<class-string>
     * @throws ReflectionException
     */
    public function getEntities(): array
    {
        $entities = array_keys($this->tables);
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
     * @param class-string $entityName
     * @return array<string>
     * @throws ReflectionException
     */
    public function getColumns(string $entityName): array
    {
        $this->initializeColumns($entityName);
        return array_values($this->columns[$entityName]);
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entityName): array
    {
        $this->initializeColumns($entityName);
        return $this->columns[$entityName];
    }

    /**
     * Get foreign key column name for relation properties
     * @param string $property
     * @param array<string, string> $columns
     * @return string
     */
    private function getRelationColumnName(string $property, array $columns): string
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
        $oneToOneList = $this->getOneToOne($entityName);
        if (!empty($oneToOneList) && isset($oneToOneList[$property])) {
            return $this->getRelationColumnName($property, $columns);
        }

        $manyToOneList = $this->getManyToOne($entityName);
        if (!empty($manyToOneList) && isset($manyToOneList[$property])) {
            return $this->getRelationColumnName($property, $columns);
        }

        return null;
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
     * @return MtFk|null
     * @throws ReflectionException
     */
    public function getForeignKey(string $entityName, string $property): ?MtFk
    {
        return $this->getMtFk($entityName)[$property] ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getConstraintName(string $entityName, string $property): ?string
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        $referencedTable = $this->getTableName($mtFk->referencedTable);
        $column = $this->getColumnName($entityName, $property);
        $table = $this->getTableName($entityName);

        if (!$referencedTable || !$column || !$table) {
            return null;
        }

        return sprintf(
            "fk_%s_%s_%s",
            strtolower($table),
            strtolower($column),
            strtolower($referencedTable)
        );
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getReferencedTable(string $entityName, string $property): ?string
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        return $this->getTableName($mtFk->referencedTable);
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getReferencedColumn(string $entityName, string $property): ?string
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        return $mtFk->referencedColumn ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getDeleteRule(string $entityName, string $property): ?string
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        return $mtFk->deleteRule->value ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getUpdateRule(string $entityName, string $property): ?string
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        return $mtFk->updateRule->value ?? null;
    }

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
     * @param class-string $entityName
     * @return void
     * @throws ReflectionException
     */
    private function initializeColumns(string $entityName): void
    {
        if (!isset($this->columns[$entityName])) {
            $result = [];
            foreach ($this->getMtColumns($entityName) as $property => $mtColumn) {
                $result[$property] = $mtColumn->columnName ?? $property;
            }

            $this->columns[$entityName] = $result;
        }
    }

    /**
     * @param class-string $entityName
     * @return MtEntity|null
     * @throws ReflectionException
     */
    private function getMtEntity(string $entityName): ?MtEntity
    {
        $entity = Php::getInstanceOfClassAttributeNamed($entityName, MtEntity::class);
        return $entity instanceof MtEntity ? $entity : null;
    }

    /**
     * @param class-string $entityName
     * @return array<string, MtColumn>
     * @throws ReflectionException
     */
    private function getMtColumns(string $entityName): array
    {
        $columns = Php::getInstanceOfPropertiesAttributesNamed($entityName, MtColumn::class);
        return array_filter($columns, static fn ($column) => $column instanceof MtColumn);
    }

    /**
     * @param class-string $entityName
     * @return array<string, MtFk>
     * @throws ReflectionException
     */
    private function getMtFk(string $entityName): array
    {
        return array_filter(
            Php::getInstanceOfPropertiesAttributesNamed($entityName, MtFk::class),
            static fn ($fk) => $fk instanceof MtFk
        );
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
