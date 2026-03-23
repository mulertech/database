<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

/**
 * Class PdoConnector.
 *
 * @author Sébastien Muler
 */
readonly class PdoConnector implements ConnectorInterface
{
    public function __construct(private DriverInterface $driver)
    {
    }

    /**
     * @param array{
     *      host?: string,
     *      port?: int|string,
     *      dbname?: string,
     *      unix_socket?: string,
     *      charset?: string
     *  } $parameters
     * @param array<int|string, mixed>|null $options
     */
    public function connect(
        array $parameters,
        string $username,
        string $password,
        ?array $options = null,
    ): \PDO {
        try {
            $dsn = $this->driver->generateDsn($parameters);

            $defaultOptions = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $options = array_replace($defaultOptions, $options ?? []);

            return new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $exception) {
            throw new \RuntimeException(sprintf('Connection failed: %s', $exception->getMessage()), (int) $exception->getCode(), $exception);
        }
    }
}
