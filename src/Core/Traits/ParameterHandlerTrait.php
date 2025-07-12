<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Traits;

use MulerTech\Database\PhpInterface\Statement;
use MulerTech\Database\Query\AbstractQueryBuilder;
use PDO;

/**
 * Trait ParameterHandlerTrait
 *
 * Provides parameter handling functionality for query builders
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
trait ParameterHandlerTrait
{
    /**
     * @var array<string, mixed>
     */
    protected array $namedParameters = [];

    /**
     * @var array<int, mixed>
     */
    protected array $dynamicParameters = [];

    /**
     * @var int
     */
    protected int $parameterCounter = 0;

    /**
     * @param Statement $statement
     * @return void
     */
    protected function bindParameters(Statement $statement): void
    {
        // Bind named parameters
        foreach ($this->namedParameters as $key => $param) {
            $statement->bindValue($key, $param['value'], $param['type']);
        }

        // Bind dynamic parameters
        foreach ($this->dynamicParameters as $index => $value) {
            $statement->bindValue($index + 1, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getNamedParameters(): array
    {
        return $this->namedParameters;
    }

    /**
     * @return array<int, mixed>
     */
    public function getDynamicParameters(): array
    {
        return $this->dynamicParameters;
    }

    /**
     * @return void
     */
    protected function resetParameters(): void
    {
        $this->namedParameters = [];
        $this->dynamicParameters = [];
        $this->parameterCounter = 0;
    }

    /**
     * @param mixed $value
     * @return int
     */
    protected function detectParameterType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return AbstractQueryBuilder|ParameterHandlerTrait
     */
    protected function mergeNamedParameters(array $params): self
    {
        $this->namedParameters = array_merge($this->namedParameters, $params);
        return $this;
    }

    /**
     * @param array<int, mixed> $params
     * @return AbstractQueryBuilder|ParameterHandlerTrait
     */
    protected function mergeDynamicParameters(array $params): self
    {
        $this->dynamicParameters = array_merge($this->dynamicParameters, $params);
        return $this;
    }
}
