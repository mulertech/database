<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

/**
 * Interface DriverInterface.
 *
 * Interface for database drivers to generate DSN strings.
 *
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
     */
    public function generateDsn(array $dsnOptions): string;
}
