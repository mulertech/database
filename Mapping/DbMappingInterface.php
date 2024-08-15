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
     * 3 : If this entity doesn't use the MtEntity mapping it return strtolower($entity)
     * @param string $entity
     * @return string
     */
    public function getTableName(string $entity): string;

    /**
     * @return array
     */
    public function getTables(): array;

    /**
     * @return array
     */
    public function getEntities(): array;

    /**
     * @param string $entity
     * @return string|null
     */
    public function getRepository(string $entity): ?string;

    /**
     * Get the auto increment start number
     * @param string $entity
     * @return int|null
     */
    public function getAutoIncrement(string $entity): ?int;
    
    /**
     * @param string $entity
     * @return array
     */
    public function getColumns(string $entity): array;
    
    /**
     * @param string $entity
     * @return array
     */
    public function getPropertiesColumns(string $entity): array;
    
    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getColumnName(string $entity, string $property): ?string;
    
    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getColumnType(string $entity, string $property): ?string;

    /**
     * @param string $entity
     * @param string $property
     * @return bool|null
     */
    public function isNullable(string $entity, string $property): ?bool;
    
    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getExtra(string $entity, string $property): ?string;
    
    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getColumnKey(string $entity, string $property): ?string;

    /**
     * @param string $entity
     * @param string $property
     * @return MtFk|null
     */
    public function getForeignKey(string $entity, string $property): ?MtFk;
    
    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getConstraintName(string $entity, string $property): ?string;

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getReferencedTable(string $entity, string $property): ?string;

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getReferencedColumn(string $entity, string $property): ?string;

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getDeleteRule(string $entity, string $property): ?string;

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     */
    public function getUpdateRule(string $entity, string $property): ?string;
}