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
 * Interface DbMappingInterface
 *
 * Interface for database mapping operations and metadata retrieval.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface DbMappingInterface
{
    /**
     * Return the table name of this entity :
     * 1 : The table name can be set manually into PhpDoc of this entity like this : @MtEntity (tableName="users", repository=UserRepository::class)
     * 2 : The name of this entity will be used if the MtEntity mapping is used like this : @MtEntity (repository=UserRepository::class)
     * 3 : If this entity doesn't use the MtEntity mapping it return null
     * @param class-string $entityName
     * @return string|null
     * @throws ReflectionException
     */
    public function getTableName(string $entityName): ?string;

    /**
     * @return array<string>
     * @throws ReflectionException
     */
    public function getTables(): array;

    /**
     * @return array<class-string>
     * @throws ReflectionException
     */
    public function getEntities(): array;

    /**
     * @param class-string $entityName
     * @return class-string|null
     * @throws ReflectionException
     */
    public function getRepository(string $entityName): ?string;

    /**
     * Get the auto increment start number
     * @param class-string $entityName
     * @return int|null
     * @throws ReflectionException
     */
    public function getAutoIncrement(string $entityName): ?int;

    /**
     * @param class-string $entityName
     * @return array<string>
     * @throws ReflectionException
     */
    public function getColumns(string $entityName): array;

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function getPropertiesColumns(string $entityName): array;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnName(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return ColumnType|null
     * @throws ReflectionException
     */
    public function getColumnType(string $entityName, string $property): ?ColumnType;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return int|null
     * @throws ReflectionException
     */
    public function getColumnLength(string $entityName, string $property): ?int;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnTypeDefinition(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return bool|null
     * @throws ReflectionException
     */
    public function isNullable(string $entityName, string $property): ?bool;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getExtra(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnDefault(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnKey(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return MtFk|null
     * @throws ReflectionException
     */
    public function getForeignKey(string $entityName, string $property): ?MtFk;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getConstraintName(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getReferencedTable(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getReferencedColumn(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return FkRule|null
     */
    public function getDeleteRule(string $entityName, string $property): ?FkRule;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return FkRule|null
     */
    public function getUpdateRule(string $entityName, string $property): ?FkRule;

    /**
     * @param class-string $entityName
     * @return array<string, MtOneToOne>
     * @throws ReflectionException
     */
    public function getOneToOne(string $entityName): array;

    /**
     * @param class-string $entityName
     * @return array<string, MtOneToMany>
     * @throws ReflectionException
     */
    public function getOneToMany(string $entityName): array;

    /**
     * @param class-string $entityName
     * @return array<string, MtManyToOne>
     * @throws ReflectionException
     */
    public function getManyToOne(string $entityName): array;

    /**
     * @param class-string $entityName
     * @return array<string, MtManyToMany>
     * @throws ReflectionException
     */
    public function getManyToMany(string $entityName): array;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return bool
     * @throws ReflectionException
     */
    public function isUnsigned(string $entityName, string $property): bool;
}
