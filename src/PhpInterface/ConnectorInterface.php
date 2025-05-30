<?php

namespace MulerTech\Database\PhpInterface;

/**
 * Interface ConnectorInterface
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
interface ConnectorInterface
{
    /**
     * @param array{
     *     host?: string,
     *     port?: int|string,
     *     dbname?: string,
     *     unix_socket?: string,
     *     charset?: string
     * } $dsnOptions
     * @param string $username
     * @param string $password
     * @param array<int|string, mixed>|null $options
     * @return mixed
     */
    public function connect(array $dsnOptions, string $username, string $password, ?array $options = null);
}
