<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use MulerTech\Database\Mapping\MetadataRegistry;

/**
 * @author Sébastien Muler
 */
interface EntityHydratorInterface
{
    /**
     * Hydrate an entity from array data.
     *
     * @param array<string, mixed> $data
     * @param class-string         $entityName
     *
     * @throws \ReflectionException
     */
    public function hydrate(array $data, string $entityName): object;

    /**
     * Get the metadata registry.
     */
    public function getMetadataRegistry(): MetadataRegistry;
}
