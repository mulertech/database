<?php

declare(strict_types=1);

namespace MulerTech\Database\Cache;

/**
 * Cache optimisé pour les métadonnées d'entités
 * @package MulerTech\Database\Cache
 * @author Sébastien Muler
 */
class MetadataCache extends MemoryCache
{
    /**
     * @var array<string, bool>
     */
    private array $permanentKeys = [];

    /**
     * @param CacheConfig|null $config
     */
    public function __construct(?CacheConfig $config = null)
    {
        parent::__construct($config ?? new CacheConfig(
            maxSize: 5000,
            ttl: 0, // No expiration for metadata
            enableStats: false,
            evictionPolicy: 'lru'
        ));
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param bool $permanent
     * @return void
     */
    public function setMetadata(string $key, mixed $value, bool $permanent = true): void
    {
        parent::set($key, $value, 0);

        if ($permanent) {
            $this->permanentKeys[$key] = true;
        }
    }

    /**
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        if (isset($this->permanentKeys[$key])) {
            return; // Don't delete permanent metadata
        }

        parent::delete($key);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $permanentData = [];

        // Preserve permanent keys
        foreach (array_keys($this->permanentKeys) as $key) {
            $permanentData[$key] = $this->get($key);
        }

        parent::clear();

        // Restore permanent keys
        foreach ($permanentData as $key => $value) {
            $this->setMetadata($key, $value, true);
        }
    }

    /**
     * @param string $entityClass
     * @return mixed
     */
    public function getEntityMetadata(string $entityClass): mixed
    {
        return $this->get($this->getEntityKey($entityClass));
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
     * @param string $entityClass
     * @param string $relation
     * @return mixed
     */
    public function getRelationMetadata(string $entityClass, string $relation): mixed
    {
        return $this->get($this->getRelationKey($entityClass, $relation));
    }

    /**
     * @param string $entityClass
     * @param string $relation
     * @param mixed $metadata
     * @return void
     */
    public function setRelationMetadata(string $entityClass, string $relation, mixed $metadata): void
    {
        $key = $this->getRelationKey($entityClass, $relation);
        $this->setMetadata($key, $metadata, true);
        $this->tag($key, ['relation_metadata', $entityClass]);
    }

    /**
     * @param string $entityClass
     * @return void
     */
    public function invalidateEntityMetadata(string $entityClass): void
    {
        $this->invalidateTag($entityClass);
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
     * @param string $entityClass
     * @param string $relation
     * @return string
     */
    private function getRelationKey(string $entityClass, string $relation): string
    {
        return sprintf('relation:%s:%s', $entityClass, $relation);
    }
}
