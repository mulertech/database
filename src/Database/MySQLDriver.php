<?php

declare(strict_types=1);

namespace MulerTech\Database\Database;

use MulerTech\Database\Database\Interface\DriverInterface;

/**
 * Class Driver
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MySQLDriver implements DriverInterface
{
    /**
     * Generates a DSN string for MySQL PDO connection.
     *
     * @param array{
     *     host?: string,
     *     port?: int|string,
     *     dbname?: string,
     *     unix_socket?: string,
     *     charset?: string
     * } $dsnOptions
     * @return string
     */
    public function generateDsn(array $dsnOptions): string
    {
        $dsn = 'mysql:';

        if (isset($dsnOptions['unix_socket'])) {
            $dsn .= 'unix_socket=' . $dsnOptions['unix_socket'];
        }

        if (!isset($dsnOptions['unix_socket'])) {
            $dsn .= 'host=' . ($dsnOptions['host'] ?? 'localhost');
            $dsn .= ';port=' . ($dsnOptions['port'] ?? 3306);
        }

        if (isset($dsnOptions['dbname'])) {
            $dsn .= ';dbname=' . $dsnOptions['dbname'];
        }

        $charset = $dsnOptions['charset'] ?? 'utf8mb4';
        $dsn .= ';charset=' . $charset;

        return $dsn;
    }

}
