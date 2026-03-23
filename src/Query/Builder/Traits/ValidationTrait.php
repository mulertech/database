<?php

declare(strict_types=1);

namespace MulerTech\Database\Query\Builder\Traits;

/**
 * Trait ValidationTrait.
 *
 * Provides common validation methods for query builders
 *
 * @author Sébastien Muler
 */
trait ValidationTrait
{
    /**
     * Common method for validating table names.
     *
     * @throws \RuntimeException
     */
    protected function validateTableName(string $table): void
    {
        if (empty($table)) {
            throw new \RuntimeException('Table name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \RuntimeException('Invalid table name format');
        }
    }

    /**
     * Common method for validating column names.
     *
     * @throws \RuntimeException
     */
    protected function validateColumnName(string $column): void
    {
        if (empty($column)) {
            throw new \RuntimeException('Column name cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \RuntimeException('Invalid column name format');
        }
    }

    /**
     * Validate array of column names.
     *
     * @param array<string> $columns
     *
     * @throws \RuntimeException
     */
    protected function validateColumnNames(array $columns): void
    {
        foreach ($columns as $column) {
            $this->validateColumnName($column);
        }
    }

    /**
     * Validate that a value is not empty.
     *
     * @throws \RuntimeException
     */
    protected function validateNotEmpty(mixed $value, string $fieldName): void
    {
        if (empty($value)) {
            throw new \RuntimeException("$fieldName cannot be empty");
        }
    }
}
