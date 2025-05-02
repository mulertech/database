<?php

namespace MulerTech\Database\Tests\Migration\Command;

use MulerTech\Database\Mapping\DbMapping;
use MulerTech\Database\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\ORM\EntityManager;
use MulerTech\Database\PhpInterface\PdoConnector;
use MulerTech\Database\PhpInterface\PdoMysql\Driver;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\MTerm\Core\Terminal;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class MigrationGenerateCommandTest extends TestCase
{
    private Terminal $terminal;
    private EntityManager $entityManager;
    private string $migrationsDirectory;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->terminal = $this->createMock(Terminal::class);
        $this->entityManager = new EntityManager(
            new PhpDatabaseManager(new PdoConnector(new Driver()), []),
            new DbMapping(
                dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Entity'
            )
        );
        $this->migrationsDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'migrations';
        
        $this->command = new MigrationGenerateCommand(
            $this->terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );
        
        if (!is_dir($this->migrationsDirectory)) {
            mkdir($this->migrationsDirectory, 0777, true);
        }
    }
    
    protected function tearDown(): void
    {
        if (is_dir($this->migrationsDirectory)) {
            $files = glob($this->migrationsDirectory . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->migrationsDirectory);
        }
    }

    public function testExecuteSuccessfulMigrationGeneration(): void
    {
        $migrationGenerateCommand = new MigrationGenerateCommand(
            $this->terminal,
            $this->entityManager,
            $this->migrationsDirectory
        );

        // Terminal messages :
        // Generating a migration from entity definitions...
        // Migration successfully generated: Migration202302151000.php
        $this->terminal->expects($this->exactly(2))
            ->method('writeLine');

        $this->assertEquals(0, $migrationGenerateCommand->execute(['202302151000']));
        $this->assertTrue(
            file_exists($this->migrationsDirectory . DIRECTORY_SEPARATOR . 'Migration202302151000.php'),
            'Migration file should be created'
        );
    }
}