<?php

declare(strict_types=1);

namespace MulerTech\Database\Schema\Migration;

use Exception;
use MulerTech\Database\Database\Interface\DatabaseParameterParser;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\Query\Builder\QueryBuilder;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Schema\Migration\Entity\MigrationHistory;
use ReflectionException;
use RuntimeException;

/**
 * Class MigrationManager
 *
 * Migration manager for executing and tracking migrations
 *
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class MigrationManager
{
    /**
     * @var array<string, Migration> Registered migrations eg. ['20231001-1230' => Migration]
     */
    private array $migrations = [];

    /**
     * @var array<int, string> Executed migration versions
     */
    private array $executedMigrations = [];

    /**
     * @param EntityManagerInterface $entityManager
     * @param class-string $migrationHistory
     * @throws ReflectionException
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $migrationHistory = MigrationHistory::class
    ) {
        $this->initializeMigrationTable();
        $this->loadExecutedMigrations();
    }

    /**
     * Ensure migration history table exists
     *
     * @return void
     * @throws ReflectionException
     */
    private function initializeMigrationTable(): void
    {
        // Create the migration history table if it doesn't exist
        // Using low-level approach to avoid circular dependencies
        $dbMapping = $this->entityManager->getDbMapping();
        $emEngine = $this->entityManager->getEmEngine();

        $tableName = $dbMapping->getTableName($this->migrationHistory);
        if ($tableName === null) {
            // If migration history is not in the main mapping, use default table name
            $tableName = 'migration_history';
        }

        // Check if table exists in the database
        $informationSchema = new InformationSchema($emEngine);
        $dbParameters = new DatabaseParameterParser()->parseParameters();
        if (!is_string($dbParameters['dbname'])) {
            throw new RuntimeException('Database name must be a string');
        }
        $tables = $informationSchema->getTables($dbParameters['dbname']);
        $tableExists = false;
        foreach ($tables as $table) {
            if ($table['TABLE_NAME'] === $tableName) {
                $tableExists = true;
                break;
            }
        }

        // Create table if it doesn't exist
        if (!$tableExists) {
            $this->createMigrationHistoryTable($tableName);
        }
    }

    /**
     * Create migration history table
     * @param string $tableName
     * @return void
     */
    private function createMigrationHistoryTable(string $tableName): void
    {
        $sql = "CREATE TABLE `$tableName` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `version` varchar(13) NOT NULL,
            `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `execution_time` int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `version` (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        $this->entityManager->getPdm()->exec($sql);
    }

    /**
     * Load executed migrations from database
     *
     * @return void
     */
    private function loadExecutedMigrations(): void
    {
        try {
            $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
                ->select('version')
                ->from('migration_history')
                ->orderBy('version');

            $results = $this->entityManager->getEmEngine()->getQueryBuilderListResult(
                $queryBuilder,
                $this->migrationHistory,
            );

            // Extract versions (string) from objects
            if (is_iterable($results)) {
                $versions = [];
                foreach ($results as $row) {
                    if (isset($row->version) && is_string($row->version)) {
                        $versions[] = $row->version;
                    }
                }
                $this->executedMigrations = $versions;
            } else {
                $this->executedMigrations = [];
            }
        } catch (Exception) {
            // Table might not exist yet
            $this->executedMigrations = [];
        }
    }

    /**
     * Register a migration with the manager
     *
     * @param Migration $migration
     * @return $this
     */
    public function registerMigration(Migration $migration): self
    {
        $version = $migration->getVersion();

        // Check for duplicate version
        if (isset($this->migrations[$version])) {
            throw new RuntimeException(
                "Migration with version $version is already registered. Each migration must have a unique version."
            );
        }

        $this->migrations[$version] = $migration;
        return $this;
    }

    /**
     * Register migrations from a directory
     *
     * @param string $directory Path to migrations directory
     * @return $this
     * @throws RuntimeException If directory does not exist
     */
    public function registerMigrations(string $directory): self
    {
        if (!is_dir($directory)) {
            throw new RuntimeException("Migration directory does not exist: $directory");
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . 'Migration*.php') ?: [];

        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);

            // Inclure le fichier s'il n'est pas déjà chargé
            if (!class_exists($className)) {
                require_once $file;
            }

            // Instancier la migration
            if (class_exists($className)) {
                $migration = new $className($this->entityManager);

                if ($migration instanceof Migration) {
                    $this->registerMigration($migration);
                }
            }
        }

        return $this;
    }

    /**
     * Check if a migration has been executed
     *
     * @param Migration $migration
     * @return bool
     */
    public function isMigrationExecuted(Migration $migration): bool
    {
        return in_array($migration->getVersion(), $this->executedMigrations, true);
    }

    /**
     * Get all migrations
     *
     * @return array<string, Migration>
     */
    public function getMigrations(): array
    {
        $migrations = $this->migrations;
        ksort($migrations); // Sort by version
        return $migrations;
    }

    /**
     * Get pending migrations (not yet executed)
     *
     * @return Migration[]
     */
    public function getPendingMigrations(): array
    {
        return array_filter($this->getMigrations(), function ($version) {
            return !$this->isMigrationExecuted($version);
        });
    }

    /**
     * Execute all pending migrations
     *
     * @return int Number of executed migrations
     */
    public function migrate(): int
    {
        $executed = 0;

        foreach ($this->getPendingMigrations() as $migration) {
            $this->executeMigration($migration);
            $executed++;
        }

        return $executed;
    }

    /**
     * Execute a specific migration
     *
     * @param Migration $migration
     * @return void
     */
    public function executeMigration(Migration $migration): void
    {
        if ($this->isMigrationExecuted($migration)) {
            throw new RuntimeException("Migration {$migration->getVersion()} has already been executed.");
        }

        $startTime = microtime(true);

        try {
            // Begin transaction
            $this->entityManager->getPdm()->beginTransaction();

            // Execute migration
            $migration->up();

            // Record migration in history
            $this->recordMigrationExecution($migration, microtime(true) - $startTime);

            // Commit transaction if there is a transaction in progress
            // The CREATE or DROP TABLE statements are not transactional
            if ($this->entityManager->getPdm()->inTransaction()) {
                $this->entityManager->getPdm()->commit();
            }

            // Update executed migrations cache
            $this->executedMigrations[] = $migration->getVersion();
        } catch (Exception $e) {
            // Rollback transaction if it's possible
            if ($this->entityManager->getPdm()->inTransaction()) {
                $this->entityManager->getPdm()->rollBack();
            }

            // Re-throw exception
            throw new RuntimeException("Migration {$migration->getVersion()} failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Record migration execution in database
     *
     * @param Migration $migration
     * @param float $executionTime
     * @return void
     * @throws ReflectionException
     */
    private function recordMigrationExecution(Migration $migration, float $executionTime): void
    {
        $tableName = $this->entityManager->getDbMapping()->getTableName($this->migrationHistory);

        if ($tableName === null) {
            // If migration history is not in the main mapping, use default table name
            $tableName = 'migration_history';
        }

        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine())
            ->insert($tableName)
            ->set('version', $migration->getVersion())
            ->set('executed_at', date('Y-m-d H:i:s'))
            ->set('execution_time', (int)($executionTime * 1000)); // Convert to milliseconds

        $queryBuilder->execute();
    }

    /**
     * Roll back the last executed migration
     *
     * @return bool Whether a migration was rolled back
     */
    public function rollback(): bool
    {
        if (empty($this->executedMigrations)) {
            return false;
        }

        $lastVersion = end($this->executedMigrations);

        // Find the migration
        $migration = $this->migrations[$lastVersion] ?? null;
        if ($migration === null) {
            throw new RuntimeException("Migration $lastVersion is recorded as executed but cannot be found.");
        }

        try {
            // Begin transaction
            $this->entityManager->getPdm()->beginTransaction();

            // Execute migration down
            $migration->down();

            // Remove from migration history
            $this->removeMigrationRecord($lastVersion);

            // Commit transaction
            $this->entityManager->getPdm()->commit();

            // Remove from executed migrations cache
            array_pop($this->executedMigrations);

            return true;
        } catch (Exception $e) {
            // Rollback transaction
            $this->entityManager->getPdm()->rollBack();

            // Re-throw exception
            throw new RuntimeException("Migration rollback $lastVersion failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Remove migration record from database
     *
     * @param string $version
     * @return void
     */
    protected function removeMigrationRecord(string $version): void
    {
        $deleteBuilder = new QueryBuilder($this->entityManager->getEmEngine())->delete('migration_history');
        $deleteBuilder->where('version', $version);

        $statement = $deleteBuilder->getResult();
        $statement->execute();
    }
}
