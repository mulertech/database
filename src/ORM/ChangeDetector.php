<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

use MulerTech\Database\Mapping\MetadataRegistry;
use MulerTech\Database\ORM\Comparator\ValueComparator;
use MulerTech\Database\ORM\Processor\ValueProcessor;
use MulerTech\Database\ORM\Validator\ArrayValidator;
use ReflectionClass;

/**
 * Detects changes in entities for ORM tracking
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class ChangeDetector
{
    private ValueProcessor $valueProcessor;
    private ValueComparator $valueComparator;
    private ArrayValidator $arrayValidator;
    private MetadataRegistry $metadataRegistry;

    public function __construct(?MetadataRegistry $metadataRegistry = null)
    {
        $this->metadataRegistry = $metadataRegistry ?? new MetadataRegistry();
    }

    /**
     * @return ValueProcessor
     */
    private function getValueProcessor(): ValueProcessor
    {
        if (!isset($this->valueProcessor)) {
            $this->valueProcessor = new ValueProcessor();
        }
        return $this->valueProcessor;
    }

    /**
     * @return ValueComparator
     */
    private function getValueComparator(): ValueComparator
    {
        if (!isset($this->valueComparator)) {
            $this->valueComparator = new ValueComparator();
        }
        return $this->valueComparator;
    }

    /**
     * @return ArrayValidator
     */
    private function getArrayValidator(): ArrayValidator
    {
        if (!isset($this->arrayValidator)) {
            $this->arrayValidator = new ArrayValidator();
        }
        return $this->arrayValidator;
    }

    /**
     * @param object $entity
     * @return array<string, mixed>
     */
    public function extractCurrentData(object $entity): array
    {
        $metadata = $this->metadataRegistry->getEntityMetadata($entity::class);
        $data = [];

        foreach ($metadata->getProperties() as $property) {
            $propertyName = $property->getName();
            $getter = $metadata->getGetter($propertyName);

            if ($getter !== null) {
                try {
                    $value = $entity->$getter();
                    $data[$propertyName] = $this->getValueProcessor()->processValue($value);
                } catch (\Throwable) {
                    // If getter throws an exception, set to null
                    $data[$propertyName] = null;
                }
            } else {
                // No getter available, set to null
                $data[$propertyName] = null;
            }
        }

        return $data;
    }

    /**
     * @param object $entity
     * @param array<string, mixed> $originalData
     * @return ChangeSet
     */
    public function computeChangeSet(object $entity, array $originalData): ChangeSet
    {
        $currentData = $this->extractCurrentData($entity);
        $changes = [];

        // Check for changes in current data vs original
        foreach ($currentData as $property => $currentValue) {
            $originalValue = $originalData[$property] ?? null;

            if (!$this->valuesAreEqual($originalValue, $currentValue)) {
                $changes[$property] = new PropertyChange(
                    property: $property,
                    oldValue: $originalValue,
                    newValue: $currentValue
                );
            }
        }

        // Check for properties that were removed (exist in original but not in current)
        foreach ($originalData as $property => $originalValue) {
            if (!array_key_exists($property, $currentData)) {
                $changes[$property] = new PropertyChange(
                    property: $property,
                    oldValue: $originalValue,
                    newValue: null
                );
            }
        }

        return new ChangeSet($entity::class, $changes);
    }

    /**
     * @param mixed $value1
     * @param mixed $value2
     * @return bool
     */
    private function valuesAreEqual(mixed $value1, mixed $value2): bool
    {
        // Handle null comparisons
        if ($value1 === null && $value2 === null) {
            return true;
        }
        if ($value1 === null || $value2 === null) {
            return false;
        }

        $type1 = $this->getValueProcessor()->getValueType($value1);
        $type2 = $this->getValueProcessor()->getValueType($value2);

        if ($type1 !== $type2) {
            return false;
        }

        return match ($type1) {
            'scalar', 'array' => $value1 === $value2,
            'entity' => $this->getValueComparator()->compareEntityReferences(
                $this->getArrayValidator()->validateEntityArray($value1),
                $this->getArrayValidator()->validateEntityArray($value2)
            ),
            'object' => $this->getValueComparator()->compareObjectReferences(
                $this->getArrayValidator()->validateObjectArray($value1),
                $this->getArrayValidator()->validateObjectArray($value2)
            ),
            'collection' => $this->getValueComparator()->compareCollections(
                $this->getArrayValidator()->validateCollectionArray($value1),
                $this->getArrayValidator()->validateCollectionArray($value2)
            ),
            default => $value1 == $value2,
        };
    }
}
