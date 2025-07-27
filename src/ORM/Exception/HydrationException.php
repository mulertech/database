<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Exception;

use RuntimeException;
use Throwable;

class HydrationException extends RuntimeException
{
    public static function failedToHydrateEntity(string $entityName, ?Throwable $previous = null): self
    {
        return new self("Failed to hydrate entity of type $entityName", 0, $previous);
    }

    public static function propertyCannotBeNull(string $property, string $entityName): self
    {
        return new self("Property $property of $entityName cannot be null");
    }
}
