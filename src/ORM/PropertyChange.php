<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * Represents a change in a property of an entity.
 * @package MulerTech\Database\ORM
 * @author Sébastien Muler
 */
final readonly class PropertyChange
{
    /**
     * @param string $property
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    public function __construct(
        public string $property,
        public mixed $oldValue,
        public mixed $newValue
    ) {
    }
}
