<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use MulerTech\Database\Mapping\DbMappingInterface;

/**
 * Helper class for relations metadata operations
 */
final class MetadataRelationsHelper
{
    /**
     * Add relation to relations array if it exists
     * @param array<string, mixed> &$relations
     * @param string $relationType
     * @param array<string, mixed>|null $relationData
     * @return void
     */
    public function addRelationIfExists(array &$relations, string $relationType, ?array $relationData): void
    {
        if ($relationData !== null && !empty($relationData)) {
            $relations[$relationType] = $relationData;
        }
    }

    /**
     * Build all relations metadata for an entity
     * @param DbMappingInterface $dbMapping
     * @param class-string $entityClass
     * @return array<string, mixed>
     */
    public function buildRelationsData(DbMappingInterface $dbMapping, string $entityClass): array
    {
        $relations = [];

        $this->addRelationIfExists($relations, 'oneToOne', $dbMapping->getOneToOne($entityClass));
        $this->addRelationIfExists($relations, 'oneToMany', $dbMapping->getOneToMany($entityClass));
        $this->addRelationIfExists($relations, 'manyToOne', $dbMapping->getManyToOne($entityClass));
        $this->addRelationIfExists($relations, 'manyToMany', $dbMapping->getManyToMany($entityClass));

        return $relations;
    }
}
