<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Processor;

use DateTimeImmutable;
use Error;
use InvalidArgumentException;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\EntityMetadata;
use MulerTech\Database\ORM\IdentityMap;
use ReflectionClass;

/**
 * Utilities for entity processing operations
 */
readonly class EntityProcessor
{
    public function __construct(
        private ChangeDetector $changeDetector,
        private IdentityMap $identityMap
    ) {
    }

    /**
     * Extract entity ID from common property names
     */
    public function extractEntityId(object $entity): int|string|null
    {
        $reflection = new ReflectionClass($entity);

        foreach (['id', 'identifier', 'uuid'] as $property) {
            if ($reflection->hasProperty($property)) {
                $reflectionProperty = $reflection->getProperty($property);
                if ($reflectionProperty->isInitialized($entity)) {
                    $value = $reflectionProperty->getValue($entity);
                    if ((is_int($value) || is_string($value))) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Copy data from source entity to target entity
     */
    public function copyEntityData(object $source, object $target): void
    {
        if ($source::class !== $target::class) {
            throw new InvalidArgumentException('Cannot copy data between different entity types');
        }

        $this->copyProperties($source, $target);
        $this->updateTargetMetadata($target);
    }

    private function copyProperties(object $source, object $target): void
    {
        $reflection = new ReflectionClass($source);

        foreach ($reflection->getProperties() as $property) {
            if ($this->shouldSkipProperty($property->getName())) {
                continue;
            }

            try {
                $value = $property->getValue($source);
                $property->setValue($target, $value);
            } catch (Error) {
                // Handle readonly properties or other restrictions
                continue;
            }
        }
    }

    private function shouldSkipProperty(string $propertyName): bool
    {
        return $propertyName === 'id';
    }

    private function updateTargetMetadata(object $target): void
    {
        $metadata = $this->identityMap->getMetadata($target);
        if ($metadata === null) {
            return;
        }

        $newData = $this->changeDetector->extractCurrentData($target);
        $newMetadata = new EntityMetadata(
            $metadata->className,
            $metadata->identifier,
            $metadata->state,
            $newData,
            $metadata->loadedAt,
            new DateTimeImmutable()
        );

        $this->identityMap->updateMetadata($target, $newMetadata);
    }
}
