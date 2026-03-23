<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Relations;

/**
 * Validator for ORM relations.
 *
 * @author Sébastien Muler
 */
readonly class RelationValidator
{
    /**
     * Validate and return target entity class.
     *
     * @param array<string, mixed> $relationData
     *
     * @return class-string
     */
    public function validateTargetEntity(
        array $relationData,
        string $sourceEntityClass,
        string $propertyName,
    ): string {
        $target = $relationData['targetEntity'] ?? '';
        if (!is_string($target) || '' === $target || !class_exists($target)) {
            $targetStr = is_string($target) ? $target : gettype($target);
            throw new \RuntimeException(sprintf('Target entity class "%s" for relation "%s" on entity "%s" does not exist.', $targetStr, $propertyName, $sourceEntityClass));
        }

        return $target;
    }

    /**
     * Validate relation property (string, non-empty).
     *
     * @param string $propertyType Property type name (e.g., 'join property', 'inverse join property')
     */
    public function validateRelationProperty(
        mixed $value,
        string $propertyType,
        string $entityClass,
        string $propertyName,
    ): string {
        if (null === $value || '' === $value) {
            throw new \RuntimeException(sprintf('The "%s" is not defined for the class "%s" and property "%s". Please check the mapping configuration.', $propertyType, $entityClass, $propertyName));
        }

        if (!is_string($value)) {
            throw new \RuntimeException(sprintf('The "%s" must be a string for the class "%s" and property "%s". Please check the mapping configuration.', $propertyType, $entityClass, $propertyName));
        }

        return $value;
    }

    /**
     * Validate mapped by property and return as class-string.
     *
     * @param array<string, mixed> $relation
     *
     * @return class-string
     */
    public function validateMappedBy(array $relation, string $entityClass, string $propertyName): string
    {
        $mappedBy = $relation['mappedBy'] ?? null;
        if (null === $mappedBy) {
            throw new \RuntimeException(sprintf('Mapped by property is not defined for the class "%s" and property "%s". Please check the mapping configuration.', $entityClass, $propertyName));
        }

        if (!is_string($mappedBy) || !class_exists($mappedBy)) {
            throw new \RuntimeException(sprintf('Invalid mappedBy value for class "%s" property "%s": expected a valid class-string.', $entityClass, $propertyName));
        }

        return $mappedBy;
    }

    /**
     * Check if a setter method accepts null values.
     */
    public function setterAcceptsNull(object $entity, string $setterMethod): bool
    {
        $reflection = new \ReflectionClass($entity);

        if (!$reflection->hasMethod($setterMethod)) {
            return false;
        }

        $method = $reflection->getMethod($setterMethod);
        $parameters = $method->getParameters();

        if (empty($parameters)) {
            return false;
        }

        $firstParameter = $parameters[0];
        $type = $firstParameter->getType();

        // If no type hint, assume it accepts null
        if (null === $type) {
            return true;
        }

        // Check if the parameter allows null
        return $type->allowsNull();
    }

    /**
     * Safely set a relation value on an entity using its setter.
     *
     * @return bool True if the value was set, false if skipped
     */
    public function setRelationValue(object $entity, string $property, mixed $value): bool
    {
        $setter = 'set'.ucfirst($property);

        if (!method_exists($entity, $setter)) {
            return false;
        }

        // If value is null, check if setter accepts null
        if (null === $value && !$this->setterAcceptsNull($entity, $setter)) {
            return false;
        }

        $entity->$setter($value);

        return true;
    }

    /**
     * Validate inverse join property for OneToMany relations.
     *
     * @param array<string, mixed> $oneToMany
     */
    public function validateInverseJoinProperty(array $oneToMany, string $entityClass, string $propertyName): string
    {
        $mappedByProperty = $oneToMany['inverseJoinProperty'] ?? null;

        if (empty($mappedByProperty)) {
            throw new \RuntimeException(sprintf('The "mappedBy" attribute is not defined for the OneToMany relation "%s" on entity "%s".', $propertyName, $entityClass));
        }

        if (!is_string($mappedByProperty)) {
            throw new \RuntimeException(sprintf('The "mappedBy" attribute must be a string for the OneToMany relation "%s" on entity "%s".', $propertyName, $entityClass));
        }

        return $mappedByProperty;
    }

    /**
     * Validate that entity data contains valid key-value pairs for creating managed entities.
     *
     * @return array<string, scalar|null>
     */
    public function validateEntityData(mixed $entityData): array
    {
        if (!is_array($entityData)) {
            return [];
        }

        $validatedEntityData = [];
        foreach ($entityData as $key => $value) {
            $stringKey = (string) $key;
            // Accept only scalar or null values
            if (is_scalar($value) || null === $value) {
                $validatedEntityData[$stringKey] = $value;
            } else {
                $validatedEntityData[$stringKey] = null;
            }
        }

        return $validatedEntityData;
    }
}
