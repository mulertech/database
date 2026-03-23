<?php

declare(strict_types=1);

namespace MulerTech\Database\Database;

use MulerTech\Database\Database\Interface\DriverInterface;

/**
 * Class DriverFactory.
 *
 * @author Sébastien Muler
 */
class DriverFactory
{
    /**
     * Create a driver instance based on the scheme.
     *
     * @throws \InvalidArgumentException
     */
    public static function create(string $scheme): DriverInterface
    {
        return match (strtolower($scheme)) {
            'mysql' => new MySQLDriver(),
            'sqlite' => new SQLiteDriver(),
            default => throw new \InvalidArgumentException("Unsupported database scheme: $scheme"),
        };
    }
}
