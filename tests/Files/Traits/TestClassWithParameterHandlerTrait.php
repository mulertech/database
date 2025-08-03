<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Traits;

use MulerTech\Database\Core\Traits\ParameterHandlerTrait;
use MulerTech\Database\Database\Interface\Statement;

/**
 * Test class that uses the ParameterHandlerTrait for testing purposes
 */
class TestClassWithParameterHandlerTrait
{
    use ParameterHandlerTrait;

    // Expose protected properties for testing
    public function setNamedParameters(array $params): void
    {
        $this->namedParameters = $params;
    }

    public function setDynamicParameters(array $params): void
    {
        $this->dynamicParameters = $params;
    }

    public function setParameterCounter(int $counter): void
    {
        $this->parameterCounter = $counter;
    }

    public function getParameterCounter(): int
    {
        return $this->parameterCounter;
    }

    // Expose protected methods for testing
    public function callBindParameters(Statement $statement): void
    {
        $this->bindParameters($statement);
    }

    public function callResetParameters(): void
    {
        $this->resetParameters();
    }

    public function callDetectParameterType(mixed $value): int
    {
        return $this->detectParameterType($value);
    }

    public function callMergeNamedParameters(array $params): self
    {
        return $this->mergeNamedParameters($params);
    }

    public function callMergeDynamicParameters(array $params): self
    {
        return $this->mergeDynamicParameters($params);
    }
}