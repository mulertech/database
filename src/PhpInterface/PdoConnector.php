<?php

declare(strict_types=1);

namespace MulerTech\Database\PhpInterface;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connector implementation
 * @package MulerTech\Database\PhpInterface
 * @author Sébastien Muler
 */
class PdoConnector implements ConnectorInterface
{
    /**
     * @param DriverInterface $driver
     */
    public function __construct(private readonly DriverInterface $driver)
    {
    }

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
    ): PDO {
        try {
            $dsn = $this->driver->generateDsn($parameters);

            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $options = array_replace($defaultOptions, $options ?? []);

            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                sprintf('Connection failed: %s', $exception->getMessage()),
                (int)$exception->getCode(),
                $exception
            );
        }
    }
}
