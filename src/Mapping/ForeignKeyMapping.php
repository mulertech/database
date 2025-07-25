<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtFk;
use MulerTech\Database\Mapping\Types\FkRule;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionException;

/**
 * Handles foreign key mapping operations
 */
class ForeignKeyMapping
{
    private DbMappingInterface $dbMapping;

    public function __construct(DbMappingInterface $dbMapping)
    {
        $this->dbMapping = $dbMapping;
    }

    /**
     * @param class-string $entityName
     * @return array<string, MtFk>
     * @throws ReflectionException
     */
    public function getMtFk(string $entityName): array
    {
        return array_filter(
            Php::getInstanceOfPropertiesAttributesNamed($entityName, MtFk::class),
            static fn ($fk) => $fk instanceof MtFk
        );
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

        $referencedTable = $this->dbMapping->getTableName($mtFk->referencedTable);
        $column = $this->dbMapping->getColumnName($entityName, $property);
        $table = $this->dbMapping->getTableName($entityName);

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

        return $this->dbMapping->getTableName($mtFk->referencedTable);
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
     * @return FkRule|null
     * @throws ReflectionException
     */
    public function getDeleteRule(string $entityName, string $property): ?FkRule
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        return $mtFk->deleteRule ?? null;
    }

    /**
     * @param class-string $entityName
     * @param string $property
     * @return FkRule|null
     * @throws ReflectionException
     */
    public function getUpdateRule(string $entityName, string $property): ?FkRule
    {
        $mtFk = $this->getForeignKey($entityName, $property);

        if ($mtFk === null || $mtFk->referencedTable === null) {
            return null;
        }

        return $mtFk->updateRule ?? null;
    }
}
