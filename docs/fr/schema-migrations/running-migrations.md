# Exécution et Rollback des Migrations

Ce guide explique comment exécuter, annuler et gérer l'état des migrations dans MulerTech Database.

## Table des Matières
- [Exécution des migrations](#exécution-des-migrations)
- [Rollback (annulation)](#rollback-annulation)
- [Gestion de l'état](#gestion-de-létat)
- [Stratégies de déploiement](#stratégies-de-déploiement)
- [Résolution des problèmes](#résolution-des-problèmes)

## Exécution des migrations

### Migration Manager

```php
use MulerTech\Database\Schema\MigrationManager;
use MulerTech\Database\Schema\MigrationExecutor;

$migrationManager = new MigrationManager($connection);
$executor = new MigrationExecutor($connection);

// Exécuter toutes les migrations en attente
$pendingMigrations = $migrationManager->getPendingMigrations();
foreach ($pendingMigrations as $migration) {
    $executor->execute($migration, 'up');
}
```

### Exécution avec validation

```php
try {
    $migrationManager->migrate();
    echo "Migrations exécutées avec succès\n";
} catch (MigrationException $e) {
    echo "Erreur lors de l'exécution des migrations: " . $e->getMessage() . "\n";
    
    // Rollback automatique si configuré
    if ($migrationManager->hasAutoRollback()) {
        $migrationManager->rollbackLastBatch();
    }
}
```

### Exécution par lots (batches)

```php
// Exécuter les migrations par groupes
$batchSize = 5;
$batches = $migrationManager->getBatchesToExecute($batchSize);

foreach ($batches as $batchNumber => $migrations) {
    echo "Exécution du lot #{$batchNumber}\n";
    
    $migrationManager->executeBatch($migrations);
    
    // Vérification après chaque lot
    if (!$migrationManager->validateBatchExecution($batchNumber)) {
        throw new Exception("Échec de validation du lot #{$batchNumber}");
    }
}
```

### Exécution jusqu'à une migration spécifique

```php
// Exécuter jusqu'à une migration donnée
$targetMigration = 'Migration20241213120000_AddUserProfiles';
$migrationManager->migrateTo($targetMigration);

// Ou exécuter seulement une migration spécifique
$migration = $migrationManager->getMigration($targetMigration);
$executor->execute($migration, 'up');
```

## Rollback (annulation)

### Rollback simple

```php
// Annuler la dernière migration
$migrationManager->rollbackLast();

// Annuler les N dernières migrations
$migrationManager->rollback(3);

// Annuler jusqu'à une migration spécifique
$migrationManager->rollbackTo('Migration20241210100000_InitialSchema');
```

### Rollback par lot

```php
// Annuler le dernier lot de migrations
$migrationManager->rollbackLastBatch();

// Annuler plusieurs lots
$migrationManager->rollbackBatches(2);

// Obtenir les informations sur les lots
$batches = $migrationManager->getExecutedBatches();
foreach ($batches as $batch) {
    echo "Lot #{$batch['batch']}: {$batch['count']} migrations\n";
}
```

### Rollback avec confirmation

```php
$migrationToRollback = $migrationManager->getLastExecutedMigration();

echo "Voulez-vous vraiment annuler la migration '{$migrationToRollback->getName()}' ?\n";
echo "Description: {$migrationToRollback->getDescription()}\n";

$confirmation = readline("Tapez 'yes' pour confirmer: ");

if ($confirmation === 'yes') {
    try {
        $migrationManager->rollbackLast();
        echo "Migration annulée avec succès\n";
    } catch (MigrationException $e) {
        echo "Erreur lors de l'annulation: " . $e->getMessage() . "\n";
    }
}
```

## Gestion de l'état

### Table des migrations

MulerTech Database maintient automatiquement une table `mt_migrations` :

```sql
CREATE TABLE mt_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    execution_time INT DEFAULT 0,
    checksum VARCHAR(64) NOT NULL
);
```

### Vérification de l'état

```php
// Vérifier l'état des migrations
$status = $migrationManager->getStatus();

echo "Migrations exécutées: {$status['executed']}\n";
echo "Migrations en attente: {$status['pending']}\n";
echo "Dernier lot: {$status['lastBatch']}\n";

// Lister les migrations en attente
$pending = $migrationManager->getPendingMigrations();
foreach ($pending as $migration) {
    echo "- {$migration->getName()}: {$migration->getDescription()}\n";
}
```

### Validation de l'intégrité

```php
// Vérifier l'intégrité du schéma
$validator = new SchemaValidator($migrationManager);

$issues = $validator->validate();
if (!empty($issues)) {
    echo "Problèmes détectés:\n";
    foreach ($issues as $issue) {
        echo "- {$issue['type']}: {$issue['message']}\n";
    }
}

// Vérifier les checksums des migrations
$checksumIssues = $migrationManager->validateChecksums();
if (!empty($checksumIssues)) {
    echo "Migrations modifiées détectées:\n";
    foreach ($checksumIssues as $migration => $issue) {
        echo "- {$migration}: {$issue}\n";
    }
}
```

## Stratégies de déploiement

### Déploiement Blue-Green

```php
class BlueGreenMigrationStrategy
{
    private MigrationManager $migrationManager;
    private DatabaseSwitcher $dbSwitcher;

    public function deploy(array $newMigrations): void
    {
        // 1. Créer une copie de la base (Green)
        $greenDb = $this->dbSwitcher->createGreenEnvironment();
        
        // 2. Exécuter les migrations sur Green
        $this->migrationManager->setConnection($greenDb);
        $this->migrationManager->migrate();
        
        // 3. Valider l'état
        if (!$this->validateGreenEnvironment($greenDb)) {
            throw new Exception("Validation Green environnement échouée");
        }
        
        // 4. Basculer vers Green
        $this->dbSwitcher->switchToGreen();
        
        // 5. Supprimer l'ancien environnement (Blue)
        $this->dbSwitcher->cleanupBlueEnvironment();
    }
    
    private function validateGreenEnvironment($connection): bool
    {
        // Tests de validation...
        return true;
    }
}
```

### Déploiement avec rollback automatique

```php
class SafeMigrationDeployment
{
    public function deployWithSafety(array $migrations): void
    {
        $checkpoint = $this->migrationManager->createCheckpoint();
        
        try {
            // Exécuter les migrations
            $this->migrationManager->executeMigrations($migrations);
            
            // Tests de validation post-migration
            $this->runPostMigrationTests();
            
            // Si tout va bien, confirmer le checkpoint
            $this->migrationManager->confirmCheckpoint($checkpoint);
            
        } catch (Exception $e) {
            // En cas d'erreur, rollback automatique
            $this->migrationManager->rollbackToCheckpoint($checkpoint);
            throw new MigrationDeploymentException(
                "Déploiement échoué, rollback effectué: " . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    private function runPostMigrationTests(): void
    {
        // Tests critiques post-migration
        $this->testDatabaseConnectivity();
        $this->testCriticalQueries();
        $this->testDataIntegrity();
    }
}
```

### Migrations à chaud (Zero-downtime)

```php
class ZeroDowntimeMigration
{
    public function addColumnSafely(string $table, string $column, array $options): void
    {
        // Étape 1: Ajouter la colonne (nullable d'abord)
        $safeOptions = array_merge($options, ['notnull' => false]);
        $this->schema->getTable($table)->addColumn($column, $options['type'], $safeOptions);
        
        // Étape 2: Populer les données existantes (par batch)
        $this->populateColumnData($table, $column, $options['default'] ?? null);
        
        // Étape 3: Mettre à jour l'application pour utiliser la nouvelle colonne
        // (déploiement application)
        
        // Étape 4: Ajouter les contraintes (dans une migration ultérieure)
        if ($options['notnull'] ?? false) {
            $this->schema->getTable($table)->changeColumn($column, ['notnull' => true]);
        }
    }
    
    private function populateColumnData(string $table, string $column, $defaultValue): void
    {
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $updated = $this->connection->executeStatement(
                "UPDATE {$table} SET {$column} = ? WHERE {$column} IS NULL LIMIT ?",
                [$defaultValue, $batchSize]
            );
            
            $offset += $batchSize;
            
            // Pause pour éviter de surcharger la base
            usleep(100000); // 100ms
            
        } while ($updated > 0);
    }
}
```

## Résolution des problèmes

### Migrations bloquées

```php
// Débloquer une migration en échec
$migrationManager->markMigrationAsFailed('Migration20241213120000_ProblematicMigration');

// Réparer et re-exécuter
$migration = $migrationManager->getMigration('Migration20241213120000_ProblematicMigration');
$migrationManager->repairAndExecute($migration);
```

### Conflits de migrations

```php
// Détecter les conflits
$conflicts = $migrationManager->detectConflicts();
foreach ($conflicts as $conflict) {
    echo "Conflit détecté: {$conflict['description']}\n";
    echo "Migrations impliquées: " . implode(', ', $conflict['migrations']) . "\n";
}

// Résoudre manuellement
$resolver = new MigrationConflictResolver($migrationManager);
$resolver->resolveConflict($conflicts[0]);
```

### Récupération après panne

```php
class MigrationRecovery
{
    public function recoverFromCrash(): void
    {
        // Identifier les migrations partiellement exécutées
        $partialMigrations = $this->migrationManager->findPartialMigrations();
        
        foreach ($partialMigrations as $migration) {
            echo "Migration partiellement exécutée trouvée: {$migration->getName()}\n";
            
            // Vérifier l'état de la base
            $schemaState = $this->analyzeSchemaState($migration);
            
            if ($schemaState === 'partial') {
                // Rollback de la migration partielle
                $this->rollbackPartialMigration($migration);
            }
            
            // Marquer comme non-exécutée pour re-tentative
            $this->migrationManager->markAsNotExecuted($migration);
        }
        
        // Re-exécuter les migrations
        $this->migrationManager->migrate();
    }
    
    private function analyzeSchemaState(Migration $migration): string
    {
        // Analyser l'état actuel du schéma par rapport à la migration
        return 'partial'; // ou 'complete', 'rollback_needed'
    }
}
```

### Logs et monitoring

```php
// Configuration du logging
$migrationManager->setLogger(new MigrationLogger());

// Exécution avec logs détaillés
$migrationManager->migrate([
    'verbose' => true,
    'dry_run' => false,
    'log_level' => 'debug'
]);

// Monitoring des performances
$performanceMonitor = new MigrationPerformanceMonitor();
$migrationManager->addListener($performanceMonitor);
```

---

**Voir aussi :**
- [Création de migrations](creating-migrations.md)
- [Comparaison de schémas](schema-diff.md)
- [Commandes CLI](migration-commands.md)
