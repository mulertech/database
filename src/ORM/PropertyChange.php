<?php

declare(strict_types=1);

namespace MulerTech\Database\ORM;

/**
 * Représente le changement d'une propriété d'entité
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

    /**
     * @return bool
     */
    public function isAddition(): bool
    {
        return $this->oldValue === null && $this->newValue !== null;
    }

    /**
     * @return bool
     */
    public function isRemoval(): bool
    {
        return $this->oldValue !== null && $this->newValue === null;
    }

    /**
     * @return bool
     */
    public function isModification(): bool
    {
        return $this->oldValue !== null && $this->newValue !== null;
    }

    /**
     * @return bool
     */
    public function hasChanged(): bool
    {
        return $this->oldValue !== $this->newValue;
    }

    /**
     * @return array{property: string, old: mixed, new: mixed, type: string}
     */
    public function toArray(): array
    {
        return [
            'property' => $this->property,
            'old' => $this->oldValue,
            'new' => $this->newValue,
            'type' => $this->getChangeType(),
        ];
    }

    /**
     * @return string
     */
    private function getChangeType(): string
    {
        if ($this->isAddition()) {
            return 'addition';
        }

        if ($this->isRemoval()) {
            return 'removal';
        }

        if ($this->isModification()) {
            return 'modification';
        }

        return 'none';
    }
}
