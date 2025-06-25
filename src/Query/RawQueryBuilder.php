<?php

declare(strict_types=1);

namespace MulerTech\Database\Query;

use MulerTech\Database\ORM\EmEngine;

/**
 * Raw SQL query builder for complex queries
 *
 * @package MulerTech\Database\Query
 * @author Sébastien Muler
 */
class RawQueryBuilder extends AbstractQueryBuilder
{
    /**
     * @var string
     */
    private string $sql;

    /**
     * @param EmEngine|null $emEngine
     * @param string $sql
     */
    public function __construct(?EmEngine $emEngine, string $sql)
    {
        parent::__construct($emEngine);
        $this->sql = $sql;
    }

    /**
     * @return string
     */
    public function toSql(): string
    {
        return $this->sql;
    }

    /**
     * @return string
     */
    public function getQueryType(): string
    {
        // Extract query type from SQL
        $sql = strtoupper(trim($this->sql));

        if (str_starts_with($sql, 'SELECT')) {
            return 'SELECT';
        }
        if (str_starts_with($sql, 'INSERT')) {
            return 'INSERT';
        }
        if (str_starts_with($sql, 'UPDATE')) {
            return 'UPDATE';
        }
        if (str_starts_with($sql, 'DELETE')) {
            return 'DELETE';
        }
        if (str_starts_with($sql, 'REPLACE')) {
            return 'REPLACE';
        }

        return 'RAW';
    }

    /**
     * @param string $sql
     * @return self
     */
    public function setSql(string $sql): self
    {
        $this->sql = $sql;
        return $this;
    }

    /**
     * @param string $sql
     * @return self
     */
    public function appendSql(string $sql): self
    {
        $this->sql .= ' ' . $sql;
        return $this;
    }

    /**
     * @param array<string, mixed> $bindings
     * @return self
     */
    public function bind(array $bindings): self
    {
        foreach ($bindings as $value) {
            $this->parameterBag->add($value);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isSelect(): bool
    {
        return $this->getQueryType() === 'SELECT';
    }

    /**
     * @return bool
     */
    public function isInsert(): bool
    {
        return $this->getQueryType() === 'INSERT';
    }

    /**
     * @return bool
     */
    public function isUpdate(): bool
    {
        return $this->getQueryType() === 'UPDATE';
    }

    /**
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->getQueryType() === 'DELETE';
    }
}
