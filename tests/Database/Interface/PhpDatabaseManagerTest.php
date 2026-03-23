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
        $this->mockConnector = $this->createStub(ConnectorInterface::class);
        $this->mockPdo = $this->createStub(PDO::class);
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
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $connection = $manager->getConnection();

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

        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->with($parsedParameters, 'testuser', 'testpass')
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager(
            $connector,
            $this->parameters,
            $mockParser
        );

        $connection = $manager->getConnection();

        $this->assertSame($this->mockPdo, $connection);
    }

    public function testGetConnectionCachesConnection(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $connection1 = $manager->getConnection();
        $connection2 = $manager->getConnection();

        $this->assertSame($connection1, $connection2);
    }

    public function testPrepareWithoutCache(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $mockStatement = $this->createStub(PDOStatement::class);

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($query)
            ->willReturn($mockStatement);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $statement = $manager->prepare($query);

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function testPrepareWithOptions(): void
    {
        $query = 'SELECT * FROM users WHERE id = ?';
        $options = [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL];
        $mockStatement = $this->createStub(PDOStatement::class);

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($query, $options)
            ->willReturn($mockStatement);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $statement = $manager->prepare($query, $options);

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function testPrepareThrowsExceptionOnFailure(): void
    {
        $query = 'INVALID SQL';

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($query)
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Syntax error']);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to prepare statement');

        $manager->prepare($query);
    }

    public function testQuery(): void
    {
        $query = 'SELECT * FROM users';
        $mockStatement = $this->createStub(PDOStatement::class);

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($mockStatement);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $statement = $manager->query($query);

        $this->assertInstanceOf(Statement::class, $statement);
    }

    public function testQueryWithCustomExecutor(): void
    {
        $query = 'SELECT * FROM users';
        $mockExecutor = $this->createMock(QueryExecutorInterface::class);
        $mockStatement = $this->createStub(Statement::class);

        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($this->mockPdo);

        $mockExecutor->expects($this->once())
            ->method('executeQuery')
            ->with($this->mockPdo, $query, null, '', null)
            ->willReturn($mockStatement);

        $manager = new PhpDatabaseManager(
            $connector,
            $this->parameters,
            null,
            $mockExecutor
        );

        $result = $manager->query($query);

        $this->assertSame($mockStatement, $result);
    }

    public function testBeginTransaction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->beginTransaction();

        $this->assertTrue($result);
    }

    public function testNestedTransactions(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $this->assertTrue($manager->beginTransaction());
        $this->assertTrue($manager->beginTransaction()); // Nested transaction
    }

    public function testCommit(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $manager->beginTransaction();
        $result = $manager->commit();

        $this->assertTrue($result);
    }

    public function testCommitNestedTransaction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $manager->beginTransaction();
        $manager->beginTransaction(); // Nested
        $this->assertTrue($manager->commit()); // Should not commit yet
        $this->assertTrue($manager->commit()); // Should commit now
    }

    public function testRollBack(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->rollBack();

        $this->assertTrue($result);
    }

    public function testInTransaction(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('inTransaction')
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->inTransaction();

        $this->assertTrue($result);
    }

    public function testSetAttribute(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('setAttribute')
            ->with(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)
            ->willReturn(true);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->assertTrue($result);
    }

    public function testExec(): void
    {
        $statement = 'CREATE TABLE test (id INT)';
        $affectedRows = 0;

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('exec')
            ->with($statement)
            ->willReturn($affectedRows);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->exec($statement);

        $this->assertEquals($affectedRows, $result);
    }

    public function testExecThrowsExceptionOnFailure(): void
    {
        $statement = 'INVALID SQL';

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('exec')
            ->with($statement)
            ->willReturn(false);

        $pdo->expects($this->once())
            ->method('errorInfo')
            ->willReturn(['42000', 1064, 'Syntax error']);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to execute statement');

        $manager->exec($statement);
    }

    public function testLastInsertId(): void
    {
        $insertId = '123';

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->with(null)
            ->willReturn($insertId);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->lastInsertId();

        $this->assertEquals($insertId, $result);
    }

    public function testLastInsertIdWithSequenceName(): void
    {
        $sequenceName = 'users_id_seq';
        $insertId = '456';

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->with($sequenceName)
            ->willReturn($insertId);

        $manager = new PhpDatabaseManager($connector, $this->parameters);
        $result = $manager->lastInsertId($sequenceName);

        $this->assertEquals($insertId, $result);
    }

    public function testLastInsertIdThrowsExceptionOnFailure(): void
    {
        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('lastInsertId')
            ->willReturn(false);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get last insert ID');

        $manager->lastInsertId();
    }


    public function testGetConnectionWithEmptyCredentials(): void
    {
        $parametersWithoutCredentials = ['host' => 'localhost'];

        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($this->mockPdo);

        $manager = new PhpDatabaseManager(
            $connector,
            $parametersWithoutCredentials
        );

        $connection = $manager->getConnection();

        $this->assertSame($this->mockPdo, $connection);
    }

    public function testBeginTransactionThrowsPDOException(): void
    {
        $pdoException = new \PDOException('Connection failed', 2002);

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException($pdoException);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Connection failed');
        $this->expectExceptionCode(2002);

        $manager->beginTransaction();
    }

    public function testCommitThrowsPDOException(): void
    {
        $pdoException = new \PDOException('Commit failed', 2006);

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $pdo->expects($this->once())
            ->method('commit')
            ->willThrowException($pdoException);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Commit failed');
        $this->expectExceptionCode(2006);

        $manager->beginTransaction();
        $manager->commit();
    }

    public function testRollBackThrowsPDOException(): void
    {
        $pdoException = new \PDOException('Rollback failed', 2013);

        $pdo = $this->createMock(PDO::class);
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())
            ->method('connect')
            ->willReturn($pdo);

        $pdo->expects($this->once())
            ->method('rollBack')
            ->willThrowException($pdoException);

        $manager = new PhpDatabaseManager($connector, $this->parameters);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Rollback failed');
        $this->expectExceptionCode(2013);

        $manager->rollBack();
    }
}