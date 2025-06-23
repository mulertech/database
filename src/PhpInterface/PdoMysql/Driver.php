<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface\PdoMysql;

use MulerTech\Database\PhpInterface\DriverInterface;

/**
 * Class Driver
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class Driver implements DriverInterface
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
        } else {
            $dsn .= 'host=' . ($dsnOptions['host'] ?? 'localhost');
            $dsn .= ';port=' . ($dsnOptions['port'] ?? 3306);
        }

        if (isset($dsnOptions['dbname'])) {
            $dsn .= ';dbname=' . $dsnOptions['dbname'];
        }

        if (isset($dsnOptions['charset'])) {
            $dsn .= ';charset=' . $dsnOptions['charset'];
        } else {
            $dsn .= ';charset=utf8mb4';
        }

        return $dsn;
    }

}
