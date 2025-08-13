# Outils de Migration

Ce guide présente les outils CLI et de comparaison de schémas pour gérer les migrations MulerTech Database.

## Commandes CLI

MulerTech Database fournit 3 commandes CLI via le framework MTerm.

### `migration:generate`

Génère une nouvelle migration basée sur les différences entre les entités et le schéma actuel.

```bash
php bin/console migration:generate
```

**Avec timestamp personnalisé :**
```bash
php bin/console migration:generate 202412131200
```

**Fonctionnement :**
- Compare le schéma actuel avec les entités définies
- Génère automatiquement le code SQL de migration
- Crée un fichier `Migration{timestamp}.php` dans le répertoire des migrations
- Retourne 0 si succès, 1 si erreur

**Sortie :**
```
Generating a migration from entity definitions...
Migration successfully generated: Migration202412131200.php
```

Si aucune différence n'est détectée :
```
No schema changes detected, no migration generated.
```

### `migration:run`

Exécute toutes les migrations en attente.

```bash
php bin/console migration:run
```

**Mode simulation (dry-run) :**
```bash
php bin/console migration:run --dry-run
```

**Fonctionnement :**
- Affiche la liste des migrations en attente
- Demande confirmation avant exécution (sauf en mode dry-run)
- Exécute les migrations dans l'ordre chronologique
- Enregistre l'exécution dans la table de suivi

**Exemple d'exécution :**
```
Running pending migrations...
3 pending migration(s):
 - 202412131200
 - 202412131300
 - 202412131400
Do you want to run these migrations? (y/n): y
3 migration(s) successfully executed.
```

### `migration:rollback`

Annule la dernière migration exécutée.

```bash
php bin/console migration:rollback
```

**Mode simulation (dry-run) :**
```bash
php bin/console migration:rollback --dry-run
```

**Fonctionnement :**
- Identifie la dernière migration exécutée
- Demande confirmation avant rollback
- Exécute la méthode `down()` de la migration
- Met à jour la table de suivi des migrations

**Exemple d'exécution :**
```
Rolling back the last migration...
Last executed migration: 202412131400
Do you want to rollback this migration? (y/n): y
Migration 202412131400 successfully rolled back.
```

## Configuration des commandes

### Setup des commandes

```php
<?php
// bin/console

require_once 'vendor/autoload.php';

use MulerTech\MTerm\Core\Terminal;
use MulerTech\Database\Schema\Migration\Command\MigrationGenerateCommand;
use MulerTech\Database\Schema\Migration\Command\MigrationRunCommand;
use MulerTech\Database\Schema\Migration\Command\MigrationRollbackCommand;
use MulerTech\Database\Schema\Migration\MigrationManager;

// Configuration de l'EntityManager
$entityManager = // ... votre configuration

// Terminal MTerm
$terminal = new Terminal();

// MigrationManager
$migrationManager = new MigrationManager($entityManager, '/path/to/migrations');

// Enregistrement des commandes
$commands = [
    new MigrationGenerateCommand($terminal, $entityManager, '/path/to/migrations'),
    new MigrationRunCommand($terminal, $migrationManager),
    new MigrationRollbackCommand($terminal, $migrationManager)
];

// Exécution
foreach ($commands as $command) {
    if ($command->name === $argv[1] ?? '') {
        exit($command->execute(array_slice($argv, 2)));
    }
}

echo "Commandes disponibles:\n";
foreach ($commands as $command) {
    echo "  {$command->name} - {$command->description}\n";
}
```

## Comparaison de Schémas

La comparaison de schémas permet de détecter automatiquement les différences entre l'état actuel de la base de données et les entités définies.

### API SchemaComparer

#### Utilisation de base

```php
use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Information\InformationSchema;

// Initialisation
$informationSchema = new InformationSchema($entityManager->getEmEngine());
$schemaComparer = new SchemaComparer(
    $informationSchema,
    $entityManager->getMetadataRegistry(),
    'my_database'
);

// Comparer le schéma
$diff = $schemaComparer->compare();

// Vérifier s'il y a des différences
if ($diff->hasDifferences()) {
    echo "Différences détectées dans le schéma\n";
} else {
    echo "Le schéma est à jour\n";
}
```

### Types de différences

#### Tables à créer

```php
// Obtenir les tables qui doivent être créées
$tablesToCreate = $diff->getTablesToCreate();

foreach ($tablesToCreate as $tableName => $entityClass) {
    echo "Table à créer : $tableName (entité : $entityClass)\n";
}
```

#### Tables à supprimer

```php
// Obtenir les tables qui doivent être supprimées
$tablesToDrop = $diff->getTablesToDrop();

foreach ($tablesToDrop as $tableName) {
    echo "Table à supprimer : $tableName\n";
}
```

#### Colonnes à ajouter

```php
// Obtenir les colonnes à ajouter par table
$columnsToAdd = $diff->getColumnsToAdd();

foreach ($columnsToAdd as $tableName => $columns) {
    echo "Table : $tableName\n";
    foreach ($columns as $columnName => $definition) {
        echo "  + Colonne à ajouter : $columnName\n";
        echo "    Type : {$definition['COLUMN_TYPE']}\n";
        echo "    Nullable : {$definition['IS_NULLABLE']}\n";
        if ($definition['COLUMN_DEFAULT'] !== null) {
            echo "    Défaut : {$definition['COLUMN_DEFAULT']}\n";
        }
    }
}
```

#### Colonnes à modifier

```php
// Obtenir les colonnes à modifier
$columnsToModify = $diff->getColumnsToModify();

foreach ($columnsToModify as $tableName => $columns) {
    echo "Table : $tableName\n";
    foreach ($columns as $columnName => $changes) {
        echo "  ~ Colonne à modifier : $columnName\n";
        
        if (isset($changes['COLUMN_TYPE'])) {
            echo "    Type : {$changes['COLUMN_TYPE']['from']} → {$changes['COLUMN_TYPE']['to']}\n";
        }
        
        if (isset($changes['IS_NULLABLE'])) {
            echo "    Nullable : {$changes['IS_NULLABLE']['from']} → {$changes['IS_NULLABLE']['to']}\n";
        }
        
        if (isset($changes['COLUMN_DEFAULT'])) {
            $from = $changes['COLUMN_DEFAULT']['from'] ?? 'NULL';
            $to = $changes['COLUMN_DEFAULT']['to'] ?? 'NULL';
            echo "    Défaut : $from → $to\n";
        }
    }
}
```

#### Clés étrangères

```php
// Clés étrangères à ajouter
$foreignKeysToAdd = $diff->getForeignKeysToAdd();

foreach ($foreignKeysToAdd as $tableName => $foreignKeys) {
    echo "Table : $tableName\n";
    foreach ($foreignKeys as $constraintName => $definition) {
        echo "  + FK à ajouter : $constraintName\n";
        echo "    Colonne : {$definition['COLUMN_NAME']}\n";
        echo "    Référence : {$definition['REFERENCED_TABLE_NAME']}.{$definition['REFERENCED_COLUMN_NAME']}\n";
        echo "    DELETE : {$definition['DELETE_RULE']->value}\n";
        echo "    UPDATE : {$definition['UPDATE_RULE']->value}\n";
    }
}

// Clés étrangères à supprimer
$foreignKeysToDrop = $diff->getForeignKeysToDrop();

foreach ($foreignKeysToDrop as $tableName => $constraintNames) {
    echo "Table : $tableName\n";
    foreach ($constraintNames as $constraintName) {
        echo "  - FK à supprimer : $constraintName\n";
    }
}
```

## Analyse complète des différences

```php
function analyzeSchemaChanges(SchemaDifference $diff): void
{
    echo "=== ANALYSE DES DIFFÉRENCES DE SCHÉMA ===\n\n";
    
    // Résumé
    $tablesToCreate = count($diff->getTablesToCreate());
    $tablesToDrop = count($diff->getTablesToDrop());
    $columnsToAdd = array_sum(array_map('count', $diff->getColumnsToAdd()));
    $columnsToModify = array_sum(array_map('count', $diff->getColumnsToModify()));
    $columnsToDrop = array_sum(array_map('count', $diff->getColumnsToDrop()));
    $foreignKeysToAdd = array_sum(array_map('count', $diff->getForeignKeysToAdd()));
    $foreignKeysToDrop = array_sum(array_map('count', $diff->getForeignKeysToDrop()));
    
    echo "Résumé :\n";
    echo "- Tables à créer : $tablesToCreate\n";
    echo "- Tables à supprimer : $tablesToDrop\n";
    echo "- Colonnes à ajouter : $columnsToAdd\n";
    echo "- Colonnes à modifier : $columnsToModify\n";
    echo "- Colonnes à supprimer : $columnsToDrop\n";
    echo "- Clés étrangères à ajouter : $foreignKeysToAdd\n";
    echo "- Clés étrangères à supprimer : $foreignKeysToDrop\n\n";
    
    // Détails des nouvelles tables
    if ($tablesToCreate > 0) {
        echo "=== NOUVELLES TABLES ===\n";
        foreach ($diff->getTablesToCreate() as $tableName => $entityClass) {
            echo "• $tableName ($entityClass)\n";
        }
        echo "\n";
    }
    
    // Détails des tables à supprimer (ATTENTION)
    if ($tablesToDrop > 0) {
        echo "=== ⚠️  TABLES À SUPPRIMER (DESTRUCTIF) ===\n";
        foreach ($diff->getTablesToDrop() as $tableName) {
            echo "• $tableName\n";
        }
        echo "\n";
    }
    
    // Changements de colonnes
    foreach ($diff->getColumnsToAdd() as $tableName => $columns) {
        echo "=== NOUVELLES COLONNES : $tableName ===\n";
        foreach ($columns as $columnName => $definition) {
            echo "• $columnName : {$definition['COLUMN_TYPE']}";
            if ($definition['IS_NULLABLE'] === 'NO') {
                echo " NOT NULL";
            }
            if ($definition['COLUMN_DEFAULT'] !== null) {
                echo " DEFAULT {$definition['COLUMN_DEFAULT']}";
            }
            echo "\n";
        }
        echo "\n";
    }
}
```

## Scripts utiles

### Script de vérification complète

```php
#!/usr/bin/env php
<?php
// scripts/check-schema.php

require_once 'vendor/autoload.php';

use MulerTech\Database\Schema\Diff\SchemaComparer;
use MulerTech\Database\Schema\Information\InformationSchema;
use MulerTech\Database\Database\Interface\DatabaseParameterParser;

// Configuration EntityManager
$entityManager = /* votre configuration */;

try {
    // Initialiser le comparateur
    $informationSchema = new InformationSchema($entityManager->getEmEngine());
    $dbParameters = new DatabaseParameterParser()->parseParameters();
    
    if (!is_string($dbParameters['dbname'])) {
        throw new RuntimeException('Database name must be a string');
    }
    
    $schemaComparer = new SchemaComparer(
        $informationSchema,
        $entityManager->getMetadataRegistry(),
        $dbParameters['dbname']
    );
    
    // Comparer
    echo "Comparaison du schéma en cours...\n";
    $diff = $schemaComparer->compare();
    
    if (!$diff->hasDifferences()) {
        echo "✅ Le schéma est à jour !\n";
        exit(0);
    }
    
    echo "❌ Différences détectées dans le schéma !\n\n";
    
    // Afficher les différences
    analyzeSchemaChanges($diff);
    
    echo "Commandes suggérées :\n";
    echo "  php bin/console migration:generate\n";
    echo "  php bin/console migration:run\n";
    
    exit(1);
    
} catch (Exception $e) {
    echo "Erreur lors de la comparaison : " . $e->getMessage() . "\n";
    exit(1);
}
```

### Workflow de développement

```bash
# 1. Modifier vos entités
# 2. Vérifier les différences
php scripts/check-schema.php

# 3. Générer la migration
php bin/console migration:generate

# 4. Vérifier le fichier généré
cat src/Migrations/Migration202412131200.php

# 5. Tester la migration
php bin/console migration:run --dry-run

# 6. Exécuter la migration
php bin/console migration:run

# 7. En cas de problème, faire rollback
php bin/console migration:rollback
```

## Gestion des changements destructifs

### Détection automatique

```php
// Dans un script de pré-déploiement
$diff = $schemaComparer->compare();

if ($diff->hasDifferences()) {
    $destructiveChanges = [];
    
    // Vérifier les suppressions de tables
    if (!empty($diff->getTablesToDrop())) {
        $destructiveChanges[] = "Suppression de tables : " . implode(', ', $diff->getTablesToDrop());
    }
    
    // Vérifier les suppressions de colonnes
    foreach ($diff->getColumnsToDrop() as $tableName => $columns) {
        $destructiveChanges[] = "Suppression de colonnes dans $tableName : " . implode(', ', $columns);
    }
    
    if (!empty($destructiveChanges)) {
        echo "⚠️  CHANGEMENTS DESTRUCTIFS DÉTECTÉS :\n";
        foreach ($destructiveChanges as $change) {
            echo "  • $change\n";
        }
        echo "\n";
        
        $confirm = readline("Continuer malgré les changements destructifs ? (yes/no): ");
        if ($confirm !== 'yes') {
            echo "Opération annulée\n";
            exit(1);
        }
    }
}
```

### Ignore des tables système

Le système ignore automatiquement la table `migration_history`, mais vous pouvez personnaliser cela :

```php
class CustomSchemaComparer extends SchemaComparer
{
    protected function shouldIgnoreTable(string $tableName): bool
    {
        $ignoredTables = [
            'migration_history',
            'cache_entries',
            'sessions',
            'logs'
        ];
        
        return in_array($tableName, $ignoredTables);
    }
}
```

## Gestion des erreurs

### Erreurs de commandes

**Migration déjà exécutée :**
```
No pending migrations.
```

**Aucune migration à annuler :**
```
No executed migrations to rollback.
```

**Erreur de génération :**
```
Error: Migration directory does not exist: /path/to/migrations
```

### Résolution de problèmes

1. **Vérifier le répertoire des migrations :**
```bash
ls -la src/Migrations/
```

2. **Vérifier la table de suivi :**
```sql
SELECT * FROM migration_history ORDER BY executed_at DESC;
```

3. **Vérifier la connexion base de données :**
```bash
php -r "
$entityManager = /* votre config */;
try {
    $entityManager->getEmEngine()->getConnection()->query('SELECT 1');
    echo 'Connexion OK';
} catch (Exception $e) {
    echo 'Erreur: ' . $e->getMessage();
}
"
```

---

**Voir aussi :**
- [Migrations](migrations.md) - Guide complet des migrations