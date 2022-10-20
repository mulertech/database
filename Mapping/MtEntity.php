<?php

namespace mtphp\Database\Mapping;

/**
 * Class MtEntity
 * @package mtphp\Database\Mapping
 * @author Sébastien Muler
 * @Annotation
 */
class MtEntity
{

    /**
     * @var string $repository
     */
    public $repository;
    /**
     * @var string $tableName
     */
    public $tableName;

    /**
     * @param string $tableName
     */
    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * @return string|null
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * @param string $repository
     */
    public function setRepository(string $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * @return string
     */
    public function getRepository(): ?string
    {
        return $this->repository;
    }

}