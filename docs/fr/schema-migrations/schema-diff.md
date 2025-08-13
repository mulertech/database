# Comparaison de Schémas

La comparaison de schémas permet de détecter automatiquement les différences entre l'état actuel de la base de données et les entités définies dans votre code.

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Comparateur de schémas](#comparateur-de-schémas)
- [Types de différences](#types-de-différences)
- [Génération automatique](#génération-automatique)
- [Validation et vérification](#validation-et-vérification)
- [Intégration CI/CD](#intégration-cicd)

## Vue d'ensemble

Le système de comparaison de schémas analyse :
- Les tables existantes vs les entités définies
- Les colonnes et leurs types
- Les index et contraintes
- Les clés étrangères
- Les données de configuration

## Comparateur de schémas

### Utilisation de base

```php
use MulerTech\Database\Schema\SchemaComparator;
use MulerTech\Database\Schema\SchemaIntrospector;

$introspector = new SchemaIntrospector($connection);
$comparator = new SchemaComparator($entityManager);

// Obtenir le schéma actuel de la base
$currentSchema = $introspector->introspectSchema();

// Obtenir le schéma attendu depuis les entités
$expectedSchema = $comparator->getSchemaFromEntities();

// Comparer les deux schémas
$diff = $comparator->compare($currentSchema, $expectedSchema);
```

### Analyse détaillée

```php
// Analyser les différences par type
$tableDiffs = $diff->getNewTables();
$columnDiffs = $diff->getChangedTables();
$removedTables = $diff->getRemovedTables();

echo "Tables à créer: " . count($tableDiffs) . "\n";
echo "Tables à modifier: " . count($columnDiffs) . "\n";
echo "Tables à supprimer: " . count($removedTables) . "\n";

// Détails des modifications
foreach ($columnDiffs as $tableName => $tableDiff) {
    echo "\nTable '{$tableName}':\n";
    
    foreach ($tableDiff->getAddedColumns() as $column) {
        echo "  + Ajouter colonne: {$column->getName()}\n";
    }
    
    foreach ($tableDiff->getChangedColumns() as $columnDiff) {
        echo "  ~ Modifier colonne: {$columnDiff->getOldColumn()->getName()}\n";
        $this->showColumnChanges($columnDiff);
    }
    
    foreach ($tableDiff->getRemovedColumns() as $column) {
        echo "  - Supprimer colonne: {$column->getName()}\n";
    }
}
```

## Types de différences

### Différences de tables

```php
class TableDifference
{
    // Tables nouvelles à créer
    public function getNewTables(): array
    {
        return $this->newTables;
    }
    
    // Tables modifiées
    public function getChangedTables(): array
    {
        return $this->changedTables;
    }
    
    // Tables à supprimer
    public function getRemovedTables(): array
    {
        return $this->removedTables;
    }
    
    // Tables renommées
    public function getRenamedTables(): array
    {
        return $this->renamedTables;
    }
}
```

### Différences de colonnes

```php
// Exemple d'analyse des changements de colonnes
$comparator->analyzeColumnDifferences($tableDiff);

foreach ($tableDiff->getChangedColumns() as $columnDiff) {
    $oldColumn = $columnDiff->getOldColumn();
    $newColumn = $columnDiff->getNewColumn();
    
    // Type changé
    if ($oldColumn->getType() !== $newColumn->getType()) {
        echo "Type: {$oldColumn->getType()} → {$newColumn->getType()}\n";
    }
    
    // Longueur changée
    if ($oldColumn->getLength() !== $newColumn->getLength()) {
        echo "Longueur: {$oldColumn->getLength()} → {$newColumn->getLength()}\n";
    }
    
    // Nullable changé
    if ($oldColumn->getNotnull() !== $newColumn->getNotnull()) {
        $oldNull = $oldColumn->getNotnull() ? 'NOT NULL' : 'NULL';
        $newNull = $newColumn->getNotnull() ? 'NOT NULL' : 'NULL';
        echo "Nullable: {$oldNull} → {$newNull}\n";
    }
    
    // Valeur par défaut changée
    if ($oldColumn->getDefault() !== $newColumn->getDefault()) {
        echo "Défaut: {$oldColumn->getDefault()} → {$newColumn->getDefault()}\n";
    }
}
```

### Différences d'index

```php
// Analyser les changements d'index
foreach ($tableDiff->getAddedIndexes() as $index) {
    echo "Nouvel index: {$index->getName()} sur " . implode(', ', $index->getColumns()) . "\n";
}

foreach ($tableDiff->getChangedIndexes() as $indexDiff) {
    $oldIndex = $indexDiff->getOldIndex();
    $newIndex = $indexDiff->getNewIndex();
    
    echo "Index modifié: {$oldIndex->getName()}\n";
    echo "  Anciennes colonnes: " . implode(', ', $oldIndex->getColumns()) . "\n";
    echo "  Nouvelles colonnes: " . implode(', ', $newIndex->getColumns()) . "\n";
}

foreach ($tableDiff->getRemovedIndexes() as $index) {
    echo "Index supprimé: {$index->getName()}\n";
}
```

### Différences de clés étrangères

```php
// Analyser les changements de clés étrangères
foreach ($tableDiff->getAddedForeignKeys() as $fk) {
    echo "Nouvelle FK: {$fk->getName()}\n";
    echo "  Référence: {$fk->getForeignTableName()}(" . implode(', ', $fk->getForeignColumns()) . ")\n";
    echo "  Règles: onDelete={$fk->getOnDelete()}, onUpdate={$fk->getOnUpdate()}\n";
}

foreach ($tableDiff->getChangedForeignKeys() as $fkDiff) {
    echo "FK modifiée: {$fkDiff->getOldForeignKey()->getName()}\n";
    // Détails des changements...
}
```

## Génération automatique

### Génération de migrations depuis les différences

```php
use MulerTech\Database\Schema\MigrationGenerator;

$generator = new MigrationGenerator($entityManager);

// Générer une migration depuis les différences détectées
$diff = $comparator->compare($currentSchema, $expectedSchema);

if (!$diff->isEmpty()) {
    $migration = $generator->generateFromDiff($diff);
    
    // Sauvegarder la migration
    $migrationName = 'update_schema_' . date('YmdHis');
    $generator->save($migration, $migrationName);
    
    echo "Migration générée: {$migrationName}\n";
} else {
    echo "Aucune différence détectée, pas de migration nécessaire.\n";
}
```

### Génération personnalisée

```php
class CustomMigrationGenerator extends MigrationGenerator
{
    protected function generateTableCreation(Table $table): string
    {
        $code = parent::generateTableCreation($table);
        
        // Ajouter des commentaires personnalisés
        $code .= "\n        // Table générée automatiquement le " . date('Y-m-d H:i:s');
        
        return $code;
    }
    
    protected function shouldIgnoreTable(string $tableName): bool
    {
        // Ignorer certaines tables système
        $ignoredTables = ['cache_entries', 'sessions', 'logs'];
        return in_array($tableName, $ignoredTables);
    }
}
```

## Validation et vérification

### Validation de cohérence

```php
use MulerTech\Database\Schema\SchemaValidator;

$validator = new SchemaValidator($entityManager);

// Valider la cohérence entre entités et base
$issues = $validator->validateConsistency();

foreach ($issues as $issue) {
    echo "⚠️  {$issue['severity']}: {$issue['message']}\n";
    echo "    Table: {$issue['table']}\n";
    echo "    Colonne: {$issue['column']}\n";
    echo "    Solution suggérée: {$issue['suggestion']}\n\n";
}

// Types d'issues détectées
if ($validator->hasTypeIssues()) {
    echo "Problèmes de types détectés:\n";
    foreach ($validator->getTypeIssues() as $issue) {
        echo "  - {$issue['entity']}::{$issue['property']}: {$issue['problem']}\n";
    }
}
```

### Vérification de destructivité

```php
// Analyser si les changements sont destructifs
$destructiveAnalyzer = new DestructiveChangeAnalyzer();
$destructiveChanges = $destructiveAnalyzer->analyze($diff);

if (!empty($destructiveChanges)) {
    echo "⚠️  ATTENTION: Changements destructifs détectés!\n";
    
    foreach ($destructiveChanges as $change) {
        echo "  - {$change['type']}: {$change['description']}\n";
        echo "    Impact: {$change['impact']}\n";
        echo "    Données affectées: {$change['affected_rows']} lignes\n";
        
        if ($change['backup_recommended']) {
            echo "    🔥 SAUVEGARDE RECOMMANDÉE\n";
        }
    }
    
    $confirmation = readline("Continuer malgré les changements destructifs? (yes/no): ");
    if ($confirmation !== 'yes') {
        echo "Opération annulée.\n";
        exit(1);
    }
}
```

### Tests de régression

```php
class SchemaRegressionTester
{
    public function testSchemaChanges(SchemaDiff $diff): array
    {
        $tests = [];
        
        // Tester les nouvelles tables
        foreach ($diff->getNewTables() as $table) {
            $tests[] = $this->testTableCreation($table);
        }
        
        // Tester les modifications de colonnes
        foreach ($diff->getChangedTables() as $tableDiff) {
            $tests[] = $this->testColumnChanges($tableDiff);
        }
        
        return array_filter($tests);
    }
    
    private function testTableCreation(Table $table): ?array
    {
        // Vérifier que la table peut être créée avec les données existantes
        try {
            $this->validateTableConstraints($table);
            return null; // Pas de problème
        } catch (Exception $e) {
            return [
                'type' => 'table_creation_error',
                'table' => $table->getName(),
                'error' => $e->getMessage()
            ];
        }
    }
}
```

## Intégration CI/CD

### Script de vérification automatique

```php
#!/usr/bin/env php
<?php
// scripts/check-schema.php

require_once 'vendor/autoload.php';

use MulerTech\Database\Schema\SchemaComparator;

$entityManager = // ... initialisation

$comparator = new SchemaComparator($entityManager);
$diff = $comparator->compareWithCurrentDatabase();

if (!$diff->isEmpty()) {
    echo "❌ Différences de schéma détectées!\n";
    
    // Afficher un résumé
    echo "Résumé des changements:\n";
    echo "- Tables à créer: " . count($diff->getNewTables()) . "\n";
    echo "- Tables à modifier: " . count($diff->getChangedTables()) . "\n";
    echo "- Tables à supprimer: " . count($diff->getRemovedTables()) . "\n";
    
    echo "\nVeuillez générer et exécuter les migrations appropriées.\n";
    echo "Commande suggérée: php bin/console mt:migration:generate\n";
    
    exit(1);
} else {
    echo "✅ Schéma à jour!\n";
    exit(0);
}
```

### Configuration GitHub Actions

```yaml
# .github/workflows/schema-check.yml
name: Schema Validation

on: [push, pull_request]

jobs:
  schema-check:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_db
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: composer install --no-progress --prefer-dist
        
      - name: Run migrations
        run: php bin/console mt:migration:migrate --no-interaction
        
      - name: Check schema consistency
        run: php scripts/check-schema.php
        
      - name: Generate migration if needed
        if: failure()
        run: |
          php bin/console mt:migration:generate --dry-run
          echo "Des migrations sont nécessaires. Voir la sortie ci-dessus."
```

### Hooks de pré-commit

```bash
#!/bin/sh
# .git/hooks/pre-commit

echo "Vérification du schéma de base de données..."

# Exécuter le script de vérification
php scripts/check-schema.php

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Échec de la vérification du schéma!"
    echo "Veuillez générer et committer les migrations nécessaires."
    echo ""
    echo "Commandes utiles:"
    echo "  php bin/console mt:migration:generate"
    echo "  php bin/console mt:migration:migrate"
    echo ""
    exit 1
fi

echo "✅ Schéma vérifié avec succès!"
```

---

**Voir aussi :**
- [Création de migrations](creating-migrations.md)
- [Exécution des migrations](running-migrations.md)
- [Commandes CLI](migration-commands.md)
