<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\State\EntityState;
use MulerTech\Collections\Collection;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

/**
 * Factory optimisée pour la création et l'hydratation d'entités
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final class EntityFactory
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $reflectionCache = [];

    /** @var array<class-string, array<string, ReflectionProperty>> */
    private array $propertiesCache = [];

    /** @var array<class-string, bool> */
    private array $hasConstructorCache = [];

    /**
     * @param EntityHydrator $hydrator
     * @param IdentityMap $identityMap
     */
    public function __construct(
        private readonly EntityHydrator $hydrator,
        private readonly IdentityMap $identityMap
    ) {
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @param bool $useConstructor
     * @return T
     */
    public function create(string $entityClass, array $data = [], bool $useConstructor = true): object
    {
        if (!isset($this->reflectionCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        $reflection = $this->reflectionCache[$entityClass];

        // Create instance
        if ($useConstructor && $this->hasConstructorCache[$entityClass]) {
            $entity = $this->createWithConstructor($reflection, $data);
        } else {
            $entity = $this->createWithoutConstructor($reflection);
        }

        // Hydrate properties
        if (!empty($data)) {
            $this->hydrate($entity, $data);
        }

        /** @var T $entity */
        return $entity;
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @return T
     */
    public function createAndManage(string $entityClass, array $data = []): object
    {
        $entity = $this->create($entityClass, $data, true);

        // Add to identity map if not already present
        $metadata = $this->identityMap->getMetadata($entity);
        if ($metadata === null) {
            $this->identityMap->add($entity);
            return $entity;
        }

        // Si l'entité est déjà présente mais pas en état MANAGED, essayer de mettre à jour son état
        if ($metadata->state !== EntityState::MANAGED) {
            try {
                // Vérifier que la transition est valide avant de l'appliquer
                if ($metadata->state->canTransitionTo(EntityState::MANAGED)) {
                    $managedMetadata = new EntityMetadata(
                        $metadata->className,
                        $metadata->identifier,
                        EntityState::MANAGED,
                        $metadata->originalData,
                        $metadata->loadedAt,
                        new \DateTimeImmutable()
                    );
                    $this->identityMap->updateMetadata($entity, $managedMetadata);
                }
            } catch (\InvalidArgumentException $e) {
                // Si la transition échoue, on continue quand même
                // L'entité sera utilisée avec son état actuel
            }
        }

        return $entity;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $data
     * @return void
     */
    public function hydrate(object $entity, array $data): void
    {
        $entityClass = $entity::class;

        if (!isset($this->propertiesCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        $properties = $this->propertiesCache[$entityClass];

        foreach ($data as $propertyName => $value) {
            if (isset($properties[$propertyName])) {
                $property = $properties[$propertyName];

                try {
                    // Convert value if needed based on property type
                    $convertedValue = $this->convertValue($value, $property);
                    $property->setValue($entity, $convertedValue);
                } catch (\TypeError $e) {
                    // Log or handle type conversion errors
                    throw new \RuntimeException(
                        sprintf(
                            'Failed to hydrate property "%s" of class "%s": %s',
                            $propertyName,
                            $entityClass,
                            $e->getMessage()
                        ),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    public function extract(object $entity): array
    {
        $entityClass = $entity::class;

        if (!isset($this->propertiesCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        $properties = $this->propertiesCache[$entityClass];
        $data = [];

        foreach ($properties as $propertyName => $property) {
            try {
                $data[$propertyName] = $property->getValue($entity);
            } catch (\Error $e) {
                // Handle uninitialized properties
                $data[$propertyName] = null;
            }
        }

        return $data;
    }

    /**
     * @param object $entity
     * @return object
     */
    public function clone(object $entity): object
    {
        $entityClass = $entity::class;
        $data = $this->extract($entity);

        // Remove ID field to create a new entity by setting this to null
        if (isset($data['id'])) {
            $data['id'] = null;
        } elseif (isset($data['uuid'])) {
            $data['uuid'] = null;
        } elseif (isset($data['identifier'])) {
            $data['identifier'] = null;
        }

        // Create a new instance without constructor to avoid issues
        $clonedEntity = $this->create($entityClass, [], false);

        // Hydrate all data except ID fields
        $this->hydrate($clonedEntity, $data);

        return $clonedEntity;
    }

    /**
     * @param class-string $entityClass
     * @return void
     */
    private function cacheReflectionData(string $entityClass): void
    {
        $reflection = new ReflectionClass($entityClass);
        $this->reflectionCache[$entityClass] = $reflection;

        // Cache constructor info
        $constructor = $reflection->getConstructor();
        $this->hasConstructorCache[$entityClass] = $constructor !== null && $constructor->getNumberOfRequiredParameters() === 0;

        // Cache properties
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic()) {
                $property->setAccessible(true);
                $properties[$property->getName()] = $property;
            }
        }
        $this->propertiesCache[$entityClass] = $properties;
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @param array<string, mixed> $data
     * @return T
     */
    private function createWithConstructor(ReflectionClass $reflection, array $data): object
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
            return $reflection->newInstance();
        }

        // Handle constructor with required parameters
        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            if (isset($data[$paramName])) {
                $parameters[] = $data[$paramName];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $parameters[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $parameters[] = null;
            } else {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot create entity %s: missing required constructor parameter "%s"',
                        $reflection->getName(),
                        $paramName
                    )
                );
            }
        }

        return $reflection->newInstanceArgs($parameters);
    }

    /**
     * @template T of object
     * @param ReflectionClass<T> $reflection
     * @return T
     */
    private function createWithoutConstructor(ReflectionClass $reflection): object
    {
        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @param mixed $value
     * @param ReflectionProperty $property
     * @return mixed
     */
    private function convertValue(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // Handle basic type conversions
        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $dbData
     * @return T
     */
    public function createFromDbData(string $entityClass, array $dbData): object
    {
        // Create entity without constructor
        $entity = $this->create($entityClass, [], false);

        // Map database columns to entity properties
        $this->hydrateFromDatabase($entity, $dbData);

        /** @var T $entity */
        return $entity;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $dbData
     * @return void
     */
    private function hydrateFromDatabase(object $entity, array $dbData): void
    {
        $entityClass = $entity::class;

        // If we have a dbMapping available in the hydrator, use it for proper hydration
        try {
            $hydratedEntity = $this->hydrator->hydrate($dbData, $entityClass);

            // Copy the hydrated scalar values to our entity
            if (!isset($this->propertiesCache[$entityClass])) {
                $this->cacheReflectionData($entityClass);
            }

            foreach ($this->propertiesCache[$entityClass] as $propertyName => $property) {
                try {
                    if ($property->isInitialized($hydratedEntity)) {
                        $value = $property->getValue($hydratedEntity);
                        $property->setValue($entity, $value);
                    }
                } catch (\Error $e) {
                    // Handle uninitialized properties
                    continue;
                }
            }

            // Ensure all collections are DatabaseCollection instances
            $this->ensureCollectionsAreDatabaseCollection($entity);

        } catch (\Exception $e) {
            // If EntityHydrator fails (e.g., no dbMapping), fall back to manual conversion
            $this->fallbackHydration($entity, $dbData);
        }
    }

    /**
     * Ensure all collection properties are DatabaseCollection instances
     *
     * @param object $entity
     * @return void
     */
    private function ensureCollectionsAreDatabaseCollection(object $entity): void
    {
        $entityClass = $entity::class;

        if (!isset($this->propertiesCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        foreach ($this->propertiesCache[$entityClass] as $propertyName => $property) {
            try {
                if ($property->isInitialized($entity)) {
                    $value = $property->getValue($entity);

                    // Convert Collections to DatabaseCollection
                    if ($value instanceof Collection && !($value instanceof DatabaseCollection)) {
                        $property->setValue($entity, new DatabaseCollection($value->items()));
                    }
                }
            } catch (\Error $e) {
                // Handle uninitialized properties - initialize with empty DatabaseCollection if it's a collection property
                $type = $property->getType();
                if ($type instanceof \ReflectionNamedType && $type->getName() === Collection::class) {
                    $property->setValue($entity, new DatabaseCollection());
                }
            }
        }
    }

    /**
     * Fallback hydration method when EntityHydrator is not available or fails
     *
     * @param object $entity
     * @param array<string, mixed> $dbData
     * @return void
     */
    private function fallbackHydration(object $entity, array $dbData): void
    {
        $entityClass = $entity::class;

        if (!isset($this->propertiesCache[$entityClass])) {
            $this->cacheReflectionData($entityClass);
        }

        foreach ($dbData as $column => $value) {
            // Convert snake_case to camelCase for property name
            $propertyName = $this->columnToPropertyName($column);

            if (isset($this->propertiesCache[$entityClass][$propertyName])) {
                $property = $this->propertiesCache[$entityClass][$propertyName];

                // Skip relation properties - they should be loaded by EntityRelationLoader
                if ($this->isRelationProperty($property)) {
                    continue;
                }

                try {
                    // Convert database value to property type
                    $convertedValue = $this->convertDatabaseValue($value, $property);
                    $property->setValue($entity, $convertedValue);
                } catch (\TypeError $e) {
                    // Log or handle type conversion error
                    continue;
                }
            }
        }
    }

    /**
     * Check if a property represents a relation based on its type hint
     *
     * @param ReflectionProperty $property
     * @return bool
     */
    private function isRelationProperty(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $typeName = $type->getName();

        // If the type is a class and not a standard PHP class, consider it a relation
        if (class_exists($typeName)) {
            // Exclude standard PHP classes that aren't relations
            $standardClasses = [
                'DateTime',
                'DateTimeImmutable',
                'DateTimeInterface',
                'stdClass',
            ];

            if (in_array($typeName, $standardClasses, true)) {
                return false;
            }

            // Check if it's from a Collection namespace (likely not a relation entity)
            if (str_contains($typeName, 'Collection')) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $column
     * @return string
     */
    private function columnToPropertyName(string $column): string
    {
        // Handle common cases
        if ($column === 'id') {
            return 'id';
        }

        // Convert snake_case to camelCase
        $parts = explode('_', $column);
        $propertyName = array_shift($parts);

        foreach ($parts as $part) {
            $propertyName .= ucfirst($part);
        }

        return $propertyName;
    }

    /**
     * @param mixed $value
     * @param ReflectionProperty $property
     * @return mixed
     */
    private function convertDatabaseValue(mixed $value, ReflectionProperty $property): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $property->getType();

        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        switch ($typeName) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'DateTime':
            case 'DateTimeImmutable':
                if (is_string($value)) {
                    return new $typeName($value);
                }
                return $value;
            case 'array':
                if (is_string($value)) {
                    return json_decode($value, true) ?: [];
                }
                return $value;
            default:
                // For object types, check if it's a JSON encoded value
                if (is_string($value) && class_exists($typeName)) {
                    $decoded = json_decode($value, true);
                    if ($decoded !== null) {
                        // Attempt to reconstruct object from array
                        return $this->create($typeName, $decoded);
                    }
                }
                return $value;
        }
    }
}
