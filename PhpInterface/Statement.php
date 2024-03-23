<?php

namespace MulerTech\Database\PhpInterface;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Statement
{

    private $statement;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @param array|null $params
     * @return bool
     */
    public function execute(array $params = null): bool
    {
        try {
            if (false === $result = $this->statement->execute($params)) {
                throw new RuntimeException('Class : Statement, function : execute. The execute action was failed.');
            }
            return $result;
        } catch (PDOException $exception) {
            throw new PDOException($exception);
        }
    }

    /**
     * @param int $mode
     * @param int $cursorOrientation
     * @param int $cursorOffset
     * @return mixed
     */
    public function fetch(int $mode = PDO::FETCH_BOTH, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0)
    {
        return $this->statement->fetch(...func_get_args());
    }

    /**
     * @param mixed $param
     * @param mixed $var
     * @param int $type
     * @param int|null $maxLength
     * @param mixed|null $driverOptions
     * @return bool
     */
    public function bindParam($param, &$var, int $type = PDO::PARAM_STR, ?int $maxLength = null, $driverOptions = null): bool
    {
        if (false === $result = $this->statement->bindParam(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : bindParam. The bindParam action was failed');
        }
        return $result;
    }

    /**
     * @param mixed $column
     * @param mixed $var
     * @param int $type
     * @param int|null $maxLength
     * @param mixed|null $driverOptions
     * @return bool
     */
    public function bindColumn($column, &$var, int $type = 2, ?int $maxLength = null, $driverOptions = null): bool
    {
        if (false === $result = $this->statement->bindColumn(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : bindColumn. The bindColumn action was failed');
        }
        return $result;
    }

    /**
     * @param mixed $param
     * @param mixed $value
     * @param int $type
     * @return bool
     */
    public function bindValue($param, $value, int $type = PDO::PARAM_STR): bool
    {
        if (false === $result = $this->statement->bindValue(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : bindValue. The bindValue action was failed');
        }
        return $result;
    }

    /**
     * @return int
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * @param int $column
     * @return mixed|false False if no more rows.
     */
    public function fetchColumn(int $column = 0)
    {
        return $this->statement->fetchColumn($column);
    }

    /**
     * @param int $mode
     * @param mixed ...$args
     * @return array
     */
    public function fetchAll(int $mode = PDO::FETCH_BOTH, ...$args): array
    {
        if (false === $result = $this->statement->fetchAll(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : fetchAll. The fetchAll action was failed');
        }
        return $result;
    }

    /**
     * @param string $class
     * @param array $constructorArgs
     * @return mixed
     */
    public function fetchObject(string $class = "stdClass", array $constructorArgs = [])
    {
        if (false === $result = $this->statement->fetchObject(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : fetchObject. The fetchObject action was failed');
        }
        return $result;
    }

    /**
     * @return string
     */
    public function errorCode(): string
    {
        return $this->statement->errorCode();
    }

    /**
     * @return array
     */
    public function errorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    /**
     * @param int $attribute
     * @param mixed $value
     * @return bool
     */
    public function setAttribute(int $attribute, $value): bool
    {
        if (false === $result = $this->statement->setAttribute(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : setAttribute. The setAttribute action was failed');
        }
        return $result;
    }

    /**
     * @param int $name
     * @return mixed
     */
    public function getAttribute(int $name)
    {
        return $this->statement->getAttribute($name);
    }

    /**
     * @return int
     */
    public function columnCount(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * @param int $column
     * @return array|false
     */
    public function getColumnMeta(int $column)
    {
        return $this->statement->getColumnMeta($column);
    }

    /**
     * @param int $mode
     * @param null|string|object $className
     * @param array $params
     * @return bool
     */
    public function setFetchMode(int $mode, $className = null, array $params = []): bool
    {
        if (false === $result = $this->statement->setFetchMode(...func_get_args())) {
            throw new RuntimeException('Class : Statement, function : setFetchMode. The setFetchMode action was failed');
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function nextRowset(): bool
    {
        return $this->statement->nextRowset();
    }

    /**
     * @return bool
     */
    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    /**
     * @return bool
     */
    public function debugDumpParams(): bool
    {
        return $this->statement->debugDumpParams();
    }
}