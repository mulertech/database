<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\ValueProcessor;

use ReflectionException;

/**
 * Interface for entity hydration functionality
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
}
