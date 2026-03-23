<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder;

use MulerTech\Database\ORM\EmEngine;

/**
 * Raw SQL query builder for complex queries.
 *
 * @author Sébastien Muler
 */
class RawQueryBuilder extends AbstractQueryBuilder
{
    private string $sql;

    public function __construct(?EmEngine $emEngine, string $sql)
    {
        parent::__construct($emEngine);
        $this->sql = $sql;
    }

    public function buildSql(): string
    {
        return $this->sql;
    }

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

    public function setSql(string $sql): self
    {
        $this->sql = $sql;

        return $this;
    }

    public function appendSql(string $sql): self
    {
        $this->sql .= ' '.$sql;

        return $this;
    }

    /**
     * @param array<string, mixed> $bindings
     */
    public function bind(array $bindings): self
    {
        foreach ($bindings as $value) {
            $this->parameterBag->add($value);
        }

        return $this;
    }

    public function isSelect(): bool
    {
        return 'SELECT' === $this->getQueryType();
    }

    public function isInsert(): bool
    {
        return 'INSERT' === $this->getQueryType();
    }

    public function isUpdate(): bool
    {
        return 'UPDATE' === $this->getQueryType();
    }

    public function isDelete(): bool
    {
        return 'DELETE' === $this->getQueryType();
    }
}
