<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\QueryExecutorInterface;
use MulerTech\Database\Database\Interface\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class QueryExecutorInterfaceTest extends TestCase
{
    private QueryExecutorInterface $executor;
    private PDO $mockPdo;

    protected function setUp(): void
    {
        $this->mockPdo = $this->createMock(PDO::class);
        
        $mockStatement = $this->createMock(PDOStatement::class);
        $mockStatement->method('execute')->willReturn(true);
        $mockStatement->method('fetchAll')->willReturn([]);
        $mockStatement->method('fetch')->willReturn(false);
        $mockStatement->method('rowCount')->willReturn(0);
        
        $this->executor = new class($mockStatement) implements QueryExecutorInterface {
            private PDOStatement $mockStatement;
            
            public function __construct(PDOStatement $mockStatement) {
                $this->mockStatement = $mockStatement;
            }
            
            public function executeQuery(
                PDO $pdo,
                string $query,
                ?int $fetchMode = null,
                int|string|object $arg3 = '',
                ?array $constructorArgs = null
            ): Statement {
                return new Statement($this->mockStatement);
            }
        };
    }

    public function testExecuteQueryWithBasicParameters(): void
    {
        $query = "SELECT * FROM users WHERE id = ?";
        
        $result = $this->executor->executeQuery($this->mockPdo, $query);
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchMode(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_ASSOC;
        
        $result = $this->executor->executeQuery($this->mockPdo, $query, $fetchMode);
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithFetchModeAndClass(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_CLASS;
        $className = 'stdClass';
        
        $result = $this->executor->executeQuery($this->mockPdo, $query, $fetchMode, $className);
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithConstructorArgs(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_CLASS;
        $className = 'stdClass';
        $constructorArgs = ['arg1', 'arg2'];
        
        $result = $this->executor->executeQuery(
            $this->mockPdo,
            $query,
            $fetchMode,
            $className,
            $constructorArgs
        );
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithNullFetchMode(): void
    {
        $query = "INSERT INTO users (name) VALUES (?)";
        
        $result = $this->executor->executeQuery($this->mockPdo, $query, null);
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithEmptyArg3(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_ASSOC;
        
        $result = $this->executor->executeQuery($this->mockPdo, $query, $fetchMode, '');
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithIntegerArg3(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_COLUMN;
        $columnIndex = 0;
        
        $result = $this->executor->executeQuery($this->mockPdo, $query, $fetchMode, $columnIndex);
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithObjectArg3(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_INTO;
        $object = new \stdClass();
        
        $result = $this->executor->executeQuery($this->mockPdo, $query, $fetchMode, $object);
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryWithNullConstructorArgs(): void
    {
        $query = "SELECT * FROM users";
        $fetchMode = PDO::FETCH_CLASS;
        $className = 'stdClass';
        
        $result = $this->executor->executeQuery(
            $this->mockPdo,
            $query,
            $fetchMode,
            $className,
            null
        );
        
        $this->assertInstanceOf(Statement::class, $result);
    }

    public function testExecuteQueryReturnsStatement(): void
    {
        $query = "SELECT 1";
        
        $result = $this->executor->executeQuery($this->mockPdo, $query);
        
        $this->assertInstanceOf(Statement::class, $result);
    }
}