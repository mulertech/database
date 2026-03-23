<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM\Processor;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\ChangeDetector;
use MulerTech\Database\ORM\EntityState;
use MulerTech\Database\ORM\IdentityMap;

/**
 * @author Sébastien Muler
 */
readonly class EntityProcessor
{
    public function __construct(
        private ChangeDetector $changeDetector,
        private IdentityMap $identityMap,
        private MetadataRegistry $metadataRegistry,
    ) {
    }

    /**
     * @throws \ReflectionException
     */
    public function extractEntityId(object $entity): int|string|null
    {
        $metadata = $this->metadataRegistry->getEntityMetadata($entity::class);

        foreach (['id', 'identifier', 'uuid'] as $property) {
            $getter = $metadata->getGetter($property);
            if (null !== $getter) {
                $value = $entity->$getter();
                if (is_int($value) || is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Copy data from source entity to target entity.
     *
     * @param object $source Source entity from which to copy data
     * @param object $target Target entity to which data will be copied
     *
     * @throws \ReflectionException
     */
    public function copyEntityData(object $source, object $target): void
    {
        if ($source::class !== $target::class) {
            throw new \InvalidArgumentException('Cannot copy data between different entity types');
        }

        $this->copyProperties($source, $target);
        $this->updateTargetMetadata($target);
    }

    /**
     * @throws \ReflectionException
     */
    private function copyProperties(object $source, object $target): void
    {
        $metadata = $this->metadataRegistry->getEntityMetadata($source::class);

        // Only process properties that have both getter and setter
        $propertiesWithAccessors = $metadata->getPropertiesWithGettersAndSetters();

        foreach ($propertiesWithAccessors as $propertyName) {
            if ($this->shouldSkipProperty($propertyName)) {
                continue;
            }

            try {
                $getter = $metadata->getRequiredGetter($propertyName);
                $setter = $metadata->getRequiredSetter($propertyName);

                $value = $source->$getter();
                $target->$setter($value);
            } catch (\Error) {
                // Handle readonly properties or other restrictions
                continue;
            }
        }
    }

    private function shouldSkipProperty(string $propertyName): bool
    {
        return 'id' === $propertyName;
    }

    private function updateTargetMetadata(object $target): void
    {
        $metadata = $this->identityMap->getMetadata($target);
        if (null === $metadata) {
            return;
        }

        $newData = $this->changeDetector->extractCurrentData($target);
        $newMetadata = new EntityState(
            $metadata->className,
            $metadata->state,
            $newData,
            new \DateTimeImmutable()
        );

        $this->identityMap->updateMetadata($target, $newMetadata);
    }
}
