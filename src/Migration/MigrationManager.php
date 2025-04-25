<?php

namespace MulerTech\Database\Migration;

use DateTime;
use Exception;
use MulerTech\Database\Migration\Entity\MigrationHistory;
use MulerTech\Database\ORM\EntityManagerInterface;
use MulerTech\Database\PhpInterface\PhpDatabaseManager;
use MulerTech\Database\Relational\Sql\InformationSchema;
use MulerTech\Database\Relational\Sql\QueryBuilder;
use MulerTech\Database\Relational\Sql\SqlOperations;
use PDO;
use RuntimeException;

/**
 * Migration manager for executing and tracking migrations
 * 
 * @package MulerTech\Database\Migration
 * @author Sébastien Muler
 */
class MigrationManager
{
    /**
     * @var Migration[] Registered migrations
     */
    private array $migrations = [];
    
    /**
     * @var string[] Executed migration versions
     */
    private array $executedMigrations = [];
    
    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->initializeMigrationTable();
        $this->loadExecutedMigrations();
    }
    
    /**
     * Ensure migration history table exists
     *
     * @return void
     */
    private function initializeMigrationTable(): void
    {
        // Create the migration history table if it doesn't exist
        // Using low-level approach to avoid circular dependencies
        $dbMapping = $this->entityManager->getDbMapping();
        $emEngine = $this->entityManager->getEmEngine();
        
        $tableName = $dbMapping->getTableName(MigrationHistory::class);
        if ($tableName === null) {
            throw new RuntimeException("Migration history table name not found in mapping.");
        }

        // Check if table exists in the database
        $informationSchema = new InformationSchema($emEngine);
        $dbParameters = PhpDatabaseManager::populateParameters();
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
     * Todo: use Migration to create the table
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
            $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
            $queryBuilder
                ->select('version')
                ->from('migration_history')
                ->orderBy('version');

            $results = $this->entityManager->getEmEngine()->getQueryBuilderListResult(
                $queryBuilder,
                MigrationHistory::class
            );

            if (!is_null($results)) {
                $this->executedMigrations = $results;
            }
        } catch (Exception $e) {
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
                "Migration with version {$version} is already registered. Each migration must have a unique version."
            );
        }
        
        $this->migrations[$version] = $migration;
        return $this;
    }
    
    /**
     * Register multiple migrations at once
     *
     * @param Migration[] $migrations
     * @return $this
     */
    public function registerMigrations(array $migrations): self
    {
        foreach ($migrations as $migration) {
            $this->registerMigration($migration);
        }
        return $this;
    }
    
    /**
     * Check if a migration has been executed
     *
     * @param string $version
     * @return bool
     */
    public function isMigrationExecuted(string $version): bool
    {
        return in_array($version, $this->executedMigrations, true);
    }
    
    /**
     * Get all migrations
     *
     * @return Migration[]
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
        $pendingMigrations = [];
        
        foreach ($this->getMigrations() as $version => $migration) {
            if (!$this->isMigrationExecuted($version)) {
                $pendingMigrations[$version] = $migration;
            }
        }
        
        return $pendingMigrations;
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
        $version = $migration->getVersion();
        
        if ($this->isMigrationExecuted($version)) {
            throw new RuntimeException("Migration {$version} has already been executed.");
        }
        
        $startTime = microtime(true);
        
        try {
            // Begin transaction
            $this->entityManager->getPdm()->beginTransaction();
            
            // Execute migration
            $migration->up();
            
            // Record migration in history
            $this->recordMigrationExecution($migration, microtime(true) - $startTime);
            
            // Commit transaction
            $this->entityManager->getPdm()->commit();
            
            // Update executed migrations cache
            $this->executedMigrations[] = $version;
        } catch (Exception $e) {
            // Rollback transaction
            $this->entityManager->getPdm()->rollBack();
            
            // Re-throw exception
            throw new RuntimeException("Migration {$version} failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Record migration execution in database
     *
     * @param Migration $migration
     * @param float $executionTime
     * @return void
     */
    private function recordMigrationExecution(Migration $migration, float $executionTime): void
    {
        $history = new MigrationHistory();
        $history->setVersion($migration->getVersion());
        $history->setExecutedAt(new DateTime()->format('Y-m-d H:i:s'));
        $history->setExecutionTime((int)($executionTime * 1000)); // Convert to milliseconds

        $this->entityManager->persist($history);
        $this->entityManager->flush();
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
            throw new RuntimeException("Migration {$lastVersion} is recorded as executed but cannot be found.");
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
            throw new RuntimeException("Migration rollback {$lastVersion} failed: " . $e->getMessage(), 0, $e);
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
        $queryBuilder = new QueryBuilder($this->entityManager->getEmEngine());
        $queryBuilder
            ->delete('migration_history')
            ->where(SqlOperations::equal('version', $queryBuilder->addNamedParameter($version)));
            
        $statement = $queryBuilder->getResult();
        $statement->execute();
    }
}
