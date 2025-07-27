<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

/**
 * Interface for parsing database connection parameters from various sources
 */
interface DatabaseParameterParserInterface
{
    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function parseParameters(array $parameters = []): array;
}
