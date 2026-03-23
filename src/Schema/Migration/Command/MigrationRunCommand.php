<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration\Command;

use MulerTech\Database\Schema\Migration\MigrationManager;
use MulerTech\MTerm\Command\AbstractCommand;
use MulerTech\MTerm\Core\Terminal;

/**
 * Command to run pending migrations.
 *
 * @author Sébastien Muler
 */
class MigrationRunCommand extends AbstractCommand
{
    public function __construct(
        Terminal $terminal,
        private readonly MigrationManager $migrationManager,
    ) {
        parent::__construct($terminal);
        $this->name = 'migration:run';
        $this->description = 'Runs all pending migrations';
    }

    /**
     * Executes the command.
     *
     * @param array<int, string> $args Command arguments
     *
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

            $this->terminal->writeLine(count($pendingMigrations).' pending migration(s):', 'white');

            foreach ($pendingMigrations as $version => $migration) {
                $this->terminal->writeLine(' - '.$version, 'white');
            }

            // Option to run in dry-run mode
            if (!empty($args) && '--dry-run' === $args[0]) {
                $this->terminal->writeLine('Dry-run mode completed. Use without --dry-run to execute the migrations.', 'yellow');

                return 0;
            }

            // Ask for confirmation
            $confirmation = $this->terminal->readChar('Do you want to run these migrations? (y/n): ');

            if ('y' !== strtolower($confirmation)) {
                $this->terminal->writeLine('Execution aborted.', 'yellow');

                return 0;
            }

            // Execute migrations
            $executed = $this->migrationManager->migrate();

            $this->terminal->writeLine($executed.' migration(s) successfully executed.', 'green');

            return 0;
        } catch (\Exception $e) {
            $this->terminal->writeLine('Error: '.$e->getMessage(), 'red');

            return 1;
        }
    }
}
