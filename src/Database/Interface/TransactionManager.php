<?php

declare(strict_types=1);

namespace MulerTech\Database\Database\Interface;

use PDO;
use PDOException;

/**
 * Manages database transaction operations with nested transaction support
 */
class TransactionManager
{
    private int $transactionLevel = 0;

    public function beginTransaction(PDO $connection): bool
    {
        if ($this->transactionLevel === 0) {
            try {
                $this->transactionLevel = 1;
                return $connection->beginTransaction();
            } catch (PDOException $exception) {
                throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
            }
        }

        // Nested transaction - just increment level
        ++$this->transactionLevel;
        return true;
    }

    public function commit(PDO $connection): bool
    {
        if ($this->transactionLevel === 1) {
            try {
                $result = $connection->commit();
                $this->transactionLevel = 0;
                return $result;
            } catch (PDOException $exception) {
                throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
            }
        }

        // Nested transaction - just decrement level
        if ($this->transactionLevel > 0) {
            --$this->transactionLevel;
        }

        return true;
    }

    public function rollBack(PDO $connection): bool
    {
        try {
            $result = $connection->rollBack();
            $this->transactionLevel = 0;
            return $result;
        } catch (PDOException $exception) {
            throw new PDOException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }
}
