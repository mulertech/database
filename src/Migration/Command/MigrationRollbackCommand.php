<?php

declare(strict_types=1);

namespace MulerTech\Database\Migration\Command;

use Exception;
use MulerTech\Database\Migration\MigrationManager;
use MulerTech\MTerm\Command\AbstractCommand;
use MulerTech\MTerm\Core\Terminal;

/**
 * Command to rollback the last executed migration
 *
 * @package MulerTech\Database\Migration\Command
 * @author Sébastien Muler
 */
class MigrationRollbackCommand extends AbstractCommand
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
        $this->name = 'migration:rollback';
        $this->description = 'Rollbacks the last executed migration';
    }

    /**
     * Executes the command
     *
     * @param array<int, string> $args Command arguments
     * @return int Return code
     */
    public function execute(array $args = []): int
    {
        $this->terminal->writeLine('Rolling back the last migration...', 'blue');

        try {
            $migrations = $this->migrationManager->getMigrations();
            // Check if there are migrations to rollback
            if (empty($migrations)) {
                $this->terminal->writeLine('No migrations found.', 'yellow');
                return 0;
            }

            // Get executed migrations
            $executedMigrations = array_filter(
                $migrations,
                fn ($migration) => $this->migrationManager->isMigrationExecuted($migration),
            );

            if (empty($executedMigrations)) {
                $this->terminal->writeLine('No executed migrations to rollback.', 'yellow');
                return 0;
            }

            // Get the last executed migration
            end($executedMigrations);
            $lastVersion = key($executedMigrations);

            $this->terminal->writeLine('Last executed migration: ' . $lastVersion, 'white');

            // Option to run in dry-run mode
            if (!empty($args) && $args[0] === '--dry-run') {
                $this->terminal->writeLine('Dry-run mode completed. Use without --dry-run to rollback the migration.', 'yellow');
                return 0;
            }

            // Ask for confirmation
            $confirmation = $this->terminal->readChar('Do you want to rollback this migration? (y/n): ');

            if (strtolower($confirmation) !== 'y') {
                $this->terminal->writeLine('Rollback aborted.', 'yellow');
                return 0;
            }

            // Rollback the migration
            if ($this->migrationManager->rollback()) {
                $this->terminal->writeLine('Migration ' . $lastVersion . ' successfully rolled back.', 'green');
                return 0;
            }

            $this->terminal->writeLine('No migration has been rolled back.', 'yellow');
            return 0;
        } catch (Exception $e) {
            $this->terminal->writeLine('Error: ' . $e->getMessage(), 'red');
            return 1;
        }
    }
}
