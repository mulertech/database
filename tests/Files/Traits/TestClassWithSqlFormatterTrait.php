<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Traits;

use MulerTech\Database\Core\Traits\SqlFormatterTrait;

/**
 * Test class that uses the SqlFormatterTrait for testing purposes
 */
class TestClassWithSqlFormatterTrait
{
    use SqlFormatterTrait;

    // Expose protected methods for testing
    public function callFormatIdentifier(string $identifier): string
    {
        return $this->formatIdentifier($identifier);
    }

    public function callFormatIdentifierWithAlias(string $identifier): string
    {
        return $this->formatIdentifierWithAlias($identifier);
    }

    public function callEscapeIdentifier(string $identifier): string
    {
        return $this->escapeIdentifier($identifier);
    }

    public function callIsEscaped(string $identifier): bool
    {
        return $this->isEscaped($identifier);
    }

    public function callIsExpression(string $identifier): bool
    {
        return $this->isExpression($identifier);
    }

    public function callFormatTable(string $table, ?string $alias = null): string
    {
        return $this->formatTable($table, $alias);
    }

    public function callFormatColumn(string $column, ?string $tableAlias = null, ?string $columnAlias = null): string
    {
        return $this->formatColumn($column, $tableAlias, $columnAlias);
    }

    public function callFormatValue(mixed $value): string
    {
        return $this->formatValue($value);
    }

    public function callQuoteString(string $value): string
    {
        return $this->quoteString($value);
    }

    public function callFormatIdentifierList(array $identifiers): string
    {
        return $this->formatIdentifierList($identifiers);
    }
}