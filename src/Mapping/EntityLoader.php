<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping;

use MulerTech\Database\Mapping\Attributes\MtEntity;
use MulerTech\Database\Mapping\Attributes\MtColumn;
use MulerTech\FileManipulation\FileType\Php;
use ReflectionClass;

/**
 * Handles entity class discovery and processing
 */
final class EntityLoader
{
    public function __construct(
        private readonly bool $recursive = true
    ) {
    }

    /**
     * Create loader with recursive search enabled
     */
    public static function recursive(): self
    {
        return new self(recursive: true);
    }

    /**
     * Create loader with recursive search disabled
     */
    public static function nonRecursive(): self
    {
        return new self(recursive: false);
    }

    /**
     * Load entities from given path
     *
     * @param string $entitiesPath
     * @return array<ReflectionClass<object>>
     */
    public function loadFromPath(string $entitiesPath): array
    {
        $classNames = Php::getClassNames($entitiesPath, $this->recursive);
        $reflections = [];

        foreach ($classNames as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            // Only include classes with MtEntity attribute
            if ($this->hasEntityAttribute($reflection)) {
                $reflections[] = $reflection;
            }
        }

        return $reflections;
    }

    /**
     * Check if class has MtEntity attribute
     * @param ReflectionClass<object> $reflection
     */
    private function hasEntityAttribute(ReflectionClass $reflection): bool
    {
        return !empty($reflection->getAttributes(MtEntity::class));
    }
}
