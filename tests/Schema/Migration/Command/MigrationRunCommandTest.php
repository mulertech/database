<?php

namespace MulerTech\Database\Tests\Schema\Migration\Command;

use Exception;
use MulerTech\Database\Schema\Migration\Command\MigrationRunCommand;
use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\MTerm\Core\Terminal;
use PHPUnit\Framework\TestCase;

class MigrationRunCommandTest extends TestCase
{
    private Terminal $terminal;
    private MigrationManager $migrationManager;
    private MigrationRunCommand $command;

    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->migrationManager = $this->createMock(MigrationManager::class);
        
        $this->command = new MigrationRunCommand(
            $this->terminal,
            $this->migrationManager
        );
    }

    public function testExecuteSuccessfulMigration(): void
    {
        // Migration stubs
        $migration1 = $this->createStub(Migration::class);
        $migration2 = $this->createStub(Migration::class);
        
        $pendingMigrations = [
            '20230101000000' => $migration1,
            '20230102000000' => $migration2
        ];
        
        // Configure the MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn($pendingMigrations);
            
        $this->migrationManager->expects($this->once())
            ->method('migrate')
            ->willReturn(2); // 2 migrations executed
            
        // Configure the terminal expectations
        $this->terminal->expects($this->exactly(5))
            ->method('writeLine');
            
        $this->terminal->expects($this->once())
            ->method('readChar')
            ->with('Do you want to run these migrations? (y/n): ')
            ->willReturn('y');
            
        $result = $this->command->execute();
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteNoPendingMigrations(): void
    {
        // Configure the MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn([]);
            
        // Configure the terminal expectations
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteDryRun(): void
    {
        // Migration stub
        $migration = $this->createStub(Migration::class);
        
        $pendingMigrations = [
            '20230101000000' => $migration
        ];
        
        // Configure the MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn($pendingMigrations);
            
        // migrate() is not called in dry-run mode
        $this->migrationManager->expects($this->never())
            ->method('migrate');
            
        // Configure the terminal expectations
        $this->terminal->expects($this->atLeastOnce())
            ->method('writeLine');
            
        $result = $this->command->execute(['--dry-run']);
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteUserCancelled(): void
    {
        // Migration stub
        $migration = $this->createStub(Migration::class);
        
        $pendingMigrations = [
            '20230101000000' => $migration
        ];
        
        // Configure the MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn($pendingMigrations);
            
        // migrate() is not called when the user cancels
        $this->migrationManager->expects($this->never())
            ->method('migrate');
            
        // Configure the terminal expectations
        $this->terminal->expects($this->atLeastOnce())
            ->method('writeLine');
            
        $this->terminal->expects($this->once())
            ->method('readChar')
            ->with('Do you want to run these migrations? (y/n): ')
            ->willReturn('n');
            
        $result = $this->command->execute();
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteWithError(): void
    {
        // Configure the MigrationManager to throw an exception
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->will($this->throwException(new Exception('An error occurred')));
            
        // Configure the terminal expectations
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        
        $this->assertEquals(1, $result);
    }
}
