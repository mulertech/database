<?php

namespace MulerTech\Database\Mapping;

use MulerTech\FileManipulation\FileType\Php;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class DbMapping implements DbMappingInterface
{
    /**
     * List of [entityNames => tables].
     * @var array<class-string, string> $tables
     */
    private array $tables = [];
    /**
     * List of [entityNames => [property => column]]
     * @var array<class-string, array<string, string>> $columns
     */
    private array $columns = [];

    /**
     * @param string $entitiesPath
     * @param bool $recursive
     */
    public function __construct(
        private readonly string $entitiesPath,
        private readonly bool $recursive = true
    ) {}

    /**
     * @param class-string $entityName
     * @return string|null
     * @throws ReflectionException
     */
    public function getTableName(string $entityName): ?string
    {
        if (empty($this->tables)) {
            $this->generateTables();
        }

        return $this->tables[$entityName] ?? null;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getTables(): array
    {
        if (empty($this->tables)) {
            $this->generateTables();
        }

        $tables = $this->tables;
        sort($tables);

        return $tables;
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getEntities(): array
    {
        if (empty($this->tables)) {
            $this->generateTables();
        }

        $entities = array_keys($this->tables);
        sort($entities);

        return $entities;
    }

    /**
     * @param class-string $entityName
     * @return string|null
     * @throws ReflectionException
     */
    public function getRepository(string $entityName): ?string
    {
        $mtEntity = $this->getMtEntity($entityName);

        if (is_null($mtEntity)) {
            throw new RuntimeException(
                sprintf('The MtEntity mapping is not implemented into the %s class.', $entityName)
            );
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
        if (!isset($this->columns[$entityName])) {
            $this->generatePropertiesColumns($entityName);
        }

        return array_values($this->columns[$entityName]);
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entityName): array
    {
        if (!isset($this->columns[$entityName])) {
            $this->generatePropertiesColumns($entityName);
        }

        return $this->columns[$entityName];
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnName(string $entityName, string $property): ?string
    {
        if (!isset($this->getPropertiesColumns($entityName)[$property])) {
            return null;
        }

        return $this->getPropertiesColumns($entityName)[$property] ?? $property;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnType(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtColumns($entityName)[$property])) {
            return null;
        }

        return $this->getMtColumns($entityName)[$property]?->columnType;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return bool|null
     * @throws ReflectionException
     */
    public function isNullable(string $entityName, string $property): ?bool
    {
        if (!isset($this->getMtColumns($entityName)[$property])) {
            return null;
        }

        return $this->getMtColumns($entityName)[$property]?->isNullable;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getExtra(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtColumns($entityName)[$property])) {
            return null;
        }

        return $this->getMtColumns($entityName)[$property]?->extra;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnDefault(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtColumns($entityName)[$property])) {
            return null;
        }

        return $this->getMtColumns($entityName)[$property]?->columnDefault;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnKey(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtColumns($entityName)[$property])) {
            return null;
        }

        return $this->getMtColumns($entityName)[$property]?->columnKey;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return MtFk|null
     * @throws ReflectionException
     */
    public function getForeignKey(string $entityName, string $property): ?MtFk
    {
        if (!isset($this->getMtFk($entityName)[$property])) {
            return null;
        }

        return $this->getMtFk($entityName)[$property] ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null Returns null if there is not enough information to build the constraint name
     * @throws ReflectionException
     */
    public function getConstraintName(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtFk($entityName)[$property])) {
            return null;
        }

        $mtFk = $this->getMtFk($entityName)[$property];

        if (
            is_null($referencedEntityTable = $mtFk->referencedTable) ||
            is_null($referencedTable = $this->getTableName($referencedEntityTable)) ||
            is_null($column = $this->getColumnName($entityName, $property)) ||
            is_null($table = $this->getTableName($entityName))
        ) {
            return null;
        }

        return sprintf('fk_%s_%s_%s', $table, $column, $referencedTable);
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getReferencedTable(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtFk($entityName)[$property])) {
            return null;
        }

        return $this->getTableName($this->getMtFk($entityName)[$property]?->referencedTable);
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getReferencedColumn(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtFk($entityName)[$property])) {
            return null;
        }

        return $this->getMtFk($entityName)[$property]?->referencedColumn;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getDeleteRule(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtFk($entityName)[$property])) {
            return null;
        }

        return $this->getMtFk($entityName)[$property]?->deleteRule;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getUpdateRule(string $entityName, string $property): ?string
    {
        if (!isset($this->getMtFk($entityName)[$property])) {
            return null;
        }

        return $this->getMtFk($entityName)[$property]?->updateRule;
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function generateTables(): void
    {
        $classNames = Php::getClassNames($this->entitiesPath, $this->recursive);

        $tables = [];
        foreach ($classNames as $className) {
            $table = $this->generateTableName($className);
            if (!is_null($table)) {
                $tables[$className] = $table;
            }
        }

        $this->tables = $tables;
    }

    /**
     * @param class-string $entityName
     * @return string|null
     * @throws ReflectionException
     */
    private function generateTableName(string $entityName): ?string
    {
        $mtEntity = $this->getMtEntity($entityName);

        if (is_null($mtEntity)){
            return null;
        }

        if (!is_null($mtEntity->tableName)) {
            return $mtEntity->tableName;
        }

        return strtolower((new ReflectionClass($entityName))->getShortName());
    }

    /**
     * @param class-string $entityName
     * @return void
     * @throws ReflectionException
     */
    public function generatePropertiesColumns(string $entityName): void
    {
        $result = [];
        foreach ($this->getMtColumns($entityName) as $property => $mtColumn) {
            if (!$mtColumn instanceof MtColumn) {
                continue;
            }

            $result[$property] = $mtColumn->columnName ?? $property;
        }

        $this->columns[$entityName] = $result;
    }

    /**
     * @param class-string $entityName
     * @return object|null
     * @throws ReflectionException
     */
    private function getMtEntity(string $entityName): ?object
    {
        return Php::getInstanceOfClassAttributeNamed($entityName, MtEntity::class);
    }

    /**
     * @param class-string $entityName
     * @return array
     * @throws ReflectionException
     */
    private function getMtColumns(string $entityName): array
    {
        return Php::getInstanceOfPropertiesAttributesNamed($entityName, MtColumn::class);
    }

    /**
     * @param class-string $entityName
     * @return array
     * @throws ReflectionException
     */
    private function getMtFk(string $entityName): array
    {
        return Php::getInstanceOfPropertiesAttributesNamed($entityName, MtFk::class);
    }
}