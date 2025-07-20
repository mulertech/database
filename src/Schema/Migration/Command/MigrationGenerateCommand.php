<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration\Command;

use MulerTech\Database\Database\Interface\PhpDatabaseManager;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\MTerm\Command\AbstractCommand;
use MulerTech\MTerm\Core\Terminal;
use ReflectionException;
use RuntimeException;

/**
 * Class MigrationGenerateCommand
 *
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
     * @throws ReflectionException
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

            // Ensure dbname is a string
            $databaseName = $dbParameters['dbname'] ?? '';
            if (!is_string($databaseName)) {
                throw new RuntimeException('Database name must be a string');
            }

            $schemaComparer = new SchemaComparer($informationSchema, $dbMapping, $databaseName);
            $migrationGenerator = $this->createMigrationGenerator($schemaComparer, $this->migrationsDirectory);

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

    /**
     * Creates a MigrationGenerator instance
     *
     * @param SchemaComparer $schemaComparer
     * @param string $migrationsDirectory
     * @return MigrationGenerator
     */
    protected function createMigrationGenerator(SchemaComparer $schemaComparer, string $migrationsDirectory): MigrationGenerator
    {
        return new MigrationGenerator($schemaComparer, $migrationsDirectory);
    }
}
