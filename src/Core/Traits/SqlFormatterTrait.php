<?php

declare(strict_types=1);

namespace MulerTech\Database\Core\Traits;

/**
 * Trait SqlFormatterTrait
 *
 * Provides SQL formatting functionality
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
trait SqlFormatterTrait
{
    /**
     * @param string $identifier
     * @return string
     */
    protected function formatIdentifier(string $identifier): string
    {
        // Handle qualified identifiers (table.column)
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            return implode('.', array_map([$this, 'escapeIdentifier'], $parts));
        }

        return $this->escapeIdentifier($identifier);
    }

    /**
     * Format identifier with alias support
     * @param string $identifier
     * @return string
     */
    protected function formatIdentifierWithAlias(string $identifier): string
    {
        // Handle 'column AS alias' format (case-insensitive)
        if (preg_match('/^(.+)\s+(?:AS|as)\s+(.+)$/i', $identifier, $matches)) {
            $column = trim($matches[1]);
            $alias = trim($matches[2]);

            // Check if the column part is a function or expression
            if ($this->isExpression($column)) {
                return $column . ' AS ' . $this->formatIdentifier($alias);
            }

            return $this->formatIdentifier($column) . ' AS ' . $this->formatIdentifier($alias);
        }

        // Handle 'column alias' format (space separated, no AS keyword)
        if (preg_match('/^([a-zA-Z_]\w*)\s+([a-zA-Z_]\w*)$/', $identifier, $matches)) {
            return $this->formatIdentifier($matches[1]) . ' AS ' . $this->formatIdentifier($matches[2]);
        }

        // Check if it's a function or expression without alias
        if ($this->isExpression($identifier)) {
            return $identifier;
        }

        return $this->formatIdentifier($identifier);
    }


    /**
     * @param string $identifier
     * @return string
     */
    protected function escapeIdentifier(string $identifier): string
    {
        // Don't escape if already escaped or is a function/expression
        if ($this->isEscaped($identifier) || $this->isExpression($identifier)) {
            return $identifier;
        }

        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * @param string $identifier
     * @return bool
     */
    protected function isEscaped(string $identifier): bool
    {
        return (str_starts_with($identifier, '`') && str_ends_with($identifier, '`'))
            || (str_starts_with($identifier, '"') && str_ends_with($identifier, '"'))
            || (str_starts_with($identifier, '[') && str_ends_with($identifier, ']'));
    }

    /**
     * @param string $identifier
     * @return bool
     */
    protected function isExpression(string $identifier): bool
    {
        // Special case: asterisk for SELECT *
        if ($identifier === '*') {
            return true;
        }

        // Check for SQL functions or expressions
        $patterns = [
            '/^\w+\s*\(.*\)$/',        // Functions: COUNT(*), SUM(column)
            '/\s[\+\-\*\/\%]\s/',      // Mathematical operators (must have spaces around them)
            '/\s+(AND|OR|NOT)\s+/i',   // Logical operators
            '/^\d+$/',                 // Numeric literals
            '/^\'.*\'$/',              // String literals
            '/\s*,\s*/',               // Multiple columns
        ];

        return array_any($patterns, static fn ($pattern) => (bool) preg_match($pattern, $identifier));
    }

    /**
     * @param string $table
     * @param string|null $alias
     * @return string
     */
    protected function formatTable(string $table, ?string $alias = null): string
    {
        $formatted = $this->formatIdentifier($table);

        if ($alias !== null && $alias !== '') {
            $formatted .= ' AS ' . $this->formatIdentifier($alias);
        }

        return $formatted;
    }

    /**
     * @param string $column
     * @param string|null $tableAlias
     * @param string|null $columnAlias
     * @return string
     */
    protected function formatColumn(string $column, ?string $tableAlias = null, ?string $columnAlias = null): string
    {
        $formatted = $this->formatIdentifier($column);

        if ($tableAlias !== null && !str_contains($column, '.')) {
            $formatted = $this->formatIdentifier($tableAlias) . '.' . $this->formatIdentifier($column);
        }

        if ($columnAlias !== null && $columnAlias !== '') {
            $formatted .= ' AS ' . $this->formatIdentifier($columnAlias);
        }

        return $formatted;
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function formatValue(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_numeric($value) => (string)$value,
            is_string($value) => $this->quoteString($value),
            is_scalar($value) => $this->quoteString((string)$value),
            default => $this->quoteString('')
        };
    }

    /**
     * @param string $value
     * @return string
     */
    protected function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param array<string> $identifiers
     * @return string
     */
    protected function formatIdentifierList(array $identifiers): string
    {
        return implode(', ', array_map([$this, 'formatIdentifier'], $identifiers));
    }
}
