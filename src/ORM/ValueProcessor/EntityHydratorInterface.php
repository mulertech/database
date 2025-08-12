<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use MulerTech\Database\Mapping\MetadataRegistry;
use ReflectionException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface EntityHydratorInterface
{
    /**
     * Hydrate an entity from array data
     *
     * @param array<string, mixed> $data
     * @param class-string $entityName
     * @return object
     * @throws ReflectionException
     */
    public function hydrate(array $data, string $entityName): object;

    /**
     * Get the metadata registry
     *
     * @return MetadataRegistry
     */
    public function getMetadataRegistry(): MetadataRegistry;
}
