<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;

/**
 * Interface for executing database queries with different fetch modes
 */
interface QueryExecutorInterface
{
    /**
     * @param array<int, mixed>|null $constructorArgs
     */
    public function executeQuery(
        PDO $pdo,
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement;
}
