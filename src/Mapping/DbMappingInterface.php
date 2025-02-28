<?php

namespace MulerTech\Database\Mapping;

/**
 * Interface DbMappingInterface
 * @package MulerTech\Database\Mapping
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
     */
    public function getTableName(string $entityName): ?string;

    /**
     * @return array<string>
     */
    public function getTables(): array;

    /**
     * @return array<class-string>
     */
    public function getEntities(): array;

    /**
     * @param class-string $entityName
     * @return class-string|null
     */
    public function getRepository(string $entityName): ?string;

    /**
     * Get the auto increment start number
     * @param class-string $entityName
     * @return int|null
     */
    public function getAutoIncrement(string $entityName): ?int;
    
    /**
     * @param class-string $entityName
     * @return array<string>
     */
    public function getColumns(string $entityName): array;
    
    /**
     * @param class-string $entityName
     * @return array<string, string>
     */
    public function getPropertiesColumns(string $entityName): array;
    
    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getColumnName(string $entityName, string $property): ?string;
    
    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getColumnType(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return bool|null
     */
    public function isNullable(string $entityName, string $property): ?bool;
    
    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getExtra(string $entityName, string $property): ?string;
    
    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getColumnKey(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return MtFk|null
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
     * @return string|null
     */
    public function getDeleteRule(string $entityName, string $property): ?string;

    /**
     * @param class-string $entityName
     * @param string $property
     * @return string|null
     */
    public function getUpdateRule(string $entityName, string $property): ?string;
}