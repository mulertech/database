<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Helper class for executing database queries with different fetch modes
 */
class QueryExecutorHelper
{
    /**
     * @param array<int, mixed>|null $constructorArgs
     */
    public static function executeQuery(
        PDO $pdo,
        string $query,
        ?int $fetchMode = null,
        int|string|object $arg3 = '',
        ?array $constructorArgs = null
    ): Statement {
        $result = match ($fetchMode) {
            null => $pdo->query($query),
            PDO::FETCH_CLASS => $pdo->query(
                $query,
                $fetchMode,
                is_string($arg3) ? $arg3 : '',
                is_array($constructorArgs) ? $constructorArgs : []
            ),
            PDO::FETCH_INTO => self::executeQueryWithFetchInto($pdo, $query, $fetchMode, $arg3),
            default => $pdo->query($query, $fetchMode)
        };

        if ($result === false) {
            throw new RuntimeException(
                sprintf(
                    'Query failed. Error: %s. Statement: %s',
                    $pdo->errorInfo()[2] ?? 'Unknown error',
                    $query
                )
            );
        }

        return new Statement($result);
    }

    private static function executeQueryWithFetchInto(PDO $pdo, string $query, int $fetchMode, int|string|object $arg3): \PDOStatement|false
    {
        if (!is_object($arg3)) {
            throw new InvalidArgumentException(
                'When using FETCH_INTO, the third argument must be an object.'
            );
        }

        return $pdo->query($query, $fetchMode, $arg3);
    }
}
