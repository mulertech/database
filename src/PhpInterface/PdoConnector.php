<?php

namespace MulerTech\Database\PhpInterface;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Class PdoConnector
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class PdoConnector implements ConnectorInterface
{
    /**
     * PdoConnector constructor.
     *
     * @param DriverInterface $driver
     */
    public function __construct(protected DriverInterface $driver)
    {
    }

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
     * @return PDO
     */
    public function connect(array $dsnOptions, string $username, string $password, ?array $options = null): PDO
    {
        try {
            return new PDO($this->driver->generateDsn($dsnOptions), $username, $password, $options);
        } catch (PDOException $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }


}
