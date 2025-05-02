<?php

namespace MulerTech\Database\Tests\Migration\Command;

use Exception;
use MulerTech\Database\Migration\Command\MigrationRunCommand;
use MulerTech\Database\Migration\Migration;
use MulerTech\Database\Migration\MigrationManager;
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
        // Créer des mocks de migrations
        $migration1 = $this->createMock(Migration::class);
        $migration2 = $this->createMock(Migration::class);
        
        $pendingMigrations = [
            '20230101000000' => $migration1,
            '20230102000000' => $migration2
        ];
        
        // Configurer le MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn($pendingMigrations);
            
        $this->migrationManager->expects($this->once())
            ->method('migrate')
            ->willReturn(2); // 2 migrations executed
            
        // Configurer les attentes pour le terminal
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
        // Configurer le MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn([]);
            
        // Configurer les attentes pour le terminal
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteDryRun(): void
    {
        // Créer un mock de migration
        $migration = $this->createMock(Migration::class);
        
        $pendingMigrations = [
            '20230101000000' => $migration
        ];
        
        // Configurer le MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn($pendingMigrations);
            
        // Pas d'appel à migrate() en mode dry-run
        $this->migrationManager->expects($this->never())
            ->method('migrate');
            
        // Configurer les attentes pour le terminal
        $this->terminal->expects($this->atLeastOnce())
            ->method('writeLine');
            
        $result = $this->command->execute(['--dry-run']);
        
        $this->assertEquals(0, $result);
    }
    
    public function testExecuteUserCancelled(): void
    {
        // Créer un mock de migration
        $migration = $this->createMock(Migration::class);
        
        $pendingMigrations = [
            '20230101000000' => $migration
        ];
        
        // Configurer le MigrationManager
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->willReturn($pendingMigrations);
            
        // Pas d'appel à migrate() quand l'utilisateur annule
        $this->migrationManager->expects($this->never())
            ->method('migrate');
            
        // Configurer les attentes pour le terminal
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
        // Configurer le MigrationManager pour lancer une exception
        $this->migrationManager->expects($this->once())
            ->method('getPendingMigrations')
            ->will($this->throwException(new Exception('Une erreur est survenue')));
            
        // Configurer les attentes pour le terminal
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');
            
        $result = $this->command->execute();
        
        $this->assertEquals(1, $result);
    }
}
