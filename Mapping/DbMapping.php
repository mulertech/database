<?php

namespace MulerTech\Database\Mapping;

use MulerTech\Database\NonRelational\DocumentStore\FileContent\AttributeReader;
use MulerTech\Database\NonRelational\DocumentStore\FileExtension\Php;
use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use MulerTech\Database\NonRelational\DocumentStore\PathManipulation;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class DbMapping implements DbMappingInterface
{
    /**
     * List of entityNames => tables.
     * @var array<class-string, string> $tables
     */
    private array $tables = [];

    /**
     * @param AttributeReader $attributeReader
     * @param string $entitiesPath
     */
    public function __construct(
        private readonly AttributeReader $attributeReader,
        private readonly string $entitiesPath
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
     * @param string $entityName
     * @return string|null
     * @throws ReflectionException
     */
    public function generateTableName(string $entityName): ?string
    {
        $mtEntity = $this->getMtEntity($entityName);

        if (is_null($mtEntity)){
            return null;
        }

        if (!is_null($mtEntity->tableName)) {
            return $mtEntity->tableName;
        }

        if ($pos = strrpos($entityName, '\\')) {
            return strtolower(substr($entityName, $pos + 1));
        }

        return strtolower($entityName);
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

        return array_values($this->tables);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function generateTables(): void
    {
        $classNames = Php::getClassNames($this->entitiesPath);

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
     * @return array
     * @throws ReflectionException
     */
    public function getEntities(): array
    {
        if (empty($this->tables)) {
            $this->generateTables();
        }

        return array_keys($this->tables);
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
     * todo : voir la méthode getMtColumn à renommer en getMtColumns ???
     * @param class-string $entityName
     * @return array<string>
     * @throws ReflectionException
     */
    public function getColumns(string $entityName): array
    {
        $result = [];
        foreach ($this->getMtColumns($entityName) as $property => $mtColumn) {
            if (!$mtColumn instanceof MtColumn) {
                continue;
            }

            $result[] = $mtColumn->columnName ?? $property;
        }

        return $result;
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entityName): array
    {
        $result = [];
        foreach ($this->getMtColumns($entityName) as $property => $mtColumn) {
            if (!$mtColumn instanceof MtColumn) {
                continue;
            }

            $result[$property] = $mtColumn->columnName ?? $property;
        }

        return $result;
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
            is_null($mtFk->referencedTable) ||
            is_null($this->getColumnName($entityName, $property)) ||
            is_null($this->getTableName($entityName))
        ) {
            return null;
        }

        return 'fk_' .
            $this->getTableName($entityName) .
            '_' .
            $this->getColumnName($entityName, $property) .
            '_' .
            $this->getTableName($mtFk->referencedTable);
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
     * @param class-string $entityName
     * @return object|null
     * @throws ReflectionException
     */
    private function getMtEntity(string $entityName): ?object
    {
        return $this->attributeReader->getInstanceOfClassAttributeNamed($entityName, MtEntity::class);
    }

    /**
     * @param class-string $entityName
     * @return array
     * @throws ReflectionException
     */
    private function getMtColumns(string $entityName): array
    {
        return $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entityName, MtColumn::class);
    }

    /**
     * @param class-string $entityName
     * @return array
     * @throws ReflectionException
     */
    private function getMtFk(string $entityName): array
    {
        return $this
            ->attributeReader
            ->getInstanceOfPropertiesAttributesNamed($entityName, MtFk::class);
    }
}