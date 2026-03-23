<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Parameters;

use MulerTech\Database\Database\Interface\Statement;

/**
 * Class QueryParameterBag.
 *
 * Centralized parameter management for database queries
 *
 * @author Sébastien Muler
 */
class QueryParameterBag
{
    /**
     * @var array<string, array{value: mixed, type: int}>
     */
    private array $namedParameters = [];

    private int $parameterCounter = 0;

    public function add(mixed $value, ?int $type = null): string
    {
        $type ??= $this->detectType($value);

        $placeholder = $this->generateNamedPlaceholder();
        $this->namedParameters[$placeholder] = ['value' => $value, 'type' => $type];

        return $placeholder;
    }

    public function addNamed(string $name, mixed $value, ?int $type = null): string
    {
        $type ??= $this->detectType($value);
        $placeholder = ':'.ltrim($name, ':');
        $this->namedParameters[$placeholder] = ['value' => $value, 'type' => $type];

        return $placeholder;
    }

    public function bind(Statement $statement): void
    {
        foreach ($this->namedParameters as $placeholder => $param) {
            $statement->bindValue($placeholder, $param['value'], $param['type']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getNamedValues(): array
    {
        return array_map(static function ($param) {
            return $param['value'];
        }, $this->namedParameters);
    }

    public function merge(QueryParameterBag $other): self
    {
        $merged = clone $this;

        foreach ($other->namedParameters as $placeholder => $param) {
            $merged->namedParameters[$placeholder] = $param;
        }

        return $merged;
    }

    public function clear(): void
    {
        $this->namedParameters = [];
        $this->parameterCounter = 0;
    }

    public function count(): int
    {
        return count($this->namedParameters);
    }

    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    private function generateNamedPlaceholder(): string
    {
        return ':param'.$this->parameterCounter++;
    }

    private function detectType(mixed $value): int
    {
        return match (true) {
            is_int($value) => \PDO::PARAM_INT,
            is_bool($value) => \PDO::PARAM_BOOL,
            is_null($value) => \PDO::PARAM_NULL,
            is_resource($value) => \PDO::PARAM_LOB,
            default => \PDO::PARAM_STR,
        };
    }

    /**
     * @return array<string, array{value: mixed, type: int}>
     */
    public function toArray(): array
    {
        return $this->namedParameters;
    }
}
