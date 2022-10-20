<?php

namespace mtphp\Database\PhpInterface;

use mtphp\Database\PhpInterface\ConnectorInterface;
use mtphp\Database\PhpInterface\DriverInterface;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Class PdoConnector
 * @package mtphp\Database\PhpInterface
 * @author Sébastien Muler
 */
class PdoConnector implements ConnectorInterface
{

    /**
     * @var DriverInterface
     */
    protected $driver;

    /**
     * PdoConnector constructor.
     * @param DriverInterface $driver
     */
    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param array $dsnOptions
     * @param string $username
     * @param string $password
     * @param array|null $options
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
