<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\ORM\State\EntityState;
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
     * @param IdentityMap $identityMap
     */
    public function __construct(
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
                    $managedMetadata = $metadata->withState(EntityState::MANAGED);
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

        // Remove ID to create a new entity
        unset($data['id'], $data['identifier'], $data['uuid']);

        return $this->create($entityClass, $data);
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
                        'Cannot create instance of %s: required parameter "%s" not provided',
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
        if ($type === null) {
            return $value;
        }

        // Déterminer le type de façon sécurisée
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
        } elseif (method_exists($type, 'getTypes')) {
            // Pour les types d'union (PHP 8.0+)
            $types = $type->getTypes();
            if (!empty($types) && $types[0] instanceof ReflectionNamedType) {
                $typeName = $types[0]->getName();
            } else {
                $typeName = 'mixed';
            }
        } else {
            $typeName = 'mixed';
        }

        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => is_array($value) ? $value : [$value],
            'DateTime' => $this->convertToDateTime($value),
            'DateTimeImmutable' => $this->convertToDateTimeImmutable($value),
            default => $value,
        };
    }

    /**
     * @param mixed $value
     * @return \DateTime|null
     */
    private function convertToDateTime(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTime) {
            return $value;
        }

        if ($value instanceof \DateTimeImmutable) {
            return \DateTime::createFromImmutable($value);
        }

        if (is_string($value)) {
            try {
                return new \DateTime($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return \DateTimeImmutable|null
     */
    private function convertToDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array{cachedClasses: int, totalProperties: int, memoryUsage: int}
     */
    public function getStatistics(): array
    {
        $totalProperties = 0;
        foreach ($this->propertiesCache as $properties) {
            $totalProperties += count($properties);
        }

        return [
            'cachedClasses' => count($this->reflectionCache),
            'totalProperties' => $totalProperties,
            'memoryUsage' => memory_get_usage(true),
        ];
    }

    /**
     * @param class-string|null $entityClass
     * @return void
     */
    public function clearCache(?string $entityClass = null): void
    {
        if ($entityClass === null) {
            $this->reflectionCache = [];
            $this->propertiesCache = [];
            $this->hasConstructorCache = [];
        } else {
            unset(
                $this->reflectionCache[$entityClass],
                $this->propertiesCache[$entityClass],
                $this->hasConstructorCache[$entityClass]
            );
        }
    }
}
