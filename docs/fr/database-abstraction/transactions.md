# Gestion des Transactions

## Table des Matières
- [Concepts Fondamentaux](#concepts-fondamentaux)
- [Transaction Manager](#transaction-manager)
- [Niveaux d'Isolation](#niveaux-disolation)
- [Transactions Imbriquées](#transactions-imbriquées)
- [Transactions Distribuées](#transactions-distribuées)
- [Gestion des Deadlocks](#gestion-des-deadlocks)
- [Patterns Transactionnels](#patterns-transactionnels)
- [Monitoring et Debug](#monitoring-et-debug)

## Concepts Fondamentaux

### ACID Properties

```php
<?php
declare(strict_types=1);

namespace MulerTech\Database\Transaction;

use MulerTech\Database\Database\Interface\DatabaseDriverInterface;

/**
 * Transaction Manager avec support ACID complet
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TransactionManager
{
    private DatabaseDriverInterface $driver;
    private int $transactionLevel = 0;
    private array $savepoints = [];
    private ?string $isolationLevel = null;
    private bool $readOnly = false;

    /**
     * @var array<callable>
     */
    private array $onCommitCallbacks = [];

    /**
     * @var array<callable>
     */
    private array $onRollbackCallbacks = [];

    public function __construct(DatabaseDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Démarrer une nouvelle transaction
     */
    public function begin(string $isolationLevel = null, bool $readOnly = false): void
    {
        if ($this->transactionLevel === 0) {
            if ($isolationLevel) {
                $this->setIsolationLevel($isolationLevel);
            }

            if ($readOnly) {
                $this->setReadOnly(true);
            }

            $this->driver->beginTransaction();
            $this->isolationLevel = $isolationLevel;
            $this->readOnly = $readOnly;
        } else {
            // Transaction imbriquée - utiliser un savepoint
            $savepointName = $this->createSavepoint();
            $this->savepoints[] = $savepointName;
        }

        $this->transactionLevel++;
    }

    /**
     * Valider la transaction courante
     */
    public function commit(): void
    {
        if ($this->transactionLevel === 0) {
            throw new TransactionException('No active transaction to commit');
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            // Transaction principale - commit réel
            try {
                $this->driver->commit();
                $this->executeCallbacks($this->onCommitCallbacks);
                $this->cleanup();
            } catch (\Exception $e) {
                $this->cleanup();
                throw new TransactionException('Transaction commit failed: ' . $e->getMessage(), 0, $e);
            }
        } else {
            // Transaction imbriquée - libérer le savepoint
            $savepointName = array_pop($this->savepoints);
            $this->releaseSavepoint($savepointName);
        }
    }

    /**
     * Annuler la transaction courante
     */
    public function rollback(): void
    {
        if ($this->transactionLevel === 0) {
            throw new TransactionException('No active transaction to rollback');
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            // Transaction principale - rollback complet
            try {
                $this->driver->rollback();
                $this->executeCallbacks($this->onRollbackCallbacks);
                $this->cleanup();
            } catch (\Exception $e) {
                $this->cleanup();
                throw new TransactionException('Transaction rollback failed: ' . $e->getMessage(), 0, $e);
            }
        } else {
            // Transaction imbriquée - rollback au savepoint
            $savepointName = array_pop($this->savepoints);
            $this->rollbackToSavepoint($savepointName);
        }
    }

    /**
     * Exécuter une opération dans une transaction
     * 
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation, string $isolationLevel = null): mixed
    {
        $this->begin($isolationLevel);

        try {
            $result = $operation();
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function isInTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function getTransactionLevel(): int
    {
        return $this->transactionLevel;
    }

    /**
     * Ajouter un callback à exécuter au commit
     */
    public function onCommit(callable $callback): void
    {
        $this->onCommitCallbacks[] = $callback;
    }

    /**
     * Ajouter un callback à exécuter au rollback
     */
    public function onRollback(callable $callback): void
    {
        $this->onRollbackCallbacks[] = $callback;
    }

    private function setIsolationLevel(string $level): void
    {
        $validLevels = [
            'READ UNCOMMITTED',
            'READ COMMITTED', 
            'REPEATABLE READ',
            'SERIALIZABLE'
        ];

        if (!in_array($level, $validLevels)) {
            throw new \InvalidArgumentException("Invalid isolation level: {$level}");
        }

        $sql = "SET TRANSACTION ISOLATION LEVEL {$level}";
        $this->driver->executeStatement($sql);
    }

    private function setReadOnly(bool $readOnly): void
    {
        $mode = $readOnly ? 'READ ONLY' : 'READ WRITE';
        $sql = "SET TRANSACTION {$mode}";
        $this->driver->executeStatement($sql);
    }

    private function createSavepoint(): string
    {
        $savepointName = 'sp_' . $this->transactionLevel . '_' . uniqid();
        $sql = "SAVEPOINT {$savepointName}";
        $this->driver->executeStatement($sql);
        return $savepointName;
    }

    private function releaseSavepoint(string $savepointName): void
    {
        $sql = "RELEASE SAVEPOINT {$savepointName}";
        $this->driver->executeStatement($sql);
    }

    private function rollbackToSavepoint(string $savepointName): void
    {
        $sql = "ROLLBACK TO SAVEPOINT {$savepointName}";
        $this->driver->executeStatement($sql);
    }

    /**
     * @param array<callable> $callbacks
     */
    private function executeCallbacks(array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            try {
                $callback();
            } catch (\Exception $e) {
                // Log les erreurs de callback mais ne pas faire échouer la transaction
                error_log("Transaction callback failed: " . $e->getMessage());
            }
        }
    }

    private function cleanup(): void
    {
        $this->savepoints = [];
        $this->onCommitCallbacks = [];
        $this->onRollbackCallbacks = [];
        $this->isolationLevel = null;
        $this->readOnly = false;
    }
}
```

## Niveaux d'Isolation

### Isolation Level Manager

```php
<?php
declare(strict_types=1);

namespace MulerTech\Database\Transaction;

/**
 * Gestionnaire des niveaux d'isolation avec détection automatique
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class IsolationLevelManager
{
    private DatabaseDriverInterface $driver;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $isolationLevels = [
        'READ UNCOMMITTED' => [
            'dirty_reads' => true,
            'non_repeatable_reads' => true,
            'phantom_reads' => true,
            'performance' => 'highest',
            'consistency' => 'lowest'
        ],
        'READ COMMITTED' => [
            'dirty_reads' => false,
            'non_repeatable_reads' => true,
            'phantom_reads' => true,
            'performance' => 'high',
            'consistency' => 'medium'
        ],
        'REPEATABLE READ' => [
            'dirty_reads' => false,
            'non_repeatable_reads' => false,
            'phantom_reads' => true,
            'performance' => 'medium',
            'consistency' => 'high'
        ],
        'SERIALIZABLE' => [
            'dirty_reads' => false,
            'non_repeatable_reads' => false,
            'phantom_reads' => false,
            'performance' => 'lowest',
            'consistency' => 'highest'
        ]
    ];

    public function __construct(DatabaseDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public function getCurrentIsolationLevel(): string
    {
        $driverName = $this->driver->getDriverName();

        $sql = match ($driverName) {
            'mysql' => 'SELECT @@transaction_isolation',
            'postgresql' => 'SHOW transaction_isolation',
            'sqlite' => 'PRAGMA read_uncommitted', // SQLite a une approche différente
            default => throw new \RuntimeException("Unsupported driver for isolation level detection: {$driverName}")
        };

        $result = $this->driver->fetchOne($sql);
        return $this->normalizeIsolationLevel($result, $driverName);
    }

    public function setIsolationLevel(string $level): void
    {
        if (!isset($this->isolationLevels[$level])) {
            throw new \InvalidArgumentException("Invalid isolation level: {$level}");
        }

        $driverName = $this->driver->getDriverName();

        $sql = match ($driverName) {
            'mysql', 'postgresql' => "SET TRANSACTION ISOLATION LEVEL {$level}",
            'sqlite' => $this->getSQLiteIsolationCommand($level),
            default => throw new \RuntimeException("Unsupported driver: {$driverName}")
        };

        $this->driver->executeStatement($sql);
    }

    /**
     * Recommander un niveau d'isolation basé sur le type d'opération
     */
    public function recommendIsolationLevel(string $operationType): string
    {
        return match ($operationType) {
            'read_only_report' => 'READ COMMITTED',
            'data_entry' => 'READ COMMITTED',
            'financial_transaction' => 'SERIALIZABLE',
            'bulk_operations' => 'READ UNCOMMITTED',
            'analytics' => 'READ COMMITTED',
            'critical_update' => 'REPEATABLE READ',
            default => 'READ COMMITTED'
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getIsolationLevelInfo(string $level): array
    {
        return $this->isolationLevels[$level] ?? [];
    }

    /**
     * Tester les phénomènes de lecture à un niveau d'isolation donné
     * 
     * @return array<string, bool>
     */
    public function testIsolationPhenomena(string $level): array
    {
        $originalLevel = $this->getCurrentIsolationLevel();
        $this->setIsolationLevel($level);

        $results = [
            'dirty_reads' => $this->testDirtyReads(),
            'non_repeatable_reads' => $this->testNonRepeatableReads(),
            'phantom_reads' => $this->testPhantomReads()
        ];

        $this->setIsolationLevel($originalLevel);
        return $results;
    }

    private function normalizeIsolationLevel(string $result, string $driverName): string
    {
        return match ($driverName) {
            'mysql' => str_replace('-', ' ', strtoupper($result)),
            'postgresql' => strtoupper($result),
            'sqlite' => $result === '1' ? 'READ UNCOMMITTED' : 'SERIALIZABLE',
            default => strtoupper($result)
        };
    }

    private function getSQLiteIsolationCommand(string $level): string
    {
        return match ($level) {
            'READ UNCOMMITTED' => 'PRAGMA read_uncommitted = 1',
            'SERIALIZABLE' => 'PRAGMA read_uncommitted = 0',
            default => throw new \InvalidArgumentException("SQLite only supports READ UNCOMMITTED and SERIALIZABLE")
        };
    }

    private function testDirtyReads(): bool
    {
        // Implémentation simplifiée - nécessiterait une vraie base de test
        return false;
    }

    private function testNonRepeatableReads(): bool
    {
        // Implémentation simplifiée
        return false;
    }

    private function testPhantomReads(): bool
    {
        // Implémentation simplifiée
        return false;
    }
}
```

## Transactions Imbriquées

### Nested Transaction Handler

```php
<?php
declare(strict_types=1);

namespace MulerTech\Database\Transaction;

/**
 * Gestionnaire de transactions imbriquées avec savepoints
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class NestedTransactionHandler
{
    private DatabaseDriverInterface $driver;
    private int $savepointCounter = 0;
    
    /**
     * @var array<string>
     */
    private array $savepointStack = [];

    public function __construct(DatabaseDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Créer un nouveau savepoint
     */
    public function createSavepoint(string $name = null): string
    {
        $name = $name ?? 'sp_' . ++$this->savepointCounter;
        
        $sql = "SAVEPOINT {$name}";
        $this->driver->executeStatement($sql);
        
        $this->savepointStack[] = $name;
        return $name;
    }

    /**
     * Rollback vers un savepoint spécifique
     */
    public function rollbackToSavepoint(string $name): void
    {
        if (!in_array($name, $this->savepointStack)) {
            throw new TransactionException("Savepoint '{$name}' does not exist");
        }

        $sql = "ROLLBACK TO SAVEPOINT {$name}";
        $this->driver->executeStatement($sql);

        // Supprimer tous les savepoints créés après celui-ci
        $index = array_search($name, $this->savepointStack);
        $this->savepointStack = array_slice($this->savepointStack, 0, $index + 1);
    }

    /**
     * Libérer un savepoint
     */
    public function releaseSavepoint(string $name): void
    {
        if (!in_array($name, $this->savepointStack)) {
            throw new TransactionException("Savepoint '{$name}' does not exist");
        }

        $sql = "RELEASE SAVEPOINT {$name}";
        $this->driver->executeStatement($sql);

        $index = array_search($name, $this->savepointStack);
        unset($this->savepointStack[$index]);
        $this->savepointStack = array_values($this->savepointStack);
    }

    /**
     * Exécuter une opération avec un savepoint automatique
     * 
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function withSavepoint(callable $operation, string $name = null): mixed
    {
        $savepointName = $this->createSavepoint($name);

        try {
            $result = $operation();
            $this->releaseSavepoint($savepointName);
            return $result;
        } catch (\Exception $e) {
            $this->rollbackToSavepoint($savepointName);
            $this->releaseSavepoint($savepointName);
            throw $e;
        }
    }

    /**
     * @return array<string>
     */
    public function getActiveSavepoints(): array
    {
        return $this->savepointStack;
    }

    public function hasActiveSavepoints(): bool
    {
        return !empty($this->savepointStack);
    }

    /**
     * Nettoyer tous les savepoints
     */
    public function cleanup(): void
    {
        $this->savepointStack = [];
        $this->savepointCounter = 0;
    }
}
```

## Transactions Distribuées

### Distributed Transaction Coordinator

```php
<?php
declare(strict_types=1);

namespace MulerTech\Database\Transaction;

/**
 * Coordinateur pour transactions distribuées (2PC)
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DistributedTransactionCoordinator
{
    /**
     * @var array<string, DatabaseDriverInterface>
     */
    private array $participants = [];

    /**
     * @var array<string, string>
     */
    private array $transactionIds = [];

    private string $coordinatorId;

    public function __construct()
    {
        $this->coordinatorId = uniqid('dtx_', true);
    }

    /**
     * Ajouter un participant à la transaction distribuée
     */
    public function addParticipant(string $name, DatabaseDriverInterface $driver): void
    {
        $this->participants[$name] = $driver;
    }

    /**
     * Exécuter une transaction distribuée avec 2-Phase Commit
     * 
     * @param array<string, callable> $operations
     * @return array<string, mixed>
     */
    public function executeDistributed(array $operations): array
    {
        if (empty($this->participants)) {
            throw new TransactionException('No participants added to distributed transaction');
        }

        $globalTransactionId = $this->generateGlobalTransactionId();
        $results = [];

        // Phase 1: Préparer toutes les transactions
        $preparedParticipants = [];
        
        try {
            foreach ($this->participants as $name => $driver) {
                $transactionId = $this->beginDistributedTransaction($driver, $name, $globalTransactionId);
                $this->transactionIds[$name] = $transactionId;

                if (isset($operations[$name])) {
                    $results[$name] = $operations[$name]($driver);
                }

                $this->prepareTransaction($driver, $transactionId);
                $preparedParticipants[] = $name;
            }

            // Phase 2: Commit si tous sont prêts
            foreach ($preparedParticipants as $name) {
                $driver = $this->participants[$name];
                $transactionId = $this->transactionIds[$name];
                $this->commitPreparedTransaction($driver, $transactionId);
            }

            return $results;

        } catch (\Exception $e) {
            // Rollback de tous les participants préparés
            foreach ($preparedParticipants as $name) {
                try {
                    $driver = $this->participants[$name];
                    $transactionId = $this->transactionIds[$name];
                    $this->rollbackPreparedTransaction($driver, $transactionId);
                } catch (\Exception $rollbackException) {
                    error_log("Failed to rollback participant {$name}: " . $rollbackException->getMessage());
                }
            }

            throw new DistributedTransactionException(
                "Distributed transaction failed: " . $e->getMessage(),
                $globalTransactionId,
                $preparedParticipants,
                $e
            );
        } finally {
            $this->cleanup();
        }
    }

    private function generateGlobalTransactionId(): string
    {
        return $this->coordinatorId . '_' . time() . '_' . uniqid();
    }

    private function beginDistributedTransaction(DatabaseDriverInterface $driver, string $participantName, string $globalTxId): string
    {
        $localTxId = $participantName . '_' . uniqid();
        
        // Commencer la transaction locale
        $driver->beginTransaction();

        // Enregistrer dans le log de transaction distribuée (si supporté)
        try {
            $sql = "INSERT INTO distributed_transactions (global_tx_id, local_tx_id, participant, status, created_at) 
                    VALUES (?, ?, ?, 'ACTIVE', NOW())";
            $driver->executeStatement($sql, [$globalTxId, $localTxId, $participantName]);
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas - pas tous les drivers la supportent
        }

        return $localTxId;
    }

    private function prepareTransaction(DatabaseDriverInterface $driver, string $transactionId): void
    {
        // Marquer comme préparé
        try {
            $sql = "UPDATE distributed_transactions SET status = 'PREPARED' WHERE local_tx_id = ?";
            $driver->executeStatement($sql, [$transactionId]);
        } catch (\Exception $e) {
            // Log mais continuer
        }

        // Vérifier que la transaction est toujours valide
        if (!$driver->inTransaction()) {
            throw new TransactionException("Transaction {$transactionId} is no longer active");
        }
    }

    private function commitPreparedTransaction(DatabaseDriverInterface $driver, string $transactionId): void
    {
        try {
            $driver->commit();
            
            // Marquer comme commitée
            $sql = "UPDATE distributed_transactions SET status = 'COMMITTED', completed_at = NOW() WHERE local_tx_id = ?";
            $driver->executeStatement($sql, [$transactionId]);
        } catch (\Exception $e) {
            throw new TransactionException("Failed to commit prepared transaction {$transactionId}: " . $e->getMessage());
        }
    }

    private function rollbackPreparedTransaction(DatabaseDriverInterface $driver, string $transactionId): void
    {
        try {
            $driver->rollback();
            
            // Marquer comme annulée
            $sql = "UPDATE distributed_transactions SET status = 'ROLLED_BACK', completed_at = NOW() WHERE local_tx_id = ?";
            $driver->executeStatement($sql, [$transactionId]);
        } catch (\Exception $e) {
            error_log("Failed to rollback transaction {$transactionId}: " . $e->getMessage());
        }
    }

    private function cleanup(): void
    {
        $this->transactionIds = [];
    }

    /**
     * Récupérer des transactions en attente pour reprise après crash
     * 
     * @return array<array<string, mixed>>
     */
    public function recoverPendingTransactions(): array
    {
        $pendingTransactions = [];

        foreach ($this->participants as $name => $driver) {
            try {
                $sql = "SELECT * FROM distributed_transactions 
                        WHERE status IN ('ACTIVE', 'PREPARED') 
                        AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
                
                $pending = $driver->fetchAll($sql);
                $pendingTransactions[$name] = $pending;
            } catch (\Exception $e) {
                // Table n'existe pas ou autre erreur
                $pendingTransactions[$name] = [];
            }
        }

        return $pendingTransactions;
    }
}

/**
 * Exception spécifique aux transactions distribuées
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DistributedTransactionException extends TransactionException
{
    private string $globalTransactionId;
    
    /**
     * @var array<string>
     */
    private array $affectedParticipants;

    /**
     * @param array<string> $affectedParticipants
     */
    public function __construct(
        string $message,
        string $globalTransactionId,
        array $affectedParticipants = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->globalTransactionId = $globalTransactionId;
        $this->affectedParticipants = $affectedParticipants;
    }

    public function getGlobalTransactionId(): string
    {
        return $this->globalTransactionId;
    }

    /**
     * @return array<string>
     */
    public function getAffectedParticipants(): array
    {
        return $this->affectedParticipants;
    }
}
```

## Gestion des Deadlocks

### Deadlock Detector and Handler

```php
<?php
declare(strict_types=1);

namespace MulerTech\Database\Transaction;

/**
 * Détecteur et gestionnaire de deadlocks
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class DeadlockHandler
{
    private DatabaseDriverInterface $driver;
    private int $maxRetries;
    private int $baseDelayMs;

    /**
     * @var array<string>
     */
    private array $deadlockIndicators = [
        'Deadlock found when trying to get lock',
        'Lock wait timeout exceeded',
        'deadlock detected',
        'could not serialize access'
    ];

    public function __construct(DatabaseDriverInterface $driver, int $maxRetries = 3, int $baseDelayMs = 100)
    {
        $this->driver = $driver;
        $this->maxRetries = $maxRetries;
        $this->baseDelayMs = $baseDelayMs;
    }

    /**
     * Exécuter une opération avec retry automatique en cas de deadlock
     * 
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function executeWithDeadlockRetry(callable $operation): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                if (!$this->isDeadlock($e) || $attempt === $this->maxRetries) {
                    throw $e;
                }

                $this->handleDeadlock($attempt);
                $attempt++;
            }
        }

        throw $lastException;
    }

    /**
     * Exécuter une transaction avec protection contre les deadlocks
     * 
     * @template T
     * @param callable(): T $transactionOperation
     * @return T
     */
    public function transactionalWithDeadlockProtection(callable $transactionOperation): mixed
    {
        return $this->executeWithDeadlockRetry(function () use ($transactionOperation) {
            $this->driver->beginTransaction();
            
            try {
                $result = $transactionOperation();
                $this->driver->commit();
                return $result;
            } catch (\Exception $e) {
                $this->driver->rollback();
                throw $e;
            }
        });
    }

    private function isDeadlock(\Exception $exception): bool
    {
        $message = $exception->getMessage();
        
        foreach ($this->deadlockIndicators as $indicator) {
            if (stripos($message, $indicator) !== false) {
                return true;
            }
        }

        // Vérifier le code d'erreur spécifique au driver
        $code = $exception->getCode();
        return match ($this->driver->getDriverName()) {
            'mysql' => $code === 1213 || $code === 1205, // ER_LOCK_DEADLOCK, ER_LOCK_WAIT_TIMEOUT
            'postgresql' => $code === '40P01' || $code === '40001', // deadlock_detected, serialization_failure
            default => false
        };
    }

    private function handleDeadlock(int $attempt): void
    {
        // Calculer le délai avec backoff exponentiel et jitter
        $delay = $this->baseDelayMs * (2 ** $attempt);
        $jitter = random_int(0, (int)($delay * 0.1)); // 10% de jitter
        $totalDelay = $delay + $jitter;

        // Log du deadlock
        error_log("Deadlock detected on attempt {$attempt}, retrying in {$totalDelay}ms");

        // Attendre avant de réessayer
        usleep($totalDelay * 1000);
    }

    /**
     * Obtenir des informations sur les deadlocks actifs
     * 
     * @return array<array<string, mixed>>
     */
    public function getDeadlockInfo(): array
    {
        $driverName = $this->driver->getDriverName();

        return match ($driverName) {
            'mysql' => $this->getMySQLDeadlockInfo(),
            'postgresql' => $this->getPostgreSQLDeadlockInfo(),
            default => []
        };
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getMySQLDeadlockInfo(): array
    {
        try {
            $result = $this->driver->fetchAssociative('SHOW ENGINE INNODB STATUS');
            $status = $result['Status'] ?? '';
            
            // Parser les informations de deadlock depuis le status InnoDB
            if (preg_match('/LATEST DETECTED DEADLOCK\s*\n-+\s*(.*?)\n-+/s', $status, $matches)) {
                return [
                    [
                        'type' => 'latest_deadlock',
                        'info' => trim($matches[1]),
                        'timestamp' => new \DateTime()
                    ]
                ];
            }
        } catch (\Exception $e) {
            // Ignore les erreurs d'accès aux informations de deadlock
        }

        return [];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getPostgreSQLDeadlockInfo(): array
    {
        try {
            // PostgreSQL stocke les informations de deadlock dans les logs
            $result = $this->driver->fetchAll(
                "SELECT query, state, wait_event_type, wait_event 
                 FROM pg_stat_activity 
                 WHERE wait_event_type = 'Lock'"
            );

            return array_map(function ($row) {
                return [
                    'type' => 'waiting_transaction',
                    'query' => $row['query'],
                    'state' => $row['state'],
                    'wait_event' => $row['wait_event'],
                    'timestamp' => new \DateTime()
                ];
            }, $result);
        } catch (\Exception $e) {
            // Ignore les erreurs d'accès aux informations de deadlock
        }

        return [];
    }

    /**
     * Configurer le timeout de lock pour éviter les deadlocks
     */
    public function configureLockTimeout(int $timeoutSeconds): void
    {
        $driverName = $this->driver->getDriverName();

        try {
            match ($driverName) {
                'mysql' => $this->driver->executeStatement(
                    "SET SESSION innodb_lock_wait_timeout = ?", 
                    [$timeoutSeconds]
                ),
                'postgresql' => $this->driver->executeStatement(
                    "SET lock_timeout = ?", 
                    ["{$timeoutSeconds}s"]
                ),
                default => null
            };
        } catch (\Exception $e) {
            // Log mais ne pas faire échouer
            error_log("Failed to set lock timeout: " . $e->getMessage());
        }
    }
}
```

## Patterns Transactionnels

### Transaction Patterns Implementation

```php
<?php
declare(strict_types=1);

namespace MulerTech\Database\Transaction\Patterns;

/**
 * Implémentation des patterns transactionnels courants
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TransactionPatterns
{
    private TransactionManager $transactionManager;
    private DeadlockHandler $deadlockHandler;

    public function __construct(TransactionManager $transactionManager, DeadlockHandler $deadlockHandler)
    {
        $this->transactionManager = $transactionManager;
        $this->deadlockHandler = $deadlockHandler;
    }

    /**
     * Pattern Saga - Transaction distribuée avec compensation
     * 
     * @param array<array{operation: callable, compensation: callable}> $steps
     * @return array<mixed>
     */
    public function executeSaga(array $steps): array
    {
        $results = [];
        $completedSteps = [];

        try {
            foreach ($steps as $index => $step) {
                $result = $step['operation']();
                $results[$index] = $result;
                $completedSteps[] = $index;
            }

            return $results;
        } catch (\Exception $e) {
            // Exécuter les compensations dans l'ordre inverse
            foreach (array_reverse($completedSteps) as $stepIndex) {
                try {
                    $steps[$stepIndex]['compensation']($results[$stepIndex] ?? null);
                } catch (\Exception $compensationError) {
                    error_log("Compensation failed for step {$stepIndex}: " . $compensationError->getMessage());
                }
            }

            throw $e;
        }
    }

    /**
     * Pattern Unit of Work - Grouper les modifications
     * 
     * @param array<callable> $operations
     * @return array<mixed>
     */
    public function unitOfWork(array $operations): array
    {
        return $this->transactionManager->transactional(function () use ($operations) {
            $results = [];
            
            foreach ($operations as $index => $operation) {
                $results[$index] = $operation();
            }
            
            return $results;
        });
    }

    /**
     * Pattern Optimistic Locking - Gestion des versions
     */
    public function optimisticUpdate(string $table, int $id, array $data, int $expectedVersion): bool
    {
        return $this->deadlockHandler->executeWithDeadlockRetry(function () use ($table, $id, $data, $expectedVersion) {
            return $this->transactionManager->transactional(function () use ($table, $id, $data, $expectedVersion) {
                // Vérifier la version actuelle
                $sql = "SELECT version FROM {$table} WHERE id = ? FOR UPDATE";
                $currentVersion = $this->transactionManager->getDriver()->fetchOne($sql, [$id]);

                if ($currentVersion !== $expectedVersion) {
                    throw new OptimisticLockException(
                        "Entity has been modified. Expected version {$expectedVersion}, got {$currentVersion}"
                    );
                }

                // Mettre à jour avec nouvelle version
                $data['version'] = $expectedVersion + 1;
                $setParts = array_map(fn($key) => "{$key} = :{$key}", array_keys($data));
                $updateSql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE id = :id";
                
                $params = array_merge($data, ['id' => $id]);
                $this->transactionManager->getDriver()->executeStatement($updateSql, $params);

                return true;
            });
        });
    }

    /**
     * Pattern Pessimistic Locking - Verrouillage préventif
     * 
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function pessimisticLock(string $table, array $ids, callable $operation): mixed
    {
        return $this->transactionManager->transactional(function () use ($table, $ids, $operation) {
            // Verrouiller les enregistrements
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT id FROM {$table} WHERE id IN ({$placeholders}) FOR UPDATE";
            $this->transactionManager->getDriver()->fetchAll($sql, $ids);

            // Exécuter l'opération
            return $operation();
        });
    }

    /**
     * Pattern Command - Exécution différée avec rollback
     */
    public function executeCommands(array $commands): array
    {
        return $this->transactionManager->transactional(function () use ($commands) {
            $results = [];
            $executedCommands = [];

            foreach ($commands as $index => $command) {
                try {
                    $result = $command->execute();
                    $results[$index] = $result;
                    $executedCommands[] = $command;
                } catch (\Exception $e) {
                    // Annuler les commandes déjà exécutées
                    foreach (array_reverse($executedCommands) as $executedCommand) {
                        if (method_exists($executedCommand, 'undo')) {
                            $executedCommand->undo();
                        }
                    }
                    throw $e;
                }
            }

            return $results;
        });
    }

    /**
     * Pattern Retry with Exponential Backoff
     * 
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function retryWithBackoff(callable $operation, int $maxAttempts = 3, int $initialDelayMs = 100): mixed
    {
        $attempt = 0;
        $delay = $initialDelayMs;

        while ($attempt < $maxAttempts) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempt++;
                
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                // Attendre avant de réessayer
                usleep($delay * 1000);
                $delay *= 2; // Backoff exponentiel
            }
        }
    }

    /**
     * Pattern Circuit Breaker - Protection contre les échecs en cascade
     */
    public function circuitBreaker(callable $operation, string $circuitName = 'default'): mixed
    {
        static $circuits = [];

        if (!isset($circuits[$circuitName])) {
            $circuits[$circuitName] = [
                'failures' => 0,
                'last_failure' => null,
                'state' => 'CLOSED' // CLOSED, OPEN, HALF_OPEN
            ];
        }

        $circuit = &$circuits[$circuitName];

        // Vérifier l'état du circuit
        if ($circuit['state'] === 'OPEN') {
            $timeSinceLastFailure = time() - $circuit['last_failure'];
            if ($timeSinceLastFailure < 60) { // 1 minute de timeout
                throw new CircuitBreakerOpenException("Circuit breaker is OPEN for {$circuitName}");
            } else {
                $circuit['state'] = 'HALF_OPEN';
            }
        }

        try {
            $result = $operation();
            
            // Réinitialiser le circuit en cas de succès
            $circuit['failures'] = 0;
            $circuit['state'] = 'CLOSED';
            
            return $result;
        } catch (\Exception $e) {
            $circuit['failures']++;
            $circuit['last_failure'] = time();
            
            // Ouvrir le circuit après 5 échecs
            if ($circuit['failures'] >= 5) {
                $circuit['state'] = 'OPEN';
            }
            
            throw $e;
        }
    }
}

/**
 * Exception pour verrouillage optimiste
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class OptimisticLockException extends TransactionException
{
}

/**
 * Exception pour circuit breaker ouvert
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class CircuitBreakerOpenException extends \Exception
{
}

/**
 * Exception de base pour les transactions
 * 
 * @package MulerTech\Database
 * @author Sébastien Muler
 */
class TransactionException extends \Exception
{
}
```

---

**Navigation :**
- [← Gestion des Connexions](connections.md)
- [→ Tests Unitaires](../testing/unit-tests.md)
- [↑ Database Abstraction](../README.md)
