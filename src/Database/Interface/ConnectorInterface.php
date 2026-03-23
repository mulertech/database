<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

/**
 * Interface for database connectors.
 *
 * @author Sébastien Muler
 */
interface ConnectorInterface
{
    /**
     * @param array<string, mixed>          $parameters
     * @param array<int|string, mixed>|null $options
     */
    public function connect(
        array $parameters,
        string $username,
        string $password,
        ?array $options = null,
    ): \PDO;
}
