<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Query\Builder;

use MulerTech\Database\ORM\EmEngine;
use MulerTech\Database\Query\Builder\AbstractQueryBuilder;
use MulerTech\Database\Query\Builder\Traits\ValidationTrait;
use MulerTech\Database\Query\Builder\Traits\QueryOptionsTrait;
use MulerTech\Database\Query\Builder\Traits\OrderLimitTrait;
use MulerTech\Database\Query\Builder\Traits\WhereClauseTrait;
use MulerTech\Database\Query\Builder\Traits\JoinClauseTrait;
use MulerTech\Database\Core\Traits\SqlFormatterTrait;
use MulerTech\Database\Query\Clause\JoinClauseBuilder;
use MulerTech\Database\Query\Clause\WhereClauseBuilder;
use PDO;

/**
 * Universal testable query builder that can be used for testing all traits
 */
class TestableQueryBuilder extends AbstractQueryBuilder
{
    use ValidationTrait;
    use QueryOptionsTrait;
    use OrderLimitTrait;
    use WhereClauseTrait;
    use JoinClauseTrait;
    use SqlFormatterTrait;

    private string $sql = '';

    public function __construct(?EmEngine $emEngine = null)
    {
        parent::__construct($emEngine);
        // Initialize builders if they exist as properties
        if (property_exists($this, 'whereBuilder')) {
            $this->whereBuilder = new WhereClauseBuilder($this->parameterBag);
        }
        if (property_exists($this, 'joinBuilder')) {
            $this->joinBuilder = new JoinClauseBuilder($this->parameterBag);
        }
    }

    public function getQueryType(): string
    {
        return 'TEST';
    }

    protected function buildSql(): string
    {
        return $this->sql ?: 'SELECT * FROM test';
    }

    public function setSql(string $sql): void
    {
        $this->sql = $sql;
    }

    // Expose protected methods for testing - AbstractQueryBuilder methods
    public function testBindParameter(mixed $value, ?int $type = PDO::PARAM_STR): string
    {
        return $this->bindParameter($value, $type);
    }

    public function testBuildSetClause(array $data): string
    {
        return $this->buildSetClause($data);
    }

    // Expose protected methods for testing - ValidationTrait methods
    public function testValidateTableName(string $table): void
    {
        $this->validateTableName($table);
    }

    public function testValidateColumnName(string $column): void
    {
        $this->validateColumnName($column);
    }

    public function testValidateColumnNames(array $columns): void
    {
        $this->validateColumnNames($columns);
    }

    public function testValidateNotEmpty(mixed $value, string $fieldName): void
    {
        $this->validateNotEmpty($value, $fieldName);
    }

    // Expose protected methods for testing - QueryOptionsTrait methods
    public function testBuildQueryModifiers(): array
    {
        return $this->buildQueryModifiers();
    }

    // Expose protected methods for testing - OrderLimitTrait methods
    public function testBuildOrderByClause(): string
    {
        return $this->buildOrderByClause();
    }

    public function testBuildLimitClause(): string
    {
        return $this->buildLimitClause();
    }
}
