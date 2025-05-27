<?php

namespace MulerTech\Database\Migration\Command;

use MulerTech\Database\Migration\MigrationGenerator;
use MulerTech\Database\Migration\Schema\SchemaComparer;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Relational\Sql\InformationSchema;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\MTerm\Command\AbstractCommand;
use MulerTech\MTerm\Core\Terminal;
use RuntimeException;

/**
 * Command to generate a migration from entity definitions
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MigrationGenerateCommand extends AbstractCommand
{
    /**
     * @param Terminal $terminal
     * @param EntityManagerInterface $entityManager
     * @param string $migrationsDirectory
     */
    public function __construct(
        Terminal $terminal,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $migrationsDirectory
    ) {
        parent::__construct($terminal);
        $this->name = 'migration:generate';
        $this->description = 'Generates a new migration from entity definitions';
    }

    /**
     * Executes the command
     *
     * @param array<int, string> $args Command arguments
     * @return int Return code
     */
    public function execute(array $args = []): int
    {
        $this->terminal->writeLine('Generating a migration from entity definitions...', 'blue');

        try {
            $date = $args[0] ?? null;

            // Creating migration generator and dependencies
            $dbMapping = $this->entityManager->getDbMapping();
            $informationSchema = new InformationSchema($this->entityManager->getEmEngine());
            $dbParameters = PhpDatabaseManager::populateParameters();
            $schemaComparer = new SchemaComparer($informationSchema, $dbMapping, $dbParameters['dbname']);
            $migrationGenerator = new MigrationGenerator($schemaComparer, $this->migrationsDirectory);

            // Generating the migration
            $migrationFile = $migrationGenerator->generateMigration($date);

            if ($migrationFile === null) {
                $this->terminal->writeLine('No schema changes detected, no migration generated.', 'yellow');
                return 0;
            }

            $this->terminal->writeLine('Migration successfully generated: ' . basename($migrationFile), 'green');
            return 0;
        } catch (RuntimeException $e) {
            $this->terminal->writeLine('Error: ' . $e->getMessage(), 'red');
            return 1;
        }
    }
}
