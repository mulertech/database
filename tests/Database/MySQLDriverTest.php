<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database;

use MulerTech\Database\Database\MySQLDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySQLDriver::class)]
final class MySQLDriverTest extends TestCase
{
    private MySQLDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new MySQLDriver();
    }

    public function testGenerateDsnWithMinimalOptions(): void
    {
        $options = [];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=localhost;port=3306;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnWithHostAndPort(): void
    {
        $options = [
            'host' => 'database.example.com',
            'port' => 3307
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=database.example.com;port=3307;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnWithHostPortAndDatabase(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'testdb'
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=localhost;port=3306;dbname=testdb;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnWithCustomCharset(): void
    {
        $options = [
            'host' => 'localhost',
            'charset' => 'latin1'
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=localhost;port=3306;charset=latin1', $dsn);
    }

    public function testGenerateDsnWithUnixSocket(): void
    {
        $options = [
            'unix_socket' => '/tmp/mysql.sock'
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnWithUnixSocketAndDatabase(): void
    {
        $options = [
            'unix_socket' => '/var/run/mysqld/mysqld.sock',
            'dbname' => 'myapp'
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=myapp;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnUnixSocketIgnoresHostAndPort(): void
    {
        $options = [
            'unix_socket' => '/tmp/mysql.sock',
            'host' => 'localhost',  // This should be ignored
            'port' => 3307          // This should be ignored
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:unix_socket=/tmp/mysql.sock;charset=utf8mb4', $dsn);
        $this->assertStringNotContainsString('host=', $dsn);
        $this->assertStringNotContainsString('port=', $dsn);
    }

    public function testGenerateDsnWithAllOptions(): void
    {
        $options = [
            'host' => 'db.example.com',
            'port' => 3308,
            'dbname' => 'production_db',
            'charset' => 'utf8'
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=db.example.com;port=3308;dbname=production_db;charset=utf8', $dsn);
    }

    public function testGenerateDsnWithStringPort(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => '3307'  // String port should work
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=localhost;port=3307;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnWithEmptyStringValues(): void
    {
        $options = [
            'host' => '',
            'port' => '',
            'dbname' => '',
            'charset' => ''
        ];

        $dsn = $this->driver->generateDsn($options);

        // The driver uses empty values as-is, only falling back to defaults when keys are missing
        $this->assertEquals('mysql:host=;port=;dbname=;charset=', $dsn);
    }

    public function testGenerateDsnWithNullValues(): void
    {
        $options = [
            'host' => null,
            'port' => null,
            'dbname' => null,
            'charset' => null
        ];

        $dsn = $this->driver->generateDsn($options);

        // Null values should use defaults
        $this->assertEquals('mysql:host=localhost;port=3306;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnIgnoresUnknownOptions(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 3306,
            'unknown_option' => 'should_be_ignored',
            'another_unknown' => 123
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=localhost;port=3306;charset=utf8mb4', $dsn);
        $this->assertStringNotContainsString('unknown_option', $dsn);
        $this->assertStringNotContainsString('another_unknown', $dsn);
    }

    public function testGenerateDsnOrderOfParameters(): void
    {
        $options = [
            'charset' => 'utf8',
            'dbname' => 'testdb',
            'port' => 3307,
            'host' => 'localhost'
        ];

        $dsn = $this->driver->generateDsn($options);

        // Should maintain consistent order regardless of input order
        $this->assertEquals('mysql:host=localhost;port=3307;dbname=testdb;charset=utf8', $dsn);
    }

    public function testGenerateDsnWithZeroPort(): void
    {
        $options = [
            'host' => 'localhost',
            'port' => 0
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertEquals('mysql:host=localhost;port=0;charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnDefaultValues(): void
    {
        // Test that default values are used when keys are missing
        $options = [];

        $dsn = $this->driver->generateDsn($options);

        $this->assertStringContainsString('host=localhost', $dsn);
        $this->assertStringContainsString('port=3306', $dsn);
        $this->assertStringContainsString('charset=utf8mb4', $dsn);
    }

    public function testGenerateDsnWithSpecialCharactersInDatabase(): void
    {
        $options = [
            'dbname' => 'test-db_123'
        ];

        $dsn = $this->driver->generateDsn($options);

        $this->assertStringContainsString('dbname=test-db_123', $dsn);
    }

    public function testGenerateDsnAlwaysStartsWithMysql(): void
    {
        $options = ['host' => 'anywhere'];

        $dsn = $this->driver->generateDsn($options);

        $this->assertStringStartsWith('mysql:', $dsn);
    }

    public function testGenerateDsnAlwaysEndsWithCharset(): void
    {
        $options = ['host' => 'localhost'];

        $dsn = $this->driver->generateDsn($options);

        $this->assertStringEndsWith('charset=utf8mb4', $dsn);
    }
}