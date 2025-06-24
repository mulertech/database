<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Parameters;

use MulerTech\Database\PhpInterface\Statement;
use PDO;
use PDOStatement;

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
     * @var array<int, array{value: mixed, type: int}>
     */
    private array $positionalParameters = [];

    /**
     * @var int
     */
    private int $parameterCounter = 0;

    /**
     * @var bool
     */
    private bool $useNamedParameters = true;

    /**
     * @param bool $useNamedParameters
     */
    public function __construct(bool $useNamedParameters = true)
    {
        $this->useNamedParameters = $useNamedParameters;
    }

    /**
     * @param mixed $value
     * @param int|null $type
     * @return string|int
     */
    public function add(mixed $value, ?int $type = null): string|int
    {
        $type ??= $this->detectType($value);

        if ($this->useNamedParameters) {
            $placeholder = $this->generateNamedPlaceholder();
            $this->namedParameters[$placeholder] = ['value' => $value, 'type' => $type];
            return $placeholder;
        }

        $this->positionalParameters[] = ['value' => $value, 'type' => $type];
        return count($this->positionalParameters);
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
     * @param mixed $value
     * @param int|null $type
     * @return int
     */
    public function addPositional(mixed $value, ?int $type = null): int
    {
        $type ??= $this->detectType($value);
        $this->positionalParameters[] = ['value' => $value, 'type' => $type];
        return count($this->positionalParameters);
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

        foreach ($this->positionalParameters as $index => $param) {
            $statement->bindValue($index + 1, $param['value'], $param['type']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getNamedValues(): array
    {
        $values = [];
        foreach ($this->namedParameters as $placeholder => $param) {
            $values[$placeholder] = $param['value'];
        }
        return $values;
    }

    /**
     * @return array<int, mixed>
     */
    public function getPositionalValues(): array
    {
        return array_column($this->positionalParameters, 'value');
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

        foreach ($other->positionalParameters as $param) {
            $merged->positionalParameters[] = $param;
        }

        return $merged;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        $this->namedParameters = [];
        $this->positionalParameters = [];
        $this->parameterCounter = 0;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->namedParameters) + count($this->positionalParameters);
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
            $value instanceof \DateTimeInterface => PDO::PARAM_STR,
            is_resource($value) => PDO::PARAM_LOB,
            default => PDO::PARAM_STR
        };
    }

    /**
     * @return array{named: array<string, array{value: mixed, type: int}>, positional: array<int, array{value: mixed, type: int}>}
     */
    public function toArray(): array
    {
        return [
            'named' => $this->namedParameters,
            'positional' => $this->positionalParameters
        ];
    }
}