<?php

namespace MulerTech\Database\Tests\Relational;

use MulerTech\Database\Query\QueryBuilder;
use MulerTech\Database\Query\SelectBuilder;
use MulerTech\Database\Relational\Sql\ComparisonOperator;
use MulerTech\Database\Relational\Sql\LinkOperator;
use MulerTech\Database\Relational\Sql\SqlOperations;
use PHPUnit\Framework\TestCase;

class SqlOperationsTest extends TestCase
{
    public function testManualOperation(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->manualOperation('age < 90');
        self::assertEquals(' age < 90', $sqlOperation);
    }

    public function testAddOperation(): void
    {
        $sqlOperation = new SqlOperations('age > 10');
        $sqlOperation->addOperation('age < 90');
        self::assertEquals(' age > 10 AND age < 90', $sqlOperation);
    }

    public function testAnd(): void
    {
        $sqlOperation = new SqlOperations('age > 10');
        $sqlOperation->and('age < 90');
        self::assertEquals(' age > 10 AND age < 90', $sqlOperation);
    }

    public function testAndNot(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->addOperation('age > 10', LinkOperator::NOT);
        self::assertEquals(' NOT age > 10', $sqlOperation);
    }

    public function testBraketOperationWithAnd(): void
    {
        $sqlOperation1 = new SqlOperations('age > 10 + bonus');
        $sqlOperation = new SqlOperations($sqlOperation1);
        $sqlOperation->and('age < 60');
        self::assertEquals(' (age > 10 + bonus) AND age < 60', $sqlOperation);
    }

    public function testOperationWithBraketAnd(): void
    {
        $sqlOperation2 = new SqlOperations('age > 10 + bonus');
        $sqlOperation = new SqlOperations('age < 60');
        $sqlOperation->and($sqlOperation2);
        self::assertEquals(' age < 60 AND (age > 10 + bonus)', $sqlOperation);
    }

    public function testOr(): void
    {
        $sqlOperation = new SqlOperations('age > 18');
        $sqlOperation->or('city=\'Paris\'');
        self::assertEquals(' age > 18 OR city=\'Paris\'', $sqlOperation);
    }

    public function testOrNot(): void
    {
        $sqlOperation = new SqlOperations('age > 18');
        $sqlOperation->orNot('city=\'Paris\'');
        self::assertEquals(' age > 18 OR NOT city=\'Paris\'', $sqlOperation);
    }

    public function testInWithArrayList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->in('city', ['paris', 'lyon', 'marseille']);
        self::assertEquals(' city IN (\'paris\', \'lyon\', \'marseille\')', $sqlOperation);
    }

    public function testInWithStringList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->in('city', '\'paris\', \'lyon\', \'marseille\'');
        self::assertEquals(' city IN (\'paris\', \'lyon\', \'marseille\')', $sqlOperation);
    }

    public function testInWithQueryBuilder(): void
    {
        $subQuery = new QueryBuilder()->select('city')
            ->from('address')
            ->where('department', 'paris');
        self::assertEquals(
            'SELECT `city` FROM `address` WHERE `department` = :param0',
            $subQuery->toSql()
        );
        /** @var SelectBuilder $query */
        $query = new QueryBuilder()->select('username');
        $query
            ->from('users')
            ->whereIn('city', $subQuery);
        self::assertEquals(
            'SELECT `users` WHERE `city` IN (SELECT `city` FROM `address` WHERE `department`= :param0)',
            $query->toSql()
        );
    }

    public function testNotInWithArrayList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->notIn('city', ['paris', 'lyon', 'marseille']);
        self::assertEquals(
            ' city NOT IN (\'paris\', \'lyon\', \'marseille\')',
            $sqlOperation
        );
    }

    public function testNotInWithStringList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->notIn('city', '\'paris\', \'lyon\', \'marseille\'');
        self::assertEquals(
            ' city NOT IN (\'paris\', \'lyon\', \'marseille\')',
            $sqlOperation
        );
    }

    public function testNotInWithQueryBuilder(): void
    {
        $sqlOperation = new SqlOperations();
        $query = new QueryBuilder();
        $query
            ->select('city')
            ->from('address')
            ->where(SqlOperations::notEqual('department', '\'paris\''));
        $sqlOperation->notIn('city', $query);
        self::assertEquals(
            ' city NOT IN (SELECT `city` FROM `address` WHERE department<>\'paris\')',
            $sqlOperation
        );
    }

    public function testSqlOperationsEqual(): void
    {
        self::assertEquals('total=1000', SqlOperations::equal('total', 1000));
    }

    public function testSqlOperationsNotEqual(): void
    {
        self::assertEquals('total<>1000', SqlOperations::notEqual('total', 1000));
    }

    public function testSqlOperationsGreater(): void
    {
        self::assertEquals('total>1000', SqlOperations::greater('total', 1000));
    }

    public function testSqlOperationsNotGreater(): void
    {
        self::assertEquals('total!>1000', SqlOperations::notGreater('total', 1000));
    }

    public function testSqlOperationsLess(): void
    {
        self::assertEquals('total<1000', SqlOperations::less('total', 1000));
    }

    public function testSqlOperationsNotLess(): void
    {
        self::assertEquals('total!<1000', SqlOperations::notLess('total', 1000));
    }

    public function testSqlOperationsGreaterEqual(): void
    {
        self::assertEquals('total>=1000', SqlOperations::greaterEqual('total', 1000));
    }

    public function testSqlOperationsLessEqual(): void
    {
        self::assertEquals('total<=1000', SqlOperations::lessEqual('total', 1000));
    }

    public function testSqlOperationsAdd(): void
    {
        self::assertEquals('total+1000', SqlOperations::add('total', 1000));
    }

    public function testSqlOperationsSubtract(): void
    {
        self::assertEquals('total-1000', SqlOperations::subtract('total', 1000));
    }

    public function testSqlOperationsMultiply(): void
    {
        self::assertEquals('total*1000', SqlOperations::multiply('total', 1000));
    }

    public function testSqlOperationsDivide(): void
    {
        self::assertEquals('total/1000', SqlOperations::divide('total', 1000));
    }

    public function testSqlOperationsModulo(): void
    {
        self::assertEquals('total%1000', SqlOperations::modulo('total', 1000));
    }

    public function testSqlOperationsBitAnd(): void
    {
        self::assertEquals('total&1000', SqlOperations::bitAnd('total', 1000));
    }

    public function testSqlOperationsBitOr(): void
    {
        self::assertEquals('total|1000', SqlOperations::bitOr('total', 1000));
    }

    public function testSqlOperationsBitExclusiveOr(): void
    {
        self::assertEquals('total^1000', SqlOperations::bitExclusiveOr('total', 1000));
    }

    public function testSqlOperationsBitNot(): void
    {
        self::assertEquals('total~1000', SqlOperations::bitNot('total', 1000));
    }

    public function testReverseOperator(): void
    {
        self::assertEquals('>=', ComparisonOperator::LESS_THAN->reverse()->value);
    }
}