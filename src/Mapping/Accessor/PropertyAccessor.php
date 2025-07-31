<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Accessor;

use MulerTech\Database\Mapping\EntityMetadata;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PropertyAccessor
{
    /**
     * @param object $entity
     * @param string $property
     * @param EntityMetadata|null $metadata
     * @return mixed
     */
    public function getValue(object $entity, string $property, ?EntityMetadata $metadata = null): mixed
    {
        $getter = $metadata?->getGetter($property);
        if ($getter !== null) {
            return $entity->$getter();
        }
        throw new RuntimeException(
            sprintf('No getter found for property %s::%s', $entity::class, $property)
        );
    }

    /**
     * @param object $entity
     * @param string $property
     * @param mixed $value
     * @param EntityMetadata|null $metadata
     * @return void
     */
    public function setValue(object $entity, string $property, mixed $value, ?EntityMetadata $metadata = null): void
    {
        $setter = $metadata?->getSetter($property);
        if ($setter !== null) {
            $entity->$setter($value);
            return;
        }
        throw new RuntimeException(
            sprintf('No setter found for property %s::%s', $entity::class, $property)
        );
    }
}
