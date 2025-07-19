<?php

namespace MulerTech\Database\Tests\Migration\Command;

use Exception;
use MulerTech\Database\Schema\Migration\Command\MigrationRollbackCommand;
use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\MTerm\Core\Terminal;
use PHPUnit\Framework\TestCase;

class MigrationRollbackCommandTest extends TestCase
{
    private Terminal $terminal;
    private MigrationManager $migrationManager;
    private MigrationRollbackCommand $command;

    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->migrationManager = $this->createMock(MigrationManager::class);
        
        $this->command = new MigrationRollbackCommand(
            $this->terminal,
            $this->migrationManager
        );
    }

    public function testExecuteSuccessfulRollback(): void
    {
        $migration = $this->createMock(Migration::class);
        
        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->willReturn([
                '20230101000000' => $migration,
                '20230102000000' => $migration
            ]);
            
        $this->migrationManager->expects($this->exactly(2))
            ->method('isMigrationExecuted')
            ->willReturn(true);
            
        $this->migrationManager->expects($this->once())
            ->method('rollback')
            ->willReturn(true);
            
        $this->terminal->expects($this->exactly(3))
            ->method('writeLine');
            
        $this->terminal->expects($this->once())
            ->method('readChar')
            ->with('Do you want to rollback this migration? (y/n): ')
            ->willReturn('y');
            
        $result = $this->command->execute();
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteNoMigrations(): void
    {
        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->willReturn([]);
            
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteNoExecutedMigrations(): void
    {
        $migration = $this->createMock(Migration::class);

        $migration->method('getVersion')
            ->willReturn('202301010000');

        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->willReturn(['20230101000000' => $migration]);
            
        $this->migrationManager->expects($this->once())
            ->method('isMigrationExecuted')
            ->willReturn(false);
            
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteDryRun(): void
    {
        $migration = $this->createMock(Migration::class);
        
        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->willReturn(['202301010000' => $migration]);
            
        $this->migrationManager->expects($this->once())
            ->method('isMigrationExecuted')
            ->willReturn(true);
            
        $this->terminal->expects($this->exactly(3))
            ->method('writeLine');
            
        $result = $this->command->execute(['--dry-run']);
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteUserCancelled(): void
    {
        $migration = $this->createMock(Migration::class);
        
        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->willReturn(['20230101000000' => $migration]);
            
        $this->migrationManager->expects($this->once())
            ->method('isMigrationExecuted')
            ->willReturn(true);
            
        $this->terminal->expects($this->exactly(3))
            ->method('writeLine');
            
        $this->terminal->expects($this->once())
            ->method('readChar')
            ->with('Do you want to rollback this migration? (y/n): ')
            ->willReturn('n');
            
        $result = $this->command->execute();
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteRollbackNoChanges(): void
    {
        $migration = $this->createMock(Migration::class);
        
        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->willReturn(['20230101000000' => $migration]);
            
        $this->migrationManager->expects($this->once())
            ->method('isMigrationExecuted')
            ->willReturn(true);
            
        $this->migrationManager->expects($this->once())
            ->method('rollback')
            ->willReturn(false);
            
        $this->terminal->expects($this->exactly(3))
            ->method('writeLine');
            
        $this->terminal->expects($this->once())
            ->method('readChar')
            ->willReturn('y');
            
        $result = $this->command->execute();
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteWithError(): void
    {
        $this->migrationManager->expects($this->once())
            ->method('getMigrations')
            ->will($this->throwException(new Exception('Une erreur est survenue')));
            
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        $this->assertEquals(1, $result);
    }
}
