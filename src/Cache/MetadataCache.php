<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

use Exception;
use MulerTech\Database\Mapping\DbMappingInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;

/**
 * Class MetadataCache
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
final class MetadataCache extends MemoryCache
{
    /**
     * @param CacheConfig|null $config
     * @param DbMappingInterface|null $dbMapping
     */
    public function __construct(
        ?CacheConfig $config = null,
        private readonly ?DbMappingInterface $dbMapping = null
    ) {
        $metadataConfig = new CacheConfig(
            maxSize: $config->maxSize ?? 50000,
            ttl: 0, // No expiration for metadata
            enableStats: $config->enableStats ?? true,
            evictionPolicy: $config->evictionPolicy ?? 'lru'
        );

        parent::__construct($metadataConfig);
    }

    /**
     * @param string $key
     * @param mixed $metadata
     * @param bool $isPermanent
     * @return void
     */
    public function setMetadata(string $key, mixed $metadata, bool $isPermanent = false): void
    {
        $this->set($key, $metadata, $isPermanent ? 0 : $this->config->ttl);
    }

    /**
     * @param string $entityClass
     * @param mixed $metadata
     * @return void
     */
    public function setEntityMetadata(string $entityClass, mixed $metadata): void
    {
        $this->setMetadata($this->getEntityKey($entityClass), $metadata, true);
        $this->tag($this->getEntityKey($entityClass), ['entity_metadata']);
    }

    /**
     * @param string $entityClass
     * @param string $property
     * @return mixed
     */
    public function getPropertyMetadata(string $entityClass, string $property): mixed
    {
        return $this->get($this->getPropertyKey($entityClass, $property));
    }

    /**
     * @param string $entityClass
     * @param string $property
     * @param mixed $metadata
     * @return void
     */
    public function setPropertyMetadata(string $entityClass, string $property, mixed $metadata): void
    {
        $key = $this->getPropertyKey($entityClass, $property);
        $this->setMetadata($key, $metadata, true);
        $this->tag($key, ['property_metadata', $entityClass]);
    }

    /**
     * Check if entity metadata has been warmed up
     * @param string $entityClass
     * @return bool
     */
    public function isWarmedUp(string $entityClass): bool
    {
        return $this->get($entityClass . ':warmed') === true;
    }

    /**
     * Warm up a single entity class
     * @param class-string $entityClass
     * @return void
     * @throws Exception
     */
    private function warmUpEntity(string $entityClass): void
    {
        if ($this->dbMapping === null || $this->isWarmedUp($entityClass)) {
            return;
        }

        try {
            // Cache table name
            $tableName = $this->dbMapping->getTableName($entityClass);
            if ($tableName !== null) {
                $this->setEntityMetadata($entityClass . ':table', $tableName);
            }

            // Cache properties to columns mapping
            $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityClass);
            $this->setEntityMetadata($entityClass . ':properties', $propertiesColumns);

            // Cache primary key information
            // DbMapping doesn't have getPrimaryKey, so we need to find it
            $primaryKey = $this->findPrimaryKey($entityClass);
            if ($primaryKey !== null) {
                $this->setEntityMetadata($entityClass . ':primaryKey', $primaryKey);
            }

            // Cache relations
            $relations = [];

            // OneToOne relations
            $oneToOne = $this->dbMapping->getOneToOne($entityClass);
            if (!empty($oneToOne)) {
                $relations['oneToOne'] = $oneToOne;
            }

            // OneToMany relations
            $oneToMany = $this->dbMapping->getOneToMany($entityClass);
            if (!empty($oneToMany)) {
                $relations['oneToMany'] = $oneToMany;
            }

            // ManyToOne relations
            $manyToOne = $this->dbMapping->getManyToOne($entityClass);
            if (!empty($manyToOne)) {
                $relations['manyToOne'] = $manyToOne;
            }

            // ManyToMany relations
            $manyToMany = $this->dbMapping->getManyToMany($entityClass);
            if (!empty($manyToMany)) {
                $relations['manyToMany'] = $manyToMany;
            }

            if (!empty($relations)) {
                $this->setEntityMetadata($entityClass . ':relations', $relations);
            }

            // Cache reflection data
            $reflection = new ReflectionClass($entityClass);
            $reflectionData = [
                'isInstantiable' => $reflection->isInstantiable(),
                'hasConstructor' => $reflection->getConstructor() !== null,
                'constructorParams' => [],
                'properties' => [],
            ];

            if ($reflection->getConstructor() !== null) {
                foreach ($reflection->getConstructor()->getParameters() as $param) {
                    $reflectionData['constructorParams'][] = [
                        'name' => $param->getName(),
                        'isOptional' => $param->isOptional(),
                        'hasDefault' => $param->isDefaultValueAvailable(),
                        'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                    ];
                }
            }

            foreach ($reflection->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $type = $property->getType();
                    $typeName = null;
                    if ($type instanceof ReflectionNamedType) {
                        $typeName = $type->getName();
                    } elseif ($type instanceof ReflectionUnionType) {
                        $typeName = implode('|', array_map(static function ($t) {
                            return $t instanceof ReflectionNamedType ? $t->getName() : (string)$t;
                        }, $type->getTypes()));
                    } elseif ($type instanceof ReflectionIntersectionType) {
                        $typeName = implode('&', array_map(static function ($t) {
                            return $t instanceof ReflectionNamedType ? $t->getName() : (string)$t;
                        }, $type->getTypes()));
                    }

                    $reflectionData['properties'][$property->getName()] = [
                        'isPublic' => $property->isPublic(),
                        'isProtected' => $property->isProtected(),
                        'isPrivate' => $property->isPrivate(),
                        'type' => $typeName,
                    ];
                }
            }

            $this->setEntityMetadata($entityClass . ':reflection', $reflectionData);

            // Mark entity as warmed up
            $this->setEntityMetadata($entityClass . ':warmed', true);

        } catch (Exception $e) {
            throw new RuntimeException(
                sprintf('Failed to warm up entity metadata for %s: %s', $entityClass, $e->getMessage()),
                0,
                $e
            );
            // Log error but continue with other entities
            // In production, you'd want to log this properly
        }
    }

    /**
     * @param string $entityClass
     * @return string
     */
    private function getEntityKey(string $entityClass): string
    {
        return sprintf('entity:%s', $entityClass);
    }

    /**
     * @param string $entityClass
     * @param string $property
     * @return string
     */
    private function getPropertyKey(string $entityClass, string $property): string
    {
        return sprintf('property:%s:%s', $entityClass, $property);
    }

    /**
     * Get cached table name for entity or load it if not cached
     * @param string $entityClass
     * @return string|null
     * @throws Exception
     */
    public function getTableName(string $entityClass): ?string
    {
        $cached = $this->get($entityClass . ':table');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':table');
        }

        return $cached;
    }

    /**
     * Get cached properties to columns mapping or load it if not cached
     * @param string $entityClass
     * @return array<string, string>|null
     * @throws Exception
     */
    public function getPropertiesColumns(string $entityClass): ?array
    {
        $cached = $this->get($entityClass . ':properties');

        if ($cached === null && !$this->isWarmedUp($entityClass)) {
            /** @var class-string $entityClass */
            $this->warmUpEntity($entityClass);
            $cached = $this->get($entityClass . ':properties');
        }

        return $cached;
    }

    /**
     * Find primary key column for an entity
     * @param class-string $entityClass
     * @return string|null
     */
    private function findPrimaryKey(string $entityClass): ?string
    {
        if ($this->dbMapping === null) {
            return null;
        }

        try {
            $propertiesColumns = $this->dbMapping->getPropertiesColumns($entityClass);

            foreach ($propertiesColumns as $property => $column) {
                $columnKey = $this->dbMapping->getColumnKey($entityClass, $property);
                if ($columnKey === 'PRI') {
                    return $property;
                }
            }

            // If no PRI key found, check for 'id' property as fallback
            if (isset($propertiesColumns['id'])) {
                return 'id';
            }

            return null;
        } catch (Exception) {
            return null;
        }
    }
}
