<?php

namespace mtphp\Database\Mapping;

/**
 * Interface DbMappingInterface
 * @package mtphp\Database\Mapping
 * @author Sébastien Muler
 */
interface DbMappingInterface
{


    /**
     * Return the table name of this entity :
     * 1 : The table name can be set manually into PhpDoc of this entity like this : @MtEntity (tableName="users", repository=UserRepository::class)
     * 2 : The name of this entity will be used if the MtEntity mapping is used like this : @MtEntity (repository=UserRepository::class)
     * 3 : If this entity doesn't use the MtEntity mapping it return null
     * @param string|null $entity
     * @return string
     */
    public function getTableName(string $entity): ?string;

    /**
     * @return array
     */
    public function getTableList(): array;

    /**
     * @param string $entity
     * @return string
     */
    public function getRepository(string $entity): string;
}