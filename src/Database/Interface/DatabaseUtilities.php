<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;

/**
 * Utility class for database operations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DatabaseUtilities
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function errorCode(): string|int|false
    {
        $errorCode = $this->connection->errorCode();
        return $errorCode ?? false;
    }

    /**
     * @return array<int, string|null>
     */
    public function errorInfo(): array
    {
        return $this->connection->errorInfo();
    }

    public function quote(string $string, int $type = PDO::PARAM_STR): string
    {
        return $this->connection->quote($string, $type);
    }

    public function getAttribute(int $attribute): mixed
    {
        return $this->connection->getAttribute($attribute);
    }
}
