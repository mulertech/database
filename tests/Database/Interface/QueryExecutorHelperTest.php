<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use InvalidArgumentException;
use MulerTech\Database\Database\Interface\QueryExecutorHelper;
use MulerTech\Database\Database\Interface\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(QueryExecutorHelper::class)]
final class QueryExecutorHelperTest extends TestCase
{
    private QueryExecutorHelper $executor;
    private PDO $mockPdo;
    private PDOStatement $mockStatement;

    protected function setUp(): void
    {
        $this->executor = new QueryExecutorHelper();
        $this->mockPdo = $this->createStub(PDO::class);
        $this->mockStatement = $this->createStub(PDOStatement::class);
    }

    public function testExecuteQueryWithNullFetchMode(): void
    {
        $query = 'SELECT * FROM users';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery($pdo, $query);

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchClass(): void
    {
        $query = 'SELECT * FROM users';
        $className = 'User';
        $constructorArgs = ['arg1', 'arg2'];

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_CLASS, $className, $constructorArgs)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_CLASS,
            $className,
            $constructorArgs
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchClassAndNonStringClassName(): void
    {
        $query = 'SELECT * FROM users';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_CLASS, '', [])
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_CLASS,
            123 // Non-string class name
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchClassAndNullConstructorArgs(): void
    {
        $query = 'SELECT * FROM users';
        $className = 'User';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_CLASS, $className, [])
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_CLASS,
            $className,
            null
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchInto(): void
    {
        $query = 'SELECT * FROM users';
        $object = new stdClass();

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_INTO, $object)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_INTO,
            $object
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchIntoAndNonObject(): void
    {
        $query = 'SELECT * FROM users';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('When using FETCH_INTO, the third argument must be an object.');

        $this->executor->executeQuery(
            $this->mockPdo,
            $query,
            PDO::FETCH_INTO,
            'not_an_object'
        );
    }

    public function testExecuteQueryWithFetchIntoAndInteger(): void
    {
        $query = 'SELECT * FROM users';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('When using FETCH_INTO, the third argument must be an object.');

        $this->executor->executeQuery(
            $this->mockPdo,
            $query,
            PDO::FETCH_INTO,
            123
        );
    }

    public function testExecuteQueryWithDefaultFetchMode(): void
    {
        $query = 'SELECT * FROM users';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_ASSOC)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_ASSOC
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithCustomFetchMode(): void
    {
        $query = 'SELECT * FROM users';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_NUM)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_NUM
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryThrowsExceptionOnQueryFailure(): void
    {
        $query = 'INVALID SQL';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Syntax error in SQL']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Query failed. Error: Syntax error in SQL. Statement: INVALID SQL');

        $this->executor->executeQuery($pdo, $query);
    }

    public function testExecuteQueryThrowsExceptionWithUnknownError(): void
    {
        $query = 'INVALID SQL';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Query failed. Error: Unknown error. Statement: INVALID SQL');

        $this->executor->executeQuery($pdo, $query);
    }

    public function testExecuteQueryWithEmptyQuery(): void
    {
        $query = '';

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery($pdo, $query);

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithComplexObject(): void
    {
        $query = 'SELECT * FROM users';
        $complexObject = new class {
            public string $name = 'test';
            public int $age = 25;
        };

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_INTO, $complexObject)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_INTO,
            $complexObject
        );

        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithAllParametersSet(): void
    {
        $query = 'SELECT * FROM users';
        $className = 'User';
        $constructorArgs = ['param1', 'param2'];

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('query')
            ->with($query, PDO::FETCH_CLASS, $className, $constructorArgs)
            ->willReturn($this->mockStatement);

        $result = $this->executor->executeQuery(
            $pdo,
            $query,
            PDO::FETCH_CLASS,
            $className,
            $constructorArgs
        );

        $this->assertInstanceOf(Statement::class, $result);
    }
}