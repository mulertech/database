# Commandes CLI de Migration

MulerTech Database fournit un ensemble de commandes CLI pour gérer facilement les migrations et le schéma de base de données.

## Table des Matières
- [Configuration](#configuration)
- [Commandes de migration](#commandes-de-migration)
- [Commandes de schéma](#commandes-de-schéma)
- [Commandes utilitaires](#commandes-utilitaires)
- [Options globales](#options-globales)
- [Exemples d'usage](#exemples-dusage)

## Configuration

### Installation des commandes

```php
// bin/console ou votre point d'entrée CLI
#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Console\Application;
use MulerTech\Database\Command\Migration\GenerateCommand;
use MulerTech\Database\Command\Migration\MigrateCommand;
use MulerTech\Database\Command\Migration\RollbackCommand;
use MulerTech\Database\Command\Migration\StatusCommand;
use MulerTech\Database\Command\Schema\ValidateCommand;
use MulerTech\Database\Command\Schema\DiffCommand;

$app = new Application('MulerTech Database CLI');

// Configuration de l'EntityManager
$entityManager = // ... votre configuration

// Enregistrement des commandes
$app->add(new GenerateCommand($entityManager));
$app->add(new MigrateCommand($entityManager));
$app->add(new RollbackCommand($entityManager));
$app->add(new StatusCommand($entityManager));
$app->add(new ValidateCommand($entityManager));
$app->add(new DiffCommand($entityManager));

$app->run();
```

### Configuration via fichier

```yaml
# config/database.yaml
mulertech_database:
  migrations:
    directory: 'src/Migrations'
    namespace: 'App\Migrations'
    table: 'mt_migrations'
  
  schema:
    auto_generate: true
    validate_on_boot: false
```

## Commandes de migration

### `mt:migration:generate`

Génère une nouvelle migration basée sur les différences détectées.

```bash
# Génération automatique
php bin/console mt:migration:generate

# Avec nom personnalisé
php bin/console mt:migration:generate --name="add_user_profiles"

# Aperçu sans création
php bin/console mt:migration:generate --dry-run

# Migration vide (à remplir manuellement)
php bin/console mt:migration:generate --empty --name="custom_data_migration"
```

**Options disponibles :**
- `--name=NAME` : Nom descriptif de la migration
- `--dry-run` : Affiche le code généré sans créer le fichier
- `--empty` : Crée une migration vide
- `--force` : Génère même s'il n'y a pas de différences
- `--filter-tables=TABLES` : Limite aux tables spécifiées (séparées par des virgules)

### `mt:migration:migrate`

Exécute les migrations en attente.

```bash
# Exécuter toutes les migrations
php bin/console mt:migration:migrate

# Exécuter jusqu'à une migration spécifique
php bin/console mt:migration:migrate --to=20241213120000

# Exécuter seulement la prochaine migration
php bin/console mt:migration:migrate --next

# Mode verbeux avec détails
php bin/console mt:migration:migrate --verbose

# Simulation sans exécution
php bin/console mt:migration:migrate --dry-run
```

**Options disponibles :**
- `--to=VERSION` : Migrer jusqu'à une version spécifique
- `--next` : Exécuter seulement la prochaine migration
- `--dry-run` : Simulation sans modification de la base
- `--no-interaction` : Mode non-interactif pour les scripts
- `--force` : Forcer l'exécution même en cas d'avertissements
- `--time` : Afficher le temps d'exécution

### `mt:migration:rollback`

Annule des migrations précédemment exécutées.

```bash
# Annuler la dernière migration
php bin/console mt:migration:rollback

# Annuler N migrations
php bin/console mt:migration:rollback --steps=3

# Annuler jusqu'à une version
php bin/console mt:migration:rollback --to=20241210100000

# Annuler tout jusqu'au début
php bin/console mt:migration:rollback --to=0

# Annuler le dernier lot
php bin/console mt:migration:rollback --batch
```

**Options disponibles :**
- `--steps=N` : Nombre de migrations à annuler
- `--to=VERSION` : Annuler jusqu'à une version spécifique
- `--batch` : Annuler le dernier lot complet
- `--dry-run` : Simulation sans modification
- `--force` : Forcer sans demander confirmation

### `mt:migration:status`

Affiche l'état des migrations.

```bash
# État général
php bin/console mt:migration:status

# Affichage détaillé
php bin/console mt:migration:status --verbose

# Format machine-readable
php bin/console mt:migration:status --format=json

# Seulement les migrations en attente
php bin/console mt:migration:status --pending-only
```

**Exemple de sortie :**
```
Migration Status
================

 Configuration:
   Directory: src/Migrations
   Namespace: App\Migrations
   Table: mt_migrations

 Status:
   Executed: 15
   Pending: 3
   Last Batch: 7

 Pending Migrations:
   20241213120000_AddUserProfiles
   20241213130000_UpdateProductSchema
   20241213140000_CreateAuditTables

 Last Executed:
   20241212180000_OptimizeIndexes (Batch 7, 2024-12-12 18:30:15)
```

### `mt:migration:reset`

Remet à zéro toutes les migrations.

```bash
# Reset complet (DESTRUCTIF!)
php bin/console mt:migration:reset --force

# Reset avec confirmation
php bin/console mt:migration:reset

# Reset et re-migration
php bin/console mt:migration:reset --and-migrate
```

## Commandes de schéma

### `mt:schema:validate`

Valide la cohérence entre entités et base de données.

```bash
# Validation complète
php bin/console mt:schema:validate

# Validation avec suggestions de correction
php bin/console mt:schema:validate --with-suggestions

# Validation seulement des erreurs critiques
php bin/console mt:schema:validate --errors-only

# Format JSON pour intégration
php bin/console mt:schema:validate --format=json
```

### `mt:schema:diff`

Compare le schéma actuel avec les entités.

```bash
# Différences complètes
php bin/console mt:schema:diff

# Seulement les nouvelles tables
php bin/console mt:schema:diff --new-tables-only

# Générer la migration correspondante
php bin/console mt:schema:diff --generate-migration

# Filtrer par tables
php bin/console mt:schema:diff --filter="users,products"
```

### `mt:schema:create`

Crée le schéma complet depuis les entités.

```bash
# Création complète (base vide)
php bin/console mt:schema:create

# Avec données de test
php bin/console mt:schema:create --with-fixtures

# Simulation
php bin/console mt:schema:create --dry-run
```

### `mt:schema:drop`

Supprime le schéma complet.

```bash
# Suppression complète (DESTRUCTIF!)
php bin/console mt:schema:drop --force

# Avec sauvegarde automatique
php bin/console mt:schema:drop --backup
```

## Commandes utilitaires

### `mt:database:backup`

Crée une sauvegarde de la base de données.

```bash
# Sauvegarde complète
php bin/console mt:database:backup

# Avec nom personnalisé
php bin/console mt:database:backup --name="pre_migration_backup"

# Seulement la structure
php bin/console mt:database:backup --schema-only

# Compression automatique
php bin/console mt:database:backup --compress
```

### `mt:database:restore`

Restaure une sauvegarde.

```bash
# Restauration interactive
php bin/console mt:database:restore

# Restauration d'un fichier spécifique
php bin/console mt:database:restore backup_20241213.sql

# Avec confirmation forcée
php bin/console mt:database:restore backup.sql --force
```

### `mt:cache:clear`

Vide les caches de l'ORM.

```bash
# Vider tous les caches
php bin/console mt:cache:clear

# Seulement le cache des métadonnées
php bin/console mt:cache:clear --metadata-only

# Seulement le cache des requêtes
php bin/console mt:cache:clear --query-only
```

## Options globales

### Options communes à toutes les commandes

```bash
# Mode verbeux (niveaux : -v, -vv, -vvv)
php bin/console [commande] -v

# Mode silencieux
php bin/console [commande] --quiet

# Mode non-interactif
php bin/console [commande] --no-interaction

# Configuration d'environnement
php bin/console [commande] --env=prod

# Aide détaillée
php bin/console [commande] --help
```

### Variables d'environnement

```bash
# Base de données alternative
MT_DATABASE_URL="mysql://user:pass@host:3306/db" php bin/console mt:migration:migrate

# Répertoire de migrations personnalisé
MT_MIGRATIONS_DIR="/custom/path" php bin/console mt:migration:generate

# Mode debug
MT_DEBUG=1 php bin/console mt:migration:migrate
```

## Exemples d'usage

### Workflow de développement typique

```bash
# 1. Modifier les entités dans le code
# 2. Générer la migration correspondante
php bin/console mt:migration:generate --name="update_user_entity"

# 3. Vérifier la migration générée
cat src/Migrations/Migration20241213120000_UpdateUserEntity.php

# 4. Exécuter la migration
php bin/console mt:migration:migrate

# 5. Valider le résultat
php bin/console mt:schema:validate
```

### Déploiement en production

```bash
# 1. Sauvegarde avant déploiement
php bin/console mt:database:backup --name="pre_deploy_$(date +%Y%m%d)"

# 2. Valider les migrations en mode dry-run
php bin/console mt:migration:migrate --dry-run

# 3. Exécuter les migrations
php bin/console mt:migration:migrate --no-interaction

# 4. Validation post-déploiement
php bin/console mt:schema:validate --errors-only
```

### Développement collaboratif

```bash
# Synchroniser avec la branche principale
git pull origin main

# Vérifier l'état des migrations
php bin/console mt:migration:status

# Exécuter les nouvelles migrations des collègues
php bin/console mt:migration:migrate

# Créer sa propre migration
php bin/console mt:migration:generate --name="my_feature"
```

### Résolution de problèmes

```bash
# Diagnostiquer les problèmes de schéma
php bin/console mt:schema:validate --with-suggestions

# Voir les différences détaillées
php bin/console mt:schema:diff --verbose

# Réinitialiser en cas de problème grave
php bin/console mt:migration:reset
php bin/console mt:schema:create
```

### Scripts automatisés

```bash
#!/bin/bash
# scripts/deploy.sh

set -e

echo "🚀 Déploiement de la base de données..."

# Sauvegarde
echo "📦 Création de la sauvegarde..."
php bin/console mt:database:backup --name="deploy_$(date +%Y%m%d_%H%M%S)"

# Validation
echo "✅ Validation des migrations..."
php bin/console mt:migration:migrate --dry-run

# Exécution
echo "⚡ Exécution des migrations..."
php bin/console mt:migration:migrate --no-interaction

# Vérification
echo "🔍 Validation finale..."
php bin/console mt:schema:validate --errors-only

echo "✅ Déploiement terminé avec succès!"
```

---

**Voir aussi :**
- [Création de migrations](creating-migrations.md)
- [Exécution des migrations](running-migrations.md)
- [Comparaison de schémas](schema-diff.md)
