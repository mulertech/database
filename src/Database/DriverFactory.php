<?php

declare(strict_types=1);

namespace MulerTech\Database\Database;

use MulerTech\Database\Database\Interface\DriverInterface;
use InvalidArgumentException;

/**
 * Class DriverFactory
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DriverFactory
{
    /**
     * Create a driver instance based on the scheme
     *
     * @param string $scheme
     * @return DriverInterface
     * @throws InvalidArgumentException
     */
    public static function create(string $scheme): DriverInterface
    {
        return match (strtolower($scheme)) {
            'mysql' => new MySQLDriver(),
            'sqlite' => new SQLiteDriver(),
            default => throw new InvalidArgumentException("Unsupported database scheme: $scheme")
        };
    }
}
