<?php

namespace MulerTech\Database\Tests;

use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use MulerTech\Database\Relational\Sql\SqlOperators;
use MulerTech\Database\Relational\Sql\SqlQuery;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RelationalSqlOperationsTest extends TestCase
{
    public function testSqlOperations(): void
    {
        $sqlOperation = new SqlOperations('age > 10');
        $sqlOperation->addOperation('age < 90');
        self::assertEquals(' age > 10 AND age < 90', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsAnd(): void
    {
        $sqlOperation = new SqlOperations('age > 10');
        $sqlOperation->and('age < 90');
        self::assertEquals(' age > 10 AND age < 90', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsNot(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->addOperation('age > 10', 'not');
        self::assertEquals(' NOT age > 10', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsAndSqlOperationFirst(): void
    {
        $sqlOperation1 = new SqlOperations('age > 10 + bonus');
        $sqlOperation = new SqlOperations($sqlOperation1);
        $sqlOperation->and('age < 60');
        self::assertEquals(' ( age > 10 + bonus) AND age < 60', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsAndSqlOperationSecond(): void
    {
        $sqlOperation2 = new SqlOperations('age > 10 + bonus');
        $sqlOperation = new SqlOperations('age < 60');
        $sqlOperation->and($sqlOperation2);
        self::assertEquals(' age < 60 AND ( age > 10 + bonus)', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsOr(): void
    {
        $sqlOperation = new SqlOperations('age > 18');
        $sqlOperation->or('city=\'Paris\'');
        self::assertEquals(' age > 18 OR city=\'Paris\'', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsInArrayList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->in('city', ['paris', 'lyon', 'marseille']);
        self::assertEquals(' city IN (\'paris\', \'lyon\', \'marseille\')', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsInStringList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->in('city', '\'paris\', \'lyon\', \'marseille\'');
        self::assertEquals(' city IN (\'paris\', \'lyon\', \'marseille\')', $sqlOperation->generateOperation());
    }

    public function testSqlOperationsInQuery(): void
    {
        $sqlOperation = new SqlOperations();
        $query = new QueryBuilder();
        $query
            ->select('city')
            ->from('address')
            ->where(SqlOperations::equal('department', '\'paris\''));
        $sqlOperation->in('city', $query);
        self::assertEquals(
            ' city IN (SELECT `city` FROM `address` WHERE department=\'paris\')',
            $sqlOperation->generateOperation()
        );
    }

    public function testSqlOperationsNotInArrayList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->notIn('city', ['paris', 'lyon', 'marseille']);
        self::assertEquals(
            ' city NOT IN (\'paris\', \'lyon\', \'marseille\')',
            $sqlOperation->generateOperation()
        );
    }

    public function testSqlOperationsNotInStringList(): void
    {
        $sqlOperation = new SqlOperations();
        $sqlOperation->notIn('city', '\'paris\', \'lyon\', \'marseille\'');
        self::assertEquals(
            ' city NOT IN (\'paris\', \'lyon\', \'marseille\')',
            $sqlOperation->generateOperation()
        );
    }

    public function testSqlOperationsNotInQuery(): void
    {
        $sqlOperation = new SqlOperations();
        $query = new QueryBuilder();
        $query
            ->select('city')
            ->from('address')
            ->where(SqlOperations::notEqual('department', '\'paris\''));
        $sqlOperation->notIn('city', $query);
        self::assertEquals(
            ' city NOT IN (SELECT `city` FROM `address` WHERE department!=\'paris\')',
            $sqlOperation->generateOperation()
        );
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

    public function testSqlOperationsAddAssignment(): void
    {
        self::assertEquals('total+1000', SqlOperations::addAssignment('total', 1000));
    }

    public function testSqlOperationsSubtract(): void
    {
        self::assertEquals('total-1000', SqlOperations::subtract('total', 1000));
    }

    public function testSqlOperationsSubtractAssignment(): void
    {
        self::assertEquals('total-1000', SqlOperations::subtractAssignment('total', 1000));
    }

    public function testSqlOperationsMultiply(): void
    {
        self::assertEquals('total*1000', SqlOperations::multiply('total', 1000));
    }

    public function testSqlOperationsMultiplyAssignment(): void
    {
        self::assertEquals('total*1000', SqlOperations::multiplyAssignment('total', 1000));
    }

    public function testSqlOperationsDivide(): void
    {
        self::assertEquals('total/1000', SqlOperations::divide('total', 1000));
    }

    public function testSqlOperationsDivideAssignment(): void
    {
        self::assertEquals('total/1000', SqlOperations::divideAssignment('total', 1000));
    }

    public function testSqlOperationsModulo(): void
    {
        self::assertEquals('total%1000', SqlOperations::modulo('total', 1000));
    }

    public function testSqlOperationsModuloAssignment(): void
    {
        self::assertEquals('total%1000', SqlOperations::moduloAssignment('total', 1000));
    }

    public function testSqlOperationsBitAnd(): void
    {
        self::assertEquals('total&1000', SqlOperations::bitAnd('total', 1000));
    }

    public function testSqlOperationsBitAndAssignment(): void
    {
        self::assertEquals('total&=1000', SqlOperations::bitAndAssignment('total', 1000));
    }

    public function testSqlOperationsBitOr(): void
    {
        self::assertEquals('total|1000', SqlOperations::bitOr('total', 1000));
    }

    public function testSqlOperationsBitOrAssignment(): void
    {
        self::assertEquals('total|=1000', SqlOperations::bitOrAssignment('total', 1000));
    }

    public function testSqlOperationsBitExclusiveOr(): void
    {
        self::assertEquals('total^1000', SqlOperations::bitExclusiveOr('total', 1000));
    }

    public function testSqlOperationsBitExclusiveOrAssignment(): void
    {
        self::assertEquals('total^=1000', SqlOperations::bitExclusiveOrAssignment('total', 1000));
    }

    public function testSqlOperationsBitNot(): void
    {
        self::assertEquals('total~1000', SqlOperations::bitNot('total', 1000));
    }

    public function testReverseOperator(): void
    {
        self::assertEquals('>=', SqlOperators::reverseOperator('<'));
    }

    public function testReverseUnknownOperator(): void
    {
        $this->expectExceptionMessage('Class : SqlOperators, function : reverseOperator. The operator "@" is unknown.');
        SqlOperators::reverseOperator('@');
    }

}