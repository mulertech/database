<?php

namespace MulerTech\Database\ORM\Engine\EntityState;

use MulerTech\Database\Mapping\DbMappingInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Tracks changes on entities and compares with the original state
 *
 * @package MulerTech\Database\ORM\Engine\EntityState
 * @author Sébastien Muler
 */
class EntityChangeTracker
{
    /**
     * @var array<int, array<string, array<int, mixed>>> Format: [$objectId => [$property => [$oldValue, $newValue]]]
     */
    private array $entityChanges = [];

    /**
     * @var array<int, array<string, mixed>> Format: [$objectId => [$column => $value]]
     */
    private array $originalEntityData = [];

    /**
     * @param DbMappingInterface $dbMapping
     */
    public function __construct(
        private readonly DbMappingInterface $dbMapping
    ) {
    }

    /**
     * @param object $entity
     * @param array<string, mixed>|null $originalData
     * @return void
     */
    public function trackOriginalData(object $entity, ?array $originalData): void
    {
        $objectId = spl_object_id($entity);

        // Only track if we have actual data to track
        if ($originalData !== null) {
            $this->originalEntityData[$objectId] = $originalData;
        } elseif (!isset($this->originalEntityData[$objectId])) {
            // Initialize with empty array if no data exists yet
            $this->originalEntityData[$objectId] = [];
        }
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function computeChanges(object $entity): void
    {
        $objectId = spl_object_id($entity);
        $originalData = $this->originalEntityData[$objectId] ?? null;

        $properties = $this->getPropertiesColumns($entity::class);
        $entityReflection = new ReflectionClass($entity);

        $changes = [];

        foreach ($properties as $property => $column) {
            $newValue = $entityReflection->getProperty($property)->getValue($entity);
            $oldValue = (is_array($originalData) && isset($originalData[$column]))
                ? $originalData[$column]
                : null;

            // Convert object values to their ID for comparison
            if (is_object($newValue) && method_exists($newValue, 'getId')) {
                $newValueId = $newValue->getId();

                if ($newValueId === null) {
                    // Only track original data if we have valid data to track
                    if ($originalData !== null) {
                        $this->trackOriginalData($entity, $originalData);
                    }
                    $this->computeChanges($newValue);
                }
            }

            if ($oldValue === $newValue && $originalData !== null) {
                continue;
            }

            $changes[$property] = [$oldValue, $newValue];
        }

        if (!empty($changes)) {
            $this->entityChanges[$objectId] = $changes;
        } else {
            // Clear changes if no changes detected
            unset($this->entityChanges[$objectId]);
        }
    }

    /**
     * @param object $entity
     * @return array<string, array<int, mixed>>
     */
    public function getChanges(object $entity): array
    {
        return $this->entityChanges[spl_object_id($entity)] ?? [];
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasChanges(object $entity): bool
    {
        return !empty($this->getChanges($entity));
    }

    /**
     * @param object $entity
     * @param string $property
     * @return bool
     */
    public function hasPropertyChanged(object $entity, string $property): bool
    {
        $changes = $this->getChanges($entity);
        return isset($changes[$property]);
    }

    /**
     * @param object $entity
     * @param string $property
     * @return array{mixed, mixed}|null
     */
    public function getPropertyChange(object $entity, string $property): ?array
    {
        $changes = $this->getChanges($entity);
        $change = $changes[$property] ?? null;

        if ($change === null) {
            return null;
        }

        // Ensure we return exactly two elements
        return [$change[0] ?? null, $change[1] ?? null];
    }

    /**
     * @param object $entity
     * @return array<string, mixed>|null
     */
    public function getOriginalData(object $entity): ?array
    {
        return $this->originalEntityData[spl_object_id($entity)] ?? null;
    }

    /**
     * @param object $entity
     * @param string $column
     * @return mixed
     */
    public function getOriginalValue(object $entity, string $column): mixed
    {
        $originalData = $this->getOriginalData($entity);
        return $originalData[$column] ?? null;
    }

    /**
     * @param object $entity
     * @return void
     */
    public function clearChanges(object $entity): void
    {
        $objectId = spl_object_id($entity);
        unset($this->entityChanges[$objectId]);
    }

    /**
     * @param object $entity
     * @return void
     */
    public function detach(object $entity): void
    {
        $objectId = spl_object_id($entity);
        unset(
            $this->entityChanges[$objectId],
            $this->originalEntityData[$objectId]
        );
    }

    /**
     * @param object $entity
     * @return void
     * @throws ReflectionException
     */
    public function refreshOriginalData(object $entity): void
    {
        $objectId = spl_object_id($entity);
        $properties = $this->getPropertiesColumns($entity::class);
        $entityReflection = new ReflectionClass($entity);

        $currentData = [];
        foreach ($properties as $property => $column) {
            $value = $entityReflection->getProperty($property)->getValue($entity);
            // Convert object values to their ID for storage
            if (is_object($value) && method_exists($value, 'getId')) {
                $value = $value->getId();
            }
            $currentData[$column] = $value;
        }

        $this->originalEntityData[$objectId] = $currentData;
        $this->clearChanges($entity);
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function isNew(object $entity): bool
    {
        return !isset($this->originalEntityData[spl_object_id($entity)]);
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->entityChanges = [];
        $this->originalEntityData = [];
    }

    /**
     * @param class-string $entityName
     * @return array<string, string>
     * @throws ReflectionException
     */
    private function getPropertiesColumns(string $entityName): array
    {
        return $this->dbMapping->getPropertiesColumns($entityName);
    }

    /**
     * @param object $entity
     * @return array<string>
     */
    public function getChangedProperties(object $entity): array
    {
        return array_keys($this->getChanges($entity));
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function hasNonIdChanges(object $entity): bool
    {
        $changes = $this->getChanges($entity);
        unset($changes['id']); // Exclude ID from significant changes
        return !empty($changes);
    }
}
