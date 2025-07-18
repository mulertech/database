<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Parameters;

use MulerTech\Database\PhpInterface\Statement;
use PDO;

/**
 * Class QueryParameterBag
 *
 * Centralized parameter management for database queries
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class QueryParameterBag
{
    /**
     * @var array<string, array{value: mixed, type: int}>
     */
    private array $namedParameters = [];

    /**
     * @var int
     */
    private int $parameterCounter = 0;

    /**
     * @param mixed $value
     * @param int|null $type
     * @return string|int
     */
    public function add(mixed $value, ?int $type = null): string|int
    {
        $type ??= $this->detectType($value);

        $placeholder = $this->generateNamedPlaceholder();
        $this->namedParameters[$placeholder] = ['value' => $value, 'type' => $type];
        return $placeholder;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param int|null $type
     * @return string
     */
    public function addNamed(string $name, mixed $value, ?int $type = null): string
    {
        $type ??= $this->detectType($value);
        $placeholder = ':' . ltrim($name, ':');
        $this->namedParameters[$placeholder] = ['value' => $value, 'type' => $type];
        return $placeholder;
    }

    /**
     * @param Statement $statement
     * @return void
     */
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

    /**
     * @param QueryParameterBag $other
     * @return self
     */
    public function merge(QueryParameterBag $other): self
    {
        $merged = clone $this;

        foreach ($other->namedParameters as $placeholder => $param) {
            $merged->namedParameters[$placeholder] = $param;
        }

        return $merged;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->namedParameters = [];
        $this->parameterCounter = 0;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->namedParameters);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return string
     */
    private function generateNamedPlaceholder(): string
    {
        return ':param' . $this->parameterCounter++;
    }

    /**
     * @param mixed $value
     * @return int
     */
    private function detectType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            is_resource($value) => PDO::PARAM_LOB,
            default => PDO::PARAM_STR
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
