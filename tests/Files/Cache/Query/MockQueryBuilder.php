<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Files\Cache\Query;

use MulerTech\Database\Query\Builder\AbstractQueryBuilder;

/**
 * Mock query builder for testing
 */
class MockQueryBuilder extends AbstractQueryBuilder
{
    private int $id = 1;
    private string $queryType;
    private array $mockProperties;
    private string $table = 'test_table';
    private array $columns = ['test_column'];
    private array $conditions = [];
    private array $joins = [];

    public function __construct(string $queryType = 'SELECT', array $mockProperties = [])
    {
        parent::__construct();
        $this->queryType = $queryType;
        $this->mockProperties = $mockProperties;
        
        // Set mock properties for structure extraction
        foreach ($mockProperties as $property => $value) {
            $this->{$property} = $value;
        }
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