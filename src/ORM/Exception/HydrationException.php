<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Exception;

/**
 * Class HydrationException.
 *
 * @author Sébastien Muler
 */
class HydrationException extends \RuntimeException
{
    public static function failedToHydrateEntity(string $entityName, ?\Throwable $previous = null): self
    {
        return new self("Failed to hydrate entity of type $entityName", 0, $previous);
    }

    public static function propertyCannotBeNull(string $property, string $entityName): self
    {
        return new self("Property $property of $entityName cannot be null");
    }

    public static function forInvalidProperty(string $entityClass, string $propertyName): self
    {
        return new self("Invalid property '$propertyName' for entity class '$entityClass'");
    }

    public static function forTypeError(string $entityClass, string $propertyName, string $expectedType, string $actualType): self
    {
        return new self("Type error for property '$propertyName' in entity '$entityClass': expected '$expectedType', got '$actualType'");
    }

    /**
     * @param array<string> $missingFields
     */
    public static function forMissingData(string $entityClass, array $missingFields): self
    {
        $fieldsString = implode(', ', $missingFields);

        return new self("Missing required data for entity '$entityClass': $fieldsString");
    }

    public static function forHydrationFailure(string $entityClass, string $reason, ?\Throwable $previous = null): self
    {
        return new self("Hydration failure for entity '$entityClass': $reason", 0, $previous);
    }

    public static function forInvalidEntity(string $className): self
    {
        return new self("Invalid entity class '$className'");
    }

    public function __toString(): string
    {
        $result = parent::__toString();

        // If we have a non-zero code, ensure it's visible in the string representation
        if (0 !== $this->getCode()) {
            $result = str_replace(
                $this->getMessage(),
                $this->getMessage().' (Code: '.$this->getCode().')',
                $result
            );
        }

        return $result;
    }
}
