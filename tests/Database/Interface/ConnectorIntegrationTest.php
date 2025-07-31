<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\ConnectorInterface;
use MulerTech\Database\Database\Interface\PdoConnector;
use MulerTech\Database\Database\MySQLDriver;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PdoConnector::class)]
final class ConnectorIntegrationTest extends TestCase
{
    private ConnectorInterface $connector;
    private array $validParameters;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('PDO MySQL extension is not available');
        }
        
        $this->connector = new PdoConnector(new MySQLDriver());
        $this->validParameters = [
            'host' => $_ENV['DATABASE_HOST'] ?? 'db',
            'port' => (int)($_ENV['DATABASE_PORT'] ?? 3306),
            'dbname' => $_ENV['DATABASE_PATH'] ? substr($_ENV['DATABASE_PATH'], 1) : 'db',
            'charset' => 'utf8mb4'
        ];
    }

    public function testConnectWithValidParameters(): void
    {
        $username = $_ENV['DATABASE_USER'] ?? 'user';
        $password = $_ENV['DATABASE_PASS'] ?? 'password';

        $connection = $this->connector->connect(
            $this->validParameters,
            $username,
            $password
        );

        $this->assertInstanceOf(PDO::class, $connection);
        $this->assertEquals(PDO::ERRMODE_EXCEPTION, $connection->getAttribute(PDO::ATTR_ERRMODE));
        $this->assertEquals(PDO::FETCH_ASSOC, $connection->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testConnectWithCustomOptions(): void
    {
        $username = $_ENV['DATABASE_USER'] ?? 'user';
        $password = $_ENV['DATABASE_PASS'] ?? 'password';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
        ];

        $connection = $this->connector->connect(
            $this->validParameters,
            $username,
            $password,
            $options
        );

        $this->assertInstanceOf(PDO::class, $connection);
        // Test that the connection is established successfully with custom options
        $this->assertEquals(PDO::ERRMODE_SILENT, $connection->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testConnectWithInvalidHost(): void
    {
        $invalidParameters = array_merge($this->validParameters, ['host' => 'invalid-host']);
        $username = $_ENV['DATABASE_USER'] ?? 'user';
        $password = $_ENV['DATABASE_PASS'] ?? 'password';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($invalidParameters, $username, $password);
    }

    public function testConnectWithInvalidCredentials(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect(
            $this->validParameters,
            'invalid_user',
            'invalid_password'
        );
    }

    public function testConnectWithUnixSocket(): void
    {
        $socketParameters = [
            'unix_socket' => '/tmp/mysql.sock',
            'dbname' => 'test'
        ];
        $username = $_ENV['DATABASE_USER'] ?? 'user';
        $password = $_ENV['DATABASE_PASS'] ?? 'password';

        // This will fail in our test environment but should throw RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($socketParameters, $username, $password);
    }

    public function testConnectionQuery(): void
    {
        $username = $_ENV['DATABASE_USER'] ?? 'user';
        $password = $_ENV['DATABASE_PASS'] ?? 'password';

        $connection = $this->connector->connect(
            $this->validParameters,
            $username,
            $password
        );

        $statement = $connection->query('SELECT 1 as test');
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals(['test' => 1], $result);
    }
}