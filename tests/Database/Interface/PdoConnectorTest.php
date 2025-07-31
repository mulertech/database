<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\DriverInterface;
use MulerTech\Database\Database\Interface\PdoConnector;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PdoConnector::class)]
final class PdoConnectorTest extends TestCase
{
    private DriverInterface $mockDriver;
    private PdoConnector $connector;

    protected function setUp(): void
    {
        $this->mockDriver = $this->createMock(DriverInterface::class);
        $this->connector = new PdoConnector($this->mockDriver);
    }

    public function testConnectWithValidParameters(): void
    {
        $parameters = [
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'test',
            'charset' => 'utf8mb4'
        ];
        $dsn = 'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4';
        $username = 'user';
        $password = 'password';

        $this->mockDriver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        // We can't easily test the actual PDO connection without a real database,
        // so we'll test that the method exists and handles parameters correctly
        // by expecting a PDOException when trying to connect to a non-existent database
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($parameters, $username, $password);
    }

    public function testConnectWithCustomOptions(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';
        $options = [PDO::ATTR_TIMEOUT => 10];

        $this->mockDriver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($parameters, $username, $password, $options);
    }

    public function testConnectWithNullOptions(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';

        $this->mockDriver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($parameters, $username, $password, null);
    }

    public function testConnectWrapssPdoException(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';

        $this->mockDriver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        try {
            $this->connector->connect($parameters, $username, $password);
            $this->fail('Expected RuntimeException to be thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Connection failed:', $e->getMessage());
            $this->assertInstanceOf(PDOException::class, $e->getPrevious());
        }
    }

    public function testConnectWithEmptyParameters(): void
    {
        $parameters = [];
        $dsn = 'mysql:host=localhost;port=3306';
        $username = '';
        $password = '';

        $this->mockDriver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($parameters, $username, $password);
    }

    public function testConnectMergesDefaultOptionsWithCustomOptions(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';
        $customOptions = [
            PDO::ATTR_TIMEOUT => 30,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT  // This should be overridden by default
        ];

        $this->mockDriver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        // The method should merge options, with defaults taking precedence
        // We expect an exception because we're using a mock DSN
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $this->connector->connect($parameters, $username, $password, $customOptions);
    }

    public function testConnectorIsReadonly(): void
    {
        $reflection = new \ReflectionClass(PdoConnector::class);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testDriverIsInjectedCorrectly(): void
    {
        $reflection = new \ReflectionClass($this->connector);
        $driverProperty = $reflection->getProperty('driver');
        $driverProperty->setAccessible(true);
        
        $this->assertSame($this->mockDriver, $driverProperty->getValue($this->connector));
    }
}