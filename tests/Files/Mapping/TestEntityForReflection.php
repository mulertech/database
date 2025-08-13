<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Mapping;

use DateTime;

class TestEntityForReflection
{
    public int $id;
    public string $name;
    public bool $active;
    public DateTime $createdAt;
    public array $metadata;
    public float $score;
    public $untypedProperty;

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function hasPermission(): bool
    {
        return true;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}