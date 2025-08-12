<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\ConnectorInterface;
use MulerTech\Database\Database\Interface\DatabaseParameterParserInterface;
use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\Database\Interface\QueryExecutorInterface;
use MulerTech\Database\Database\Interface\Statement;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PhpDatabaseManager::class)]
final class PhpDatabaseManagerTest extends TestCase
{
    private ConnectorInterface $mockConnector;
    private PDO $mockPdo;
    private PhpDatabaseManager $manager;
    private array $parameters;

    protected function setUp(): void
    {
        $this->mockConnector = $this->createMock(ConnectorInterface::class);
        $this->mockPdo = $this->createMock(PDO::class);
        $this->parameters = [
            'host' => 'localhost',
            'port' => 3306,
            'user' => 'testuser',
            'pass' => 'testpass',
            'dbname' => 'testdb'
        ];

        // Clear environment variables that might interfere
        $this->clearEnvironmentVariables();

        $this->manager = new PhpDatabaseManager(
            $this->mockConnector,
            $this->parameters
        );
    }

    private function clearEnvironmentVariables(): void
    {
        $envKeys = [
            'DATABASE_SCHEME', 'DATABASE_HOST', 'DATABASE_PORT', 'DATABASE_USER',
            'DATABASE_PASS', 'DATABASE_PATH', 'DATABASE_QUERY', 'DATABASE_FRAGMENT'
        ];
        foreach ($envKeys as $key) {
            unset($_ENV[$key]);
        }
    }

    public function testGetConnection(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->with(
                $this->anything(), // Parameters may be modified by environment
                $this->anything(), // Username may come from environment
                $this->anything()  // Password may come from environment
            )
            ->willReturn($this->mockPdo);

        $connection = $this->manager->getConnection();

        $this->assertSame($this->mockPdo, $connection);
    }

    public function testGetConnectionWithParameterParser(): void
    {
        $mockParser = $this->createMock(DatabaseParameterParserInterface::class);
        $parsedParameters = array_merge($this->parameters, ['charset' => 'utf8mb4']);

        $mockParser->expects($this->once())
            ->method('parseParameters')
            ->with($this->parameters)
            ->willReturn($parsedParameters);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->with($parsedParameters, 'testuser', 'testpass')
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager(
            $this->mockConnector,
            $this->parameters,
            $mockParser
        );

        $connection = $manager->getConnection();

        $this->assertSame($this->mockPdo, $connection);
    }

    public function testGetConnectionCachesConnection(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $connection1 = $this->manager->getConnection();
        $connection2 = $this->manager->getConnection();

        $this->assertSame($connection1, $connection2);
    }

    public function testPrepareWithoutCache(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $mockStatement = $this->createMock(PDOStatement::class);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($query)
            ->willReturn($mockStatement);

        $statement = $this->manager->prepare($query);

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function testPrepareWithOptions(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL];
        $mockStatement = $this->createMock(PDOStatement::class);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($query, $options)
            ->willReturn($mockStatement);

        $statement = $this->manager->prepare($query, $options);

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function testPrepareThrowsExceptionOnFailure(): void
    {
        $query = 'INVALID SQL';

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($query)
            ->willReturn(false);

        $this->mockPdo->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Syntax error']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to prepare statement');

        $this->manager->prepare($query);
    }

    public function testQuery(): void
    {
        $query = 'SELECT * FROM users';
        $mockStatement = $this->createMock(PDOStatement::class);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($mockStatement);

        $statement = $this->manager->query($query);

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function testQueryWithCustomExecutor(): void
    {
        $query = 'SELECT * FROM users';
        $mockExecutor = $this->createMock(QueryExecutorInterface::class);
        $mockStatement = $this->createMock(Statement::class);

        $mockExecutor->expects($this->once())
            ->method('executeQuery')
            ->with($this->mockPdo, $query, null, '', null)
            ->willReturn($mockStatement);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager(
            $this->mockConnector,
            $this->parameters,
            null,
            $mockExecutor
        );

        $result = $manager->query($query);

        $this->assertSame($mockStatement, $result);
    }

    public function testBeginTransaction(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $result = $this->manager->beginTransaction();

        $this->assertTrue($result);
    }

    public function testNestedTransactions(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->assertTrue($this->manager->beginTransaction());
        $this->assertTrue($this->manager->beginTransaction()); // Nested transaction
    }

    public function testCommit(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $this->manager->beginTransaction();
        $result = $this->manager->commit();

        $this->assertTrue($result);
    }

    public function testCommitNestedTransaction(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $this->manager->beginTransaction();
        $this->manager->beginTransaction(); // Nested
        $this->assertTrue($this->manager->commit()); // Should not commit yet
        $this->assertTrue($this->manager->commit()); // Should commit now
    }

    public function testRollBack(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $result = $this->manager->rollBack();

        $this->assertTrue($result);
    }

    public function testInTransaction(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('inTransaction')
            ->willReturn(true);

        $result = $this->manager->inTransaction();

        $this->assertTrue($result);
    }

    public function testSetAttribute(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('setAttribute')
            ->with(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)
            ->willReturn(true);

        $result = $this->manager->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->assertTrue($result);
    }

    public function testExec(): void
    {
        $statement = 'CREATE TABLE test (id INT)';
        $affectedRows = 0;

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($statement)
            ->willReturn($affectedRows);

        $result = $this->manager->exec($statement);

        $this->assertEquals($affectedRows, $result);
    }

    public function testExecThrowsExceptionOnFailure(): void
    {
        $statement = 'INVALID SQL';

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('exec')
            ->with($statement)
            ->willReturn(false);

        $this->mockPdo->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Syntax error']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute statement');

        $this->manager->exec($statement);
    }

    public function testLastInsertId(): void
    {
        $insertId = '123';

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->with(null)
            ->willReturn($insertId);

        $result = $this->manager->lastInsertId();

        $this->assertEquals($insertId, $result);
    }

    public function testLastInsertIdWithSequenceName(): void
    {
        $sequenceName = 'users_id_seq';
        $insertId = '456';

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->with($sequenceName)
            ->willReturn($insertId);

        $result = $this->manager->lastInsertId($sequenceName);

        $this->assertEquals($insertId, $result);
    }

    public function testLastInsertIdThrowsExceptionOnFailure(): void
    {
        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get last insert ID');

        $this->manager->lastInsertId();
    }


    public function testGetConnectionWithEmptyCredentials(): void
    {
        $parametersWithoutCredentials = ['host' => 'localhost'];

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->with(
                $this->anything(), // Parameters may be modified by environment
                $this->anything(), // Username may come from environment
                $this->anything()  // Password may come from environment
            )
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager(
            $this->mockConnector,
            $parametersWithoutCredentials
        );

        $connection = $manager->getConnection();

        $this->assertSame($this->mockPdo, $connection);
    }

    public function testBeginTransactionThrowsPDOException(): void
    {
        $pdoException = new \PDOException('Connection failed', 2002);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException($pdoException);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Connection failed');
        $this->expectExceptionCode(2002);

        $this->manager->beginTransaction();
    }

    public function testCommitThrowsPDOException(): void
    {
        $pdoException = new \PDOException('Commit failed', 2006);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        // First begin a transaction
        $this->mockPdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        // Then make commit throw exception
        $this->mockPdo->expects($this->once())
            ->method('commit')
            ->willThrowException($pdoException);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Commit failed');
        $this->expectExceptionCode(2006);

        $this->manager->beginTransaction();
        $this->manager->commit();
    }

    public function testRollBackThrowsPDOException(): void
    {
        $pdoException = new \PDOException('Rollback failed', 2013);

        $this->mockConnector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $this->mockPdo->expects($this->once())
            ->method('rollBack')
            ->willThrowException($pdoException);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Rollback failed');
        $this->expectExceptionCode(2013);

        $this->manager->rollBack();
    }
}