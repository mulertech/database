<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Engine\Persistence;

/**
 * @author Sébastien Muler
 */
readonly class UpdateValueProcessor
{
    /**
     * Extract foreign key ID from various value types.
     *
     * @param array<string, mixed>|object|string|null $value
     */
    public function extractForeignKeyId(array|object|string|null $value): int|string|null
    {
        if (is_array($value) && isset($value['__entity__'], $value['__id__'])) {
            // Serialized entity reference from ChangeDetector
            $id = $value['__id__'];

            return (is_int($id) || is_string($id)) ? $id : null;
        }

        if (is_object($value)) {
            // Extract ID from object
            return $this->getEntityId($value);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Validate if array has string keys.
     *
     * @param array<mixed, mixed> $array
     */
    public function isValidArrayWithStringKeys(array $array): bool
    {
        return array_all(array_keys($array), static fn ($key) => is_string($key));
    }

    /**
     * Check if value is processable for UPDATE.
     */
    public function isProcessableValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $this->isValidArrayWithStringKeys($value);
        }

        return is_object($value) || is_string($value) || null === $value;
    }

    /**
     * Get entity ID using getId method.
     */
    private function getEntityId(object $entity): int|string|null
    {
        if (!method_exists($entity, 'getId')) {
            return null;
        }

        return $entity->getId();
    }
}
