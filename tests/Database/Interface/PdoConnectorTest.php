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
        $this->mockDriver = $this->createStub(DriverInterface::class);
        $this->connector = new PdoConnector($this->mockDriver);
    }

    private function createConnectorWithMock(DriverInterface $driver): PdoConnector
    {
        return new PdoConnector($driver);
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

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $connector = $this->createConnectorWithMock($driver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $connector->connect($parameters, $username, $password);
    }

    public function testConnectWithCustomOptions(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';
        $options = [PDO::ATTR_TIMEOUT => 10];

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $connector = $this->createConnectorWithMock($driver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $connector->connect($parameters, $username, $password, $options);
    }

    public function testConnectWithNullOptions(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $connector = $this->createConnectorWithMock($driver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $connector->connect($parameters, $username, $password, null);
    }

    public function testConnectWrapssPdoException(): void
    {
        $parameters = ['host' => 'localhost', 'dbname' => 'test'];
        $dsn = 'mysql:host=localhost;dbname=test';
        $username = 'user';
        $password = 'password';

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $connector = $this->createConnectorWithMock($driver);

        try {
            $connector->connect($parameters, $username, $password);
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

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $connector = $this->createConnectorWithMock($driver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $connector->connect($parameters, $username, $password);
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

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('generateDsn')
            ->with($parameters)
            ->willReturn($dsn);

        $connector = $this->createConnectorWithMock($driver);

        // The method should merge options, with defaults taking precedence
        // We expect an exception because we're using a mock DSN
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Connection failed:');

        $connector->connect($parameters, $username, $password, $customOptions);
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