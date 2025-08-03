<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database\Interface;

use MulerTech\Database\Database\Interface\DriverInterface;
use PHPUnit\Framework\TestCase;

class DriverInterfaceTest extends TestCase
{
    private DriverInterface $driver;

    protected function setUp(): void
    {
        $this->driver = new class implements DriverInterface {
            public function generateDsn(array $dsnOptions): string
            {
                $dsn = 'mysql:';
                $parts = [];
                
                if (isset($dsnOptions['host'])) {
                    $parts[] = 'host=' . $dsnOptions['host'];
                }
                
                if (isset($dsnOptions['port'])) {
                    $parts[] = 'port=' . $dsnOptions['port'];
                }
                
                if (isset($dsnOptions['dbname'])) {
                    $parts[] = 'dbname=' . $dsnOptions['dbname'];
                }
                
                if (isset($dsnOptions['unix_socket'])) {
                    $parts[] = 'unix_socket=' . $dsnOptions['unix_socket'];
                }
                
                if (isset($dsnOptions['charset'])) {
                    $parts[] = 'charset=' . $dsnOptions['charset'];
                }
                
                return $dsn . implode(';', $parts);
            }
        };
    }

    public function testGenerateDsnWithHostAndPort(): void
    {
        $dsnOptions = [
            'host' => 'localhost',
            'port' => 3306
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringStartsWith('mysql:', $result);
        $this->assertStringContainsString('host=localhost', $result);
        $this->assertStringContainsString('port=3306', $result);
    }

    public function testGenerateDsnWithDatabase(): void
    {
        $dsnOptions = [
            'host' => 'localhost',
            'dbname' => 'test_database'
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringContainsString('dbname=test_database', $result);
    }

    public function testGenerateDsnWithUnixSocket(): void
    {
        $dsnOptions = [
            'unix_socket' => '/var/run/mysqld/mysqld.sock'
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringContainsString('unix_socket=/var/run/mysqld/mysqld.sock', $result);
    }

    public function testGenerateDsnWithCharset(): void
    {
        $dsnOptions = [
            'host' => 'localhost',
            'charset' => 'utf8mb4'
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringContainsString('charset=utf8mb4', $result);
    }

    public function testGenerateDsnWithAllOptions(): void
    {
        $dsnOptions = [
            'host' => 'db-server',
            'port' => '3307',
            'dbname' => 'production_db',
            'charset' => 'utf8mb4'
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringStartsWith('mysql:', $result);
        $this->assertStringContainsString('host=db-server', $result);
        $this->assertStringContainsString('port=3307', $result);
        $this->assertStringContainsString('dbname=production_db', $result);
        $this->assertStringContainsString('charset=utf8mb4', $result);
    }

    public function testGenerateDsnWithEmptyOptions(): void
    {
        $result = $this->driver->generateDsn([]);
        
        $this->assertEquals('mysql:', $result);
    }

    public function testGenerateDsnReturnsString(): void
    {
        $result = $this->driver->generateDsn(['host' => 'test']);
        
        $this->assertIsString($result);
    }

    public function testGenerateDsnWithNumericPort(): void
    {
        $dsnOptions = [
            'host' => 'localhost',
            'port' => 3306
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringContainsString('port=3306', $result);
    }

    public function testGenerateDsnWithStringPort(): void
    {
        $dsnOptions = [
            'host' => 'localhost',
            'port' => '3306'
        ];
        
        $result = $this->driver->generateDsn($dsnOptions);
        
        $this->assertStringContainsString('port=3306', $result);
    }
}