# Création de Migrations

Les migrations permettent de gérer l'évolution du schéma de base de données de manière contrôlée et reproductible.

## Table des Matières
- [Vue d'ensemble](#vue-densemble)
- [Génération automatique](#génération-automatique)
- [Création manuelle](#création-manuelle)
- [Structure d'une migration](#structure-dune-migration)
- [Types d'opérations](#types-dopérations)
- [Bonnes pratiques](#bonnes-pratiques)

## Vue d'ensemble

Une migration est un fichier PHP qui décrit les modifications à apporter au schéma de base de données. Chaque migration possède :
- Un timestamp unique pour l'ordre d'exécution
- Une méthode `up()` pour appliquer les changements
- Une méthode `down()` pour annuler les changements

## Génération automatique

MulerTech Database peut générer automatiquement des migrations en comparant l'état actuel du schéma avec vos entités :

```php
use MulerTech\Database\Schema\MigrationGenerator;

$generator = new MigrationGenerator($entityManager);
$migration = $generator->generateFromDiff();

// Sauvegarder la migration
$generator->save($migration, 'add_user_profile_table');
```

### Via CLI (si disponible)
```bash
php bin/console mt:migration:generate --name="add_user_profile"
```

## Création manuelle

Créez une nouvelle migration manuellement :

```php
<?php

declare(strict_types=1);

namespace App\Migrations;

use MulerTech\Database\Schema\Migration;
use MulerTech\Database\Schema\Schema;
use MulerTech\Database\Schema\Table;

final class Migration20241213120000_AddUserProfileTable extends Migration
{
    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user_profiles');
        
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('user_id', 'integer', ['notnull' => true]);
        $table->addColumn('bio', 'text', ['notnull' => false]);
        $table->addColumn('avatar_url', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => true]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => true]);
        
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['user_id'], 'UNIQ_USER_PROFILE_USER_ID');
        $table->addForeignKeyConstraint('users', ['user_id'], ['id'], ['onDelete' => 'CASCADE']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user_profiles');
    }

    public function getDescription(): string
    {
        return 'Add user_profiles table with foreign key to users';
    }
}
```

## Structure d'une migration

### Classe de base
Toutes les migrations héritent de `MulerTech\Database\Schema\Migration` :

```php
abstract class Migration
{
    abstract public function up(Schema $schema): void;
    abstract public function down(Schema $schema): void;
    
    public function getDescription(): string
    {
        return '';
    }
    
    public function isTransactional(): bool
    {
        return true;
    }
}
```

### Nommage
- Format : `Migration{YYYYMMDDHHMMSS}_{DescriptiveName}`
- Exemple : `Migration20241213120000_AddUserProfileTable`

## Types d'opérations

### Création de table

```php
public function up(Schema $schema): void
{
    $table = $schema->createTable('products');
    
    $table->addColumn('id', 'integer', ['autoincrement' => true]);
    $table->addColumn('name', 'string', ['length' => 100, 'notnull' => true]);
    $table->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2]);
    
    $table->setPrimaryKey(['id']);
    $table->addIndex(['name'], 'IDX_PRODUCT_NAME');
}
```

### Modification de table

```php
public function up(Schema $schema): void
{
    $table = $schema->getTable('users');
    
    // Ajouter une colonne
    $table->addColumn('email_verified_at', 'datetime', ['notnull' => false]);
    
    // Modifier une colonne
    $table->changeColumn('email', ['length' => 180]);
    
    // Supprimer une colonne
    $table->dropColumn('old_field');
    
    // Ajouter un index
    $table->addIndex(['email_verified_at'], 'IDX_USER_EMAIL_VERIFIED');
}
```

### Gestion des contraintes

```php
public function up(Schema $schema): void
{
    $table = $schema->getTable('orders');
    
    // Ajouter une clé étrangère
    $table->addForeignKeyConstraint(
        'users',           // Table référencée
        ['user_id'],       // Colonnes locales
        ['id'],           // Colonnes référencées
        [
            'onDelete' => 'CASCADE',
            'onUpdate' => 'CASCADE'
        ]
    );
    
    // Ajouter une contrainte unique
    $table->addUniqueConstraint(['order_number'], 'UNIQ_ORDER_NUMBER');
}
```

### Insertion de données

```php
public function up(Schema $schema): void
{
    // Créer d'abord la table
    $table = $schema->createTable('categories');
    $table->addColumn('id', 'integer', ['autoincrement' => true]);
    $table->addColumn('name', 'string', ['length' => 50]);
    $table->setPrimaryKey(['id']);
    
    // Insérer des données de base
    $schema->getConnection()->executeStatement(
        "INSERT INTO categories (name) VALUES (?), (?), (?)",
        ['Electronics', 'Books', 'Clothing']
    );
}
```

## Bonnes pratiques

### 1. Migrations atomiques
Chaque migration doit représenter un changement logique unique :

```php
// ✅ Bon : Une seule responsabilité
class Migration20241213120000_AddUserEmailVerification extends Migration
{
    public function up(Schema $schema): void
    {
        $table = $schema->getTable('users');
        $table->addColumn('email_verified_at', 'datetime', ['notnull' => false]);
        $table->addColumn('email_verification_token', 'string', ['length' => 64, 'notnull' => false]);
    }
}

// ❌ Mauvais : Plusieurs responsabilités
class Migration20241213120000_UpdateUserAndAddProducts extends Migration
{
    // Mélange modifications users et création products
}
```

### 2. Toujours implémenter `down()`

```php
public function up(Schema $schema): void
{
    $table = $schema->createTable('user_sessions');
    // ... définition table
}

public function down(Schema $schema): void
{
    $schema->dropTable('user_sessions');
}
```

### 3. Migrations réversibles

```php
// ✅ Bon : Réversible
public function up(Schema $schema): void
{
    $table = $schema->getTable('users');
    $table->addColumn('middle_name', 'string', ['length' => 50, 'notnull' => false]);
}

public function down(Schema $schema): void
{
    $table = $schema->getTable('users');
    $table->dropColumn('middle_name');
}

// ❌ Attention : Perte de données
public function up(Schema $schema): void
{
    $table = $schema->getTable('users');
    $table->dropColumn('old_data'); // Données perdues !
}
```

### 4. Validation des données

```php
public function up(Schema $schema): void
{
    // Vérifier que les données existantes sont compatibles
    $connection = $schema->getConnection();
    $invalidRows = $connection->fetchOne(
        "SELECT COUNT(*) FROM users WHERE email IS NULL OR email = ''"
    );
    
    if ($invalidRows > 0) {
        throw new RuntimeException(
            "Cannot add NOT NULL constraint: {$invalidRows} rows have invalid email"
        );
    }
    
    $table = $schema->getTable('users');
    $table->changeColumn('email', ['notnull' => true]);
}
```

### 5. Documentation claire

```php
final class Migration20241213120000_OptimizeUserQueries extends Migration
{
    public function up(Schema $schema): void
    {
        $table = $schema->getTable('users');
        
        // Index composite pour les requêtes fréquentes de recherche d'utilisateurs actifs
        $table->addIndex(['status', 'last_login_at'], 'IDX_USER_ACTIVE_SEARCH');
        
        // Index pour les requêtes de tri par date de création
        $table->addIndex(['created_at'], 'IDX_USER_CREATED_AT');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('users');
        $table->dropIndex('IDX_USER_ACTIVE_SEARCH');
        $table->dropIndex('IDX_USER_CREATED_AT');
    }

    public function getDescription(): string
    {
        return 'Add indexes to optimize user search and sorting queries';
    }
}
```

## Migrations non-transactionnelles

Pour certaines opérations (comme les modifications de gros volumes) :

```php
final class Migration20241213120000_ConvertLargeTable extends Migration
{
    public function up(Schema $schema): void
    {
        // Cette migration sera exécutée sans transaction
        $connection = $schema->getConnection();
        
        // Traitement par batch pour éviter les timeouts
        $batchSize = 1000;
        $offset = 0;
        
        do {
            $rows = $connection->fetchAllAssociative(
                "SELECT id, old_data FROM large_table LIMIT ? OFFSET ?",
                [$batchSize, $offset]
            );
            
            foreach ($rows as $row) {
                $newData = $this->transformData($row['old_data']);
                $connection->executeStatement(
                    "UPDATE large_table SET new_data = ? WHERE id = ?",
                    [$newData, $row['id']]
                );
            }
            
            $offset += $batchSize;
        } while (count($rows) === $batchSize);
    }

    public function isTransactional(): bool
    {
        return false; // Désactive les transactions pour cette migration
    }
    
    private function transformData(string $oldData): string
    {
        // Logique de transformation
        return strtoupper($oldData);
    }
}
```

---

**Voir aussi :**
- [Exécution des migrations](running-migrations.md)
- [Comparaison de schémas](schema-diff.md)
- [Commandes CLI](migration-commands.md)
