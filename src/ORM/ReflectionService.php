<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

class ReflectionService
{
    /**
     * @var array<string, array<string, ReflectionProperty>>
     */
    private array $reflectionCache = [];

    /**
     * @param class-string $entityClass
     * @param string $propertyName
     * @return ReflectionProperty|null
     * @throws ReflectionException
     */
    public function getProperty(string $entityClass, string $propertyName): ?ReflectionProperty
    {
        if (!isset($this->reflectionCache[$entityClass])) {
            $this->reflectionCache[$entityClass] = [];
        }

        if (isset($this->reflectionCache[$entityClass][$propertyName])) {
            return $this->reflectionCache[$entityClass][$propertyName];
        }

        $reflection = new ReflectionClass($entityClass);
        if ($reflection->hasProperty($propertyName)) {
            $property = $reflection->getProperty($propertyName);
            $this->reflectionCache[$entityClass][$propertyName] = $property;
            return $property;
        }

        return null;
    }

    /**
     * @param class-string $entityClass
     * @param string $propertyName
     * @return bool
     */
    public function isPropertyNullable(string $entityClass, string $propertyName): bool
    {
        try {
            $property = $this->getProperty($entityClass, $propertyName);
            if ($property !== null) {
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType) {
                    return $type->allowsNull();
                }
            }
        } catch (ReflectionException) {
            // If we can't determine, assume nullable
        }

        return true;
    }

    /**
     * @param class-string $entityClass
     * @return array<string, ReflectionProperty>
     * @throws ReflectionException
     */
    public function getNonStaticProperties(string $entityClass): array
    {
        $reflection = new ReflectionClass($entityClass);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic()) {
                $properties[$property->getName()] = $property;
            }
        }

        return $properties;
    }
}
