<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache\Query;

use MulerTech\Database\Query\Builder\AbstractQueryBuilder;

/**
 * Mock query builder for testing
 */
class MockQueryBuilder extends AbstractQueryBuilder
{
    private string $queryType;
    public ?array $testArrayProperty = null;

    public function __construct(string $queryType = 'SELECT', ?array $testArrayProperty = null)
    {
        parent::__construct();
        $this->queryType = $queryType;
        $this->testArrayProperty = $testArrayProperty;
    }

    public function getQueryType(): string
    {
        return $this->queryType;
    }

    protected function buildSql(): string
    {
        return 'SELECT * FROM test';
    }
}