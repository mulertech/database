<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface;

use PDO;

/**
 * Interface for database connectors
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface ConnectorInterface
{
    /**
     * @param array<string, mixed> $parameters
     * @param string $username
     * @param string $password
     * @param array<int|string, mixed>|null $options
     * @return PDO
     */
    public function connect(
        array $parameters,
        string $username,
        string $password,
        ?array $options = null
    ): PDO;
}
