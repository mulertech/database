<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Query\Builder;

use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Query\Builder\SelectBuilder;
use MulerTech\Database\Query\Builder\InsertBuilder;
use MulerTech\Database\Query\Builder\UpdateBuilder;
use MulerTech\Database\Query\Builder\DeleteBuilder;
use MulerTech\Database\Query\Builder\RawQueryBuilder;
use MulerTech\Database\ORM\EmEngine;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for QueryBuilder class
 */
class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new QueryBuilder();
    }

    public function testConstructorWithoutEmEngine(): void
    {
        $qb = new QueryBuilder();
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }

    public function testConstructorWithEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $qb = new QueryBuilder($emEngine);
        $this->assertInstanceOf(QueryBuilder::class, $qb);
    }

    public function testSelectWithoutColumns(): void
    {
        $builder = $this->queryBuilder->select();
        
        $this->assertInstanceOf(SelectBuilder::class, $builder);
    }

    public function testSelectWithColumns(): void
    {
        $builder = $this->queryBuilder->select('id', 'name', 'email');
        
        $this->assertInstanceOf(SelectBuilder::class, $builder);
    }

    public function testInsert(): void
    {
        $tableName = 'users';
        $builder = $this->queryBuilder->insert($tableName);
        
        $this->assertInstanceOf(InsertBuilder::class, $builder);
    }

    public function testUpdateWithoutAlias(): void
    {
        $tableName = 'users';
        $builder = $this->queryBuilder->update($tableName);
        
        $this->assertInstanceOf(UpdateBuilder::class, $builder);
    }

    public function testUpdateWithAlias(): void
    {
        $tableName = 'users';
        $alias = 'u';
        $builder = $this->queryBuilder->update($tableName, $alias);
        
        $this->assertInstanceOf(UpdateBuilder::class, $builder);
    }

    public function testDeleteWithoutAlias(): void
    {
        $tableName = 'users';
        $builder = $this->queryBuilder->delete($tableName);
        
        $this->assertInstanceOf(DeleteBuilder::class, $builder);
    }

    public function testDeleteWithAlias(): void
    {
        $tableName = 'users';
        $alias = 'u';
        $builder = $this->queryBuilder->delete($tableName, $alias);
        
        $this->assertInstanceOf(DeleteBuilder::class, $builder);
    }

    public function testRaw(): void
    {
        $sql = 'SELECT COUNT(*) FROM users';
        $builder = $this->queryBuilder->raw($sql);
        
        $this->assertInstanceOf(RawQueryBuilder::class, $builder);
    }

    public function testMultipleBuilderCreations(): void
    {
        // Test that we can create multiple builders
        $select1 = $this->queryBuilder->select();
        $select2 = $this->queryBuilder->select('id');
        $insert = $this->queryBuilder->insert('users');
        $update = $this->queryBuilder->update('users');
        $delete = $this->queryBuilder->delete('users');
        $raw = $this->queryBuilder->raw('SELECT 1');

        $this->assertInstanceOf(SelectBuilder::class, $select1);
        $this->assertInstanceOf(SelectBuilder::class, $select2);
        $this->assertInstanceOf(InsertBuilder::class, $insert);
        $this->assertInstanceOf(UpdateBuilder::class, $update);
        $this->assertInstanceOf(DeleteBuilder::class, $delete);
        $this->assertInstanceOf(RawQueryBuilder::class, $raw);

        // Ensure they are different instances
        $this->assertNotSame($select1, $select2);
    }

    public function testWithMockedEmEngine(): void
    {
        $emEngine = $this->createMock(EmEngine::class);
        $qb = new QueryBuilder($emEngine);

        $select = $qb->select();
        $insert = $qb->insert('users');
        $update = $qb->update('users');
        $delete = $qb->delete('users');
        $raw = $qb->raw('SELECT 1');

        $this->assertInstanceOf(SelectBuilder::class, $select);
        $this->assertInstanceOf(InsertBuilder::class, $insert);
        $this->assertInstanceOf(UpdateBuilder::class, $update);
        $this->assertInstanceOf(DeleteBuilder::class, $delete);
        $this->assertInstanceOf(RawQueryBuilder::class, $raw);
    }
}