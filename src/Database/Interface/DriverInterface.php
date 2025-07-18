<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

/**
 * Interface DriverInterface
 *
 * Interface for database drivers to generate DSN strings.
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface DriverInterface
{
    /**
     * @param array{
     *     host?: string,
     *     port?: int|string,
     *     dbname?: string,
     *     unix_socket?: string,
     *     charset?: string
     * } $dsnOptions
     * @return string
     */
    public function generateDsn(array $dsnOptions): string;
}
