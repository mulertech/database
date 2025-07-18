<?php

namespace MulerTech\Database\Tests\Migration\Entity;

use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use PHPUnit\Framework\TestCase;

class MigrationHistoryTest extends TestCase
{
    private MigrationHistory $migrationHistory;
    
    protected function setUp(): void
    {
        $this->migrationHistory = new MigrationHistory();
    }
    
    public function testIdGetterAndSetter(): void
    {
        $this->assertNull($this->migrationHistory->getId());
        
        $this->migrationHistory->setId(42);
        $this->assertEquals(42, $this->migrationHistory->getId());
        
        $this->migrationHistory->setId(null);
        $this->assertNull($this->migrationHistory->getId());
    }
    
    public function testVersionGetterAndSetter(): void
    {
        $version = '20230415-1200';
        $this->migrationHistory->setVersion($version);
        $this->assertEquals($version, $this->migrationHistory->getVersion());
    }
    
    public function testExecutedAtGetterAndSetter(): void
    {
        $date = '2023-04-15 12:00:00';
        $this->migrationHistory->setExecutedAt($date);
        $this->assertEquals($date, $this->migrationHistory->getExecutedAt());
    }
    
    public function testExecutionTimeGetterAndSetter(): void
    {
        $this->assertEquals(0, $this->migrationHistory->getExecutionTime());
        
        $executionTime = 123;
        $this->migrationHistory->setExecutionTime($executionTime);
        $this->assertEquals($executionTime, $this->migrationHistory->getExecutionTime());
    }
    
    public function testFluentInterface(): void
    {
        $result = $this->migrationHistory
            ->setId(1)
            ->setVersion('20230415-1200')
            ->setExecutedAt('2023-04-15 12:00:00')
            ->setExecutionTime(123);
        
        $this->assertSame($this->migrationHistory, $result);
    }
}
