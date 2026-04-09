<?php

declare(strict_types=1);

namespace MulerTech\Database\Tests\Database;

use MulerTech\Database\Database\DriverFactory;
use MulerTech\Database\Database\SQLiteDriver;
use PHPUnit\Framework\TestCase;

class DriverFactoryTest extends TestCase
{
    public function testCreateSqliteDriver(): void
    {
        $driver = DriverFactory::create('sqlite');
        $this->assertInstanceOf(SQLiteDriver::class, $driver);
    }

    public function testCreateSqliteDriverCaseInsensitive(): void
    {
        $driver = DriverFactory::create('SQLite');
        $this->assertInstanceOf(SQLiteDriver::class, $driver);
    }
}
