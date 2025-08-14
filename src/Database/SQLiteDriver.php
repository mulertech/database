<?php

declare(strict_types=1);

namespace MulerTech\Database\Database;

use MulerTech\Database\Database\Interface\DriverInterface;

/**
 * Class SQLiteDriver
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class SQLiteDriver implements DriverInterface
{
    /**
     * Generates a DSN string for SQLite PDO connection.
     *
     * @param array{
     *     path?: string,
     *     mode?: string
     * } $dsnOptions
     * @return string
     */
    public function generateDsn(array $dsnOptions): string
    {
        $path = $dsnOptions['path'] ?? ':memory:';

        // Handle special cases for SQLite paths
        if ($path === ':memory:') {
            return 'sqlite::memory:';
        }

        return 'sqlite:' . $path;
    }
}
