<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Cache;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Helper class for reflection-related metadata operations
 */
final class MetadataReflectionHelper
{
    /**
     * Get constructor parameters metadata
     * @param ReflectionClass<object> $reflection
     * @return array<int, array<string, mixed>>
     */
    public function getConstructorParams(ReflectionClass $reflection): array
    {
        $constructorParams = [];

        if ($reflection->getConstructor() !== null) {
            foreach ($reflection->getConstructor()->getParameters() as $param) {
                $constructorParams[] = [
                    'name' => $param->getName(),
                    'isOptional' => $param->isOptional(),
                    'hasDefault' => $param->isDefaultValueAvailable(),
                    'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                ];
            }
        }

        return $constructorParams;
    }

    /**
     * Get properties metadata from reflection
     * @param ReflectionClass<object> $reflection
     * @return array<string, array<string, mixed>>
     */
    public function getPropertiesMetadata(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = [
                    'isPublic' => $property->isPublic(),
                    'isProtected' => $property->isProtected(),
                    'isPrivate' => $property->isPrivate(),
                    'type' => $this->getPropertyTypeName($property->getType()),
                ];
            }
        }

        return $properties;
    }

    /**
     * Get property type name from reflection type
     * @param \ReflectionType|null $type
     * @return string|null
     */
    public function getPropertyTypeName(?\ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(static function ($reflectionType) {
                return $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : (string)$reflectionType;
            }, $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(function ($reflectionType) {
                return $reflectionType instanceof ReflectionNamedType ? $reflectionType->getName() : (string)$reflectionType;
            }, $type->getTypes()));
        }

        return null;
    }

    /**
     * Build complete reflection metadata for an entity
     * @param ReflectionClass<object> $reflection
     * @return array<string, mixed>
     */
    public function buildReflectionData(ReflectionClass $reflection): array
    {
        return [
            'isInstantiable' => $reflection->isInstantiable(),
            'hasConstructor' => $reflection->getConstructor() !== null,
            'constructorParams' => $this->getConstructorParams($reflection),
            'properties' => $this->getPropertiesMetadata($reflection),
        ];
    }
}
