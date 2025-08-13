# Migrations

Les migrations permettent de gérer l'évolution du schéma de base de données de manière contrôlée et reproductible.

## Vue d'ensemble

Une migration est un fichier PHP qui décrit les modifications à apporter au schéma de base de données. Chaque migration possède :
- Un timestamp unique pour l'ordre d'exécution (format `YYYYMMDDHHMM`)
- Une méthode `up()` pour appliquer les changements
- Une méthode `down()` pour annuler les changements

## Structure d'une migration

### Classe de base

Toutes les migrations héritent de `MulerTech\Database\Schema\Migration\Migration` :

```php
abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;
    
    public function getVersion(): string;
    public function createQueryBuilder(): QueryBuilder;
}
```

### Nommage

- Format : `Migration{YYYYMMDDHHMM}`
- Exemple : `Migration202412131200` (13 décembre 2024, 12h00)

### Exemple de migration complète

```php
<?php

declare(strict_types=1);

use MulerTech\Database\Schema\Migration\Migration;

/**
 * Add user_profiles table
 */
class Migration202412131200 extends Migration
{
    public function up(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        // Créer la table user_profiles
        $queryBuilder->raw("
            CREATE TABLE user_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                bio TEXT,
                avatar_url VARCHAR(255),
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY UNIQ_USER_PROFILE_USER_ID (user_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ")->execute();
    }

    public function down(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->raw("DROP TABLE user_profiles")->execute();
    }
}
```

## Création de migrations

### Génération automatique

MulerTech Database peut générer automatiquement des migrations en comparant le schéma actuel avec vos entités :

```php
use MulerTech\Database\Schema\Migration\MigrationGenerator;
use MulerTech\Database\Schema\Diff\SchemaComparer;

$generator = new MigrationGenerator($schemaComparer, $metadataRegistry, '/path/to/migrations');
$migrationFile = $generator->generateMigration();

if ($migrationFile) {
    echo "Migration créée : " . basename($migrationFile);
} else {
    echo "Aucune différence détectée";
}
```

### Types d'opérations

#### Création de table

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    $queryBuilder->raw("
        CREATE TABLE products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_PRODUCT_NAME (name)
        )
    ")->execute();
}
```

#### Modification de table

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Ajouter une colonne
    $queryBuilder->raw("
        ALTER TABLE users 
        ADD COLUMN email_verified_at DATETIME NULL
    ")->execute();
    
    // Modifier une colonne
    $queryBuilder->raw("
        ALTER TABLE users 
        MODIFY COLUMN email VARCHAR(180) NOT NULL
    ")->execute();
    
    // Ajouter un index
    $queryBuilder->raw("
        ALTER TABLE users 
        ADD INDEX IDX_USER_EMAIL_VERIFIED (email_verified_at)
    ")->execute();
}
```

#### Contraintes et clés étrangères

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Ajouter une clé étrangère
    $queryBuilder->raw("
        ALTER TABLE orders 
        ADD CONSTRAINT FK_ORDER_USER 
        FOREIGN KEY (user_id) REFERENCES users(id) 
        ON DELETE CASCADE ON UPDATE CASCADE
    ")->execute();
    
    // Ajouter une contrainte unique
    $queryBuilder->raw("
        ALTER TABLE orders 
        ADD CONSTRAINT UNIQ_ORDER_NUMBER 
        UNIQUE (order_number)
    ")->execute();
}
```

#### Insertion de données

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Insérer des données de base
    $queryBuilder->raw("
        INSERT INTO categories (name) VALUES (?)
    ")->bind(['Electronics'])->execute();
    
    $queryBuilder->raw("
        INSERT INTO categories (name) VALUES (?)
    ")->bind(['Books'])->execute();
    
    $queryBuilder->raw("
        INSERT INTO categories (name) VALUES (?)
    ")->bind(['Clothing'])->execute();
}
```

## Gestion des migrations

### API du MigrationManager

Le `MigrationManager` fournit une API simple pour gérer les migrations :

```php
use MulerTech\Database\Schema\Migration\MigrationManager;

// Initialisation
$migrationManager = new MigrationManager($entityManager);

// Enregistrer les migrations depuis un répertoire
$migrationManager->registerMigrations('/path/to/migrations');
```

### Exécution des migrations

#### Migration simple

```php
// Exécuter toutes les migrations en attente
$executed = $migrationManager->migrate();
echo "Migrations exécutées : $executed\n";
```

#### Vérifier les migrations en attente

```php
// Obtenir les migrations en attente
$pendingMigrations = $migrationManager->getPendingMigrations();

foreach ($pendingMigrations as $migration) {
    echo "En attente : " . $migration->getVersion() . "\n";
}

if (empty($pendingMigrations)) {
    echo "Aucune migration en attente\n";
}
```

#### Exécuter une migration spécifique

```php
// Enregistrer une migration individuelle
$migration = new Migration202412131200($entityManager);
$migrationManager->registerMigration($migration);

// Exécuter cette migration si elle n'est pas déjà exécutée
if (!$migrationManager->isMigrationExecuted($migration)) {
    $migrationManager->executeMigration($migration);
    echo "Migration {$migration->getVersion()} exécutée\n";
}
```

### Rollback (annulation)

#### Rollback simple

```php
// Annuler la dernière migration exécutée
$success = $migrationManager->rollback();

if ($success) {
    echo "Dernière migration annulée avec succès\n";
} else {
    echo "Aucune migration à annuler\n";
}
```

#### Vérification avant rollback

```php
// Obtenir toutes les migrations
$allMigrations = $migrationManager->getMigrations();
$executed = [];

foreach ($allMigrations as $version => $migration) {
    if ($migrationManager->isMigrationExecuted($migration)) {
        $executed[] = $version;
    }
}

if (!empty($executed)) {
    $lastExecuted = end($executed);
    echo "Dernière migration exécutée : $lastExecuted\n";
    
    // Confirmer avant rollback
    $confirm = readline("Voulez-vous annuler cette migration ? (y/n): ");
    if (strtolower($confirm) === 'y') {
        $migrationManager->rollback();
    }
}
```

## Table de suivi des migrations

MulerTech Database maintient automatiquement une table `migration_history` :

```sql
CREATE TABLE `migration_history` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `version` varchar(13) NOT NULL,
    `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `execution_time` int unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Vérifier l'historique

```sql
-- Voir toutes les migrations exécutées
SELECT version, executed_at, execution_time 
FROM migration_history 
ORDER BY executed_at DESC;

-- Voir la dernière migration
SELECT version, executed_at 
FROM migration_history 
ORDER BY executed_at DESC 
LIMIT 1;
```

## Gestion des erreurs

### Migration en échec

```php
try {
    $migrationManager->migrate();
    echo "Toutes les migrations exécutées avec succès\n";
} catch (RuntimeException $e) {
    echo "Erreur lors de l'exécution : " . $e->getMessage() . "\n";
    
    // En cas d'erreur, la transaction est automatiquement annulée
    // La migration échouée n'est pas enregistrée dans l'historique
}
```

### Rollback en échec

```php
try {
    $success = $migrationManager->rollback();
    if (!$success) {
        echo "Aucune migration à annuler\n";
    }
} catch (RuntimeException $e) {
    echo "Erreur lors du rollback : " . $e->getMessage() . "\n";
}
```

## Workflow complet

```php
<?php
// Exemple complet d'utilisation

use MulerTech\Database\Schema\Migration\MigrationManager;

try {
    // 1. Initialiser le manager
    $migrationManager = new MigrationManager($entityManager);
    
    // 2. Charger les migrations
    $migrationManager->registerMigrations(__DIR__ . '/migrations');
    
    // 3. Vérifier les migrations en attente
    $pending = $migrationManager->getPendingMigrations();
    if (empty($pending)) {
        echo "Aucune migration en attente\n";
        exit(0);
    }
    
    echo "Migrations en attente :\n";
    foreach ($pending as $migration) {
        echo "- " . $migration->getVersion() . "\n";
    }
    
    // 4. Demander confirmation
    $confirm = readline("Exécuter ces migrations ? (y/n): ");
    if (strtolower($confirm) !== 'y') {
        echo "Opération annulée\n";
        exit(0);
    }
    
    // 5. Exécuter les migrations
    $executed = $migrationManager->migrate();
    echo "$executed migration(s) exécutée(s) avec succès\n";
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
```

## Bonnes pratiques

### 1. Migrations atomiques

Chaque migration doit représenter un changement logique unique :

```php
// ✅ Bon : Une seule responsabilité
class Migration202412131200 extends Migration
{
    public function up(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $queryBuilder->raw("
            ALTER TABLE users 
            ADD COLUMN email_verified_at DATETIME NULL,
            ADD COLUMN email_verification_token VARCHAR(64) NULL
        ")->execute();
    }
    
    public function down(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $queryBuilder->raw("
            ALTER TABLE users 
            DROP COLUMN email_verified_at,
            DROP COLUMN email_verification_token
        ")->execute();
    }
}
```

### 2. Toujours implémenter `down()`

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    $queryBuilder->raw("
        CREATE TABLE user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(128) NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL
        )
    ")->execute();
}

public function down(): void
{
    $queryBuilder = $this->createQueryBuilder();
    $queryBuilder->raw("DROP TABLE user_sessions")->execute();
}
```

### 3. Validation des données

```php
public function up(): void
{
    $queryBuilder = $this->createQueryBuilder();
    
    // Vérifier que les données existantes sont compatibles
    $result = $queryBuilder->raw("
        SELECT COUNT(*) as count 
        FROM users 
        WHERE email IS NULL OR email = ''
    ")->execute()->fetchScalar();
    
    if ($result > 0) {
        throw new RuntimeException(
            "Cannot add NOT NULL constraint: {$result} rows have invalid email"
        );
    }
    
    // Modifier la colonne
    $queryBuilder->raw("
        ALTER TABLE users 
        MODIFY COLUMN email VARCHAR(255) NOT NULL
    ")->execute();
}
```

### 4. Opérations par batch pour gros volumes

```php
class Migration202412131200 extends Migration
{
    public function up(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        // Traitement par batch pour éviter les timeouts
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $rows = $queryBuilder->raw("
                SELECT id, old_data 
                FROM large_table 
                LIMIT ? OFFSET ?
            ")->bind([$batchSize, $offset])->execute()->fetchAll();
            
            foreach ($rows as $row) {
                $newData = strtoupper($row['old_data']);
                
                $updateQuery = $this->createQueryBuilder();
                $updateQuery->raw("
                    UPDATE large_table 
                    SET new_data = ? 
                    WHERE id = ?
                ")->bind([$newData, $row['id']])->execute();
            }
            
            $offset += $batchSize;
        } while (count($rows) === $batchSize);
    }

    public function down(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        
        $queryBuilder->raw("
            UPDATE large_table 
            SET new_data = NULL
        ")->execute();
    }
}
```

### 5. Test avant déploiement

```php
// Dans un environnement de test
$testMigrationManager = new MigrationManager($testEntityManager);
$testMigrationManager->registerMigrations('/path/to/migrations');

try {
    $testMigrationManager->migrate();
    echo "Tests de migration réussis\n";
} catch (Exception $e) {
    echo "Tests de migration échoués : " . $e->getMessage() . "\n";
    exit(1);
}
```

### 6. Sauvegarde avant migration

```php
// Créer une sauvegarde avant les migrations importantes
$backup = "backup_" . date('Y-m-d_H-i-s') . ".sql";
exec("mysqldump -u user -p database > $backup");

try {
    $migrationManager->migrate();
    echo "Migrations réussies, sauvegarde conservée dans $backup\n";
} catch (Exception $e) {
    echo "Migration échouée, restaurer depuis $backup si nécessaire\n";
    throw $e;
}
```

### 7. Vérification post-migration

```php
// Après migration, vérifier l'état
$allMigrations = $migrationManager->getMigrations();
$pendingAfter = $migrationManager->getPendingMigrations();

if (empty($pendingAfter)) {
    echo "Toutes les migrations ont été appliquées\n";
    echo "Total des migrations : " . count($allMigrations) . "\n";
} else {
    echo "Attention : " . count($pendingAfter) . " migration(s) encore en attente\n";
}
```

---

**Voir aussi :**
- [Outils de migration](migration-tools.md) - Commandes CLI et comparaison de schémas