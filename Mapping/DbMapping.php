<?php

namespace mtphp\Database\Mapping;

use mtphp\Database\NonRelational\DocumentStore\FileManipulation;
use mtphp\Database\NonRelational\DocumentStore\PathManipulation;
use RuntimeException;
use mtphp\PhpDocExtractor\PhpDocExtractorInterface;
use ReflectionException;

class DbMapping implements DbMappingInterface
{

    /**
     * @var PhpDocExtractorInterface
     */
    private $phpDocExtractor;
    /**
     * @var string $entityPath
     */
    private $entityPath;

    public function __construct(PhpDocExtractorInterface $phpDocExtractor, string $entityPath)
    {
        $this->phpDocExtractor = $phpDocExtractor;
        $this->entityPath = $entityPath;
    }

    /**
     * @param string $entity
     * @return string|null
     * @throws ReflectionException
     */
    public function getTableName(string $entity): ?string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtEntity = $phpDocs->getClassMappingOf(MtEntity::class);
        if (is_null($mappingMtEntity)) {
            return null;
        }
        if (is_null($mappingMtEntity->getTableName())) {
            return ($pos = strrpos($entity, '\\')) ? strtolower(substr($entity, $pos + 1)) : strtolower($entity);
        }
        return $mappingMtEntity->getTableName();
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getTableList(): array
    {
        $fileList = PathManipulation::fileList($this->entityPath);
        $fileManipulation = new FileManipulation();
        $tableList = array_map(function ($entity) use ($fileManipulation) {
            return $this->getTableName($fileManipulation->fileClassName($entity));
        }, $fileList);
        return array_values(array_filter($tableList, static function ($value) {
            return !is_null($value);
        }));
    }

    /**
     * @param string $entity
     * @return string
     * @throws ReflectionException
     */
    public function getRepository(string $entity): string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtEntity = $phpDocs->getClassMappingOf(MtEntity::class);
        if (is_null($mappingMtEntity)) {
            throw new RuntimeException(sprintf('The MtEntity mapping is not implemented into the %s class.', $entity));
        }
        return $mappingMtEntity->getRepository();
    }

    /**
     * @throws ReflectionException
     */
    public function getColumnList(string $entity): array
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtColumn::class);
        $columnList = [];
        array_walk($mappingMtColumn, static function ($value, $key) use (&$columnList) {
            $columnList[] = $value->getColumnName() ?? $key;
        });
        return $columnList;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnName(string $entity, string $property): ?string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtColumn::class);
        return $mappingMtColumn[$property]->getColumnName() ?? $property;
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getType(string $entity, string $property): ?string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtColumn::class);
        return $mappingMtColumn[$property]->getColumnType();
    }

    /**
     * @param string $entity
     * @param string $property
     * @return bool
     * @throws ReflectionException
     */
    public function isNullable(string $entity, string $property): bool
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtColumn::class);
        return $mappingMtColumn[$property]->isNullable();
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getExtra(string $entity, string $property): ?string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtColumn::class);
        return $mappingMtColumn[$property]->getExtra();
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string|null
     * @throws ReflectionException
     */
    public function getColumnKey(string $entity, string $property): ?string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtColumn::class);
        return $mappingMtColumn[$property]->getColumnKey();
    }

    /**
     * @throws ReflectionException
     */
    public function getConstraintName(string $entity, string $property): string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtFk::class);
        return $mappingMtColumn[$property]->getConstraintName($this->getTableName($entity), $this->getColumnName($entity, $property));
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getReferencedTable(string $entity, string $property): string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtFk::class);
        return $mappingMtColumn[$property]->getReferencedTable();
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getReferencedColumn(string $entity, string $property): string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtFk::class);
        return $mappingMtColumn[$property]->getReferencedColumn();
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getDeleteRule(string $entity, string $property): string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtFk::class);
        return $mappingMtColumn[$property]->getDeleteRule();
    }

    /**
     * @param string $entity
     * @param string $property
     * @return string
     * @throws ReflectionException
     */
    public function getUpdateRule(string $entity, string $property): string
    {
        $phpDocs = $this->phpDocExtractor->getClassMetadata($entity);
        $mappingMtColumn = $phpDocs->getPropertiesMappingOf(MtFk::class);
        return $mappingMtColumn[$property]->getUpdateRule();
    }

}