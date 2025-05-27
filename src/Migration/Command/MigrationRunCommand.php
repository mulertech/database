<?php

namespace MulerTech\Database\Migration\Command;

use Exception;
use MulerTech\Database\Migration\MigrationManager;
use MulerTech\MTerm\Command\AbstractCommand;
use MulerTech\MTerm\Core\Terminal;

/**
 * Command to run pending migrations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MigrationRunCommand extends AbstractCommand
{
    /**
     * @param Terminal $terminal
     * @param MigrationManager $migrationManager
     */
    public function __construct(
        Terminal $terminal,
        private readonly MigrationManager $migrationManager
    ) {
        parent::__construct($terminal);
        $this->name = 'migration:run';
        $this->description = 'Runs all pending migrations';
    }

    /**
     * Executes the command
     *
     * @param array<int, string> $args Command arguments
     * @return int Return code
     */
    public function execute(array $args = []): int
    {
        $this->terminal->writeLine('Running pending migrations...', 'blue');

        try {
            $pendingMigrations = $this->migrationManager->getPendingMigrations();

            if (empty($pendingMigrations)) {
                $this->terminal->writeLine('No pending migrations.', 'yellow');
                return 0;
            }

            $this->terminal->writeLine(count($pendingMigrations) . ' pending migration(s):', 'white');

            foreach ($pendingMigrations as $version => $migration) {
                $this->terminal->writeLine(' - ' . $version, 'white');
            }

            // Option to run in dry-run mode
            if (!empty($args) && $args[0] === '--dry-run') {
                $this->terminal->writeLine('Dry-run mode completed. Use without --dry-run to execute the migrations.', 'yellow');
                return 0;
            }

            // Ask for confirmation
            $confirmation = $this->terminal->readChar('Do you want to run these migrations? (y/n): ');

            if (strtolower($confirmation) !== 'y') {
                $this->terminal->writeLine('Execution aborted.', 'yellow');
                return 0;
            }

            // Execute migrations
            $executed = $this->migrationManager->migrate();

            $this->terminal->writeLine($executed . ' migration(s) successfully executed.', 'green');
            return 0;
        } catch (Exception $e) {
            $this->terminal->writeLine('Error: ' . $e->getMessage(), 'red');
            return 1;
        }
    }
}
