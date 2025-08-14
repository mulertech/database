<?php

namespace MulerTech\Database\Tests\Database;

use MulerTech\Database\Database\SQLiteDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SQLiteDriver::class)]
final class SQLiteDriverTest extends TestCase
{
    private SQLiteDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new SQLiteDriver();
    }

    public function testGenerateDsnForInMemoryDatabase(): void
    {
        $dsn = $this->driver->generateDsn([]);
        $this->assertEquals('sqlite::memory:', $dsn);
    }

    public function testGenerateDsnForInMemoryDatabaseExplicit(): void
    {
        $dsn = $this->driver->generateDsn(['path' => ':memory:']);
        $this->assertEquals('sqlite::memory:', $dsn);
    }

    public function testGenerateDsnForFileDatabase(): void
    {
        $dsn = $this->driver->generateDsn(['path' => '/tmp/test.db']);
        $this->assertEquals('sqlite:/tmp/test.db', $dsn);
    }

    public function testGenerateDsnForRelativeFileDatabase(): void
    {
        $dsn = $this->driver->generateDsn(['path' => 'database.sqlite']);
        $this->assertEquals('sqlite:database.sqlite', $dsn);
    }
}