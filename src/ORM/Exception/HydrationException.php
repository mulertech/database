<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Exception;

use RuntimeException;
use Throwable;

/**
 * Class HydrationException
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class HydrationException extends RuntimeException
{
    /**
     * @param string $entityName
     * @param Throwable|null $previous
     * @return self
     */
    public static function failedToHydrateEntity(string $entityName, ?Throwable $previous = null): self
    {
        return new self("Failed to hydrate entity of type $entityName", 0, $previous);
    }

    /**
     * @param string $property
     * @param string $entityName
     * @return self
     */
    public static function propertyCannotBeNull(string $property, string $entityName): self
    {
        return new self("Property $property of $entityName cannot be null");
    }
}
