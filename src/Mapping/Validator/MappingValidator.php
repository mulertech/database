<?php

declare(strict_types=1);

namespace MulerTech\Database\Mapping\Validator;

use MulerTech\Database\Mapping\Attributes\{MtEntity, MtColumn, MtFk, MtOneToOne, MtOneToMany, MtManyToOne, MtManyToMany};
use ReflectionClass;
use RuntimeException;

/**
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MappingValidator
{
    private const ALLOWED_ATTRIBUTES = [
        MtEntity::class,
        MtColumn::class,
        MtFk::class,
        MtOneToOne::class,
        MtOneToMany::class,
        MtManyToOne::class,
        MtManyToMany::class,
    ];

    /**
     * @param class-string $entityClass
     * @return void
     * @throws RuntimeException
     */
    public function validateEntity(string $entityClass): void
    {
        $reflection = new ReflectionClass($entityClass);

        // Validate class attributes
        foreach ($reflection->getAttributes() as $attribute) {
            if (!in_array($attribute->getName(), self::ALLOWED_ATTRIBUTES, true)) {
                throw new RuntimeException(
                    sprintf(
                        'Invalid mapping attribute %s on class %s. Only Mt* attributes are allowed.',
                        $attribute->getName(),
                        $entityClass
                    )
                );
            }
        }

        // Validate property attributes
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                if (!in_array($attribute->getName(), self::ALLOWED_ATTRIBUTES, true)) {
                    throw new RuntimeException(
                        sprintf(
                            'Invalid mapping attribute %s on property %s::%s',
                            $attribute->getName(),
                            $entityClass,
                            $property->getName()
                        )
                    );
                }
            }
        }
    }
}
