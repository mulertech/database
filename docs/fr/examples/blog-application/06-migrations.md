# Migrations de Base de Données

Cette section explique comment gérer l'évolution du schéma de la base de données pour l'application blog.

## Table des matières

1. [Vue d'ensemble des migrations](#vue-densemble-des-migrations)
2. [Migrations initiales](#migrations-initiales)
3. [Évolution du schéma](#évolution-du-schéma)
4. [Rollback et versioning](#rollback-et-versioning)
5. [Bonnes pratiques](#bonnes-pratiques)

## Vue d'ensemble des migrations

Les migrations permettent de versionner et d'appliquer progressivement les modifications de schéma de base de données.

### Structure des migrations

```
database/
├── migrations/
│   ├── 2024_01_01_000000_create_users_table.php
│   ├── 2024_01_01_000001_create_categories_table.php
│   ├── 2024_01_01_000002_create_posts_table.php
│   ├── 2024_01_01_000003_create_tags_table.php
│   ├── 2024_01_01_000004_create_post_tags_table.php
│   ├── 2024_01_01_000005_create_comments_table.php
│   ├── 2024_02_15_120000_add_soft_deletes.php
│   └── 2024_03_10_140000_add_performance_indexes.php
└── seeds/
    ├── UserSeeder.php
    ├── CategorySeeder.php
    └── PostSeeder.php
```

## Migrations initiales

### 1. Table des utilisateurs

```php
<?php
// database/migrations/2024_01_01_000000_create_users_table.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('password');
            $table->enum('role', ['user', 'editor', 'admin'])->default('user');
            $table->string('avatar')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour les performances
            $table->index(['active', 'created_at']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('users');
    }
};
```

### 2. Table des catégories

```php
<?php
// database/migrations/2024_01_01_000001_create_categories_table.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#0066cc'); // Couleur hex
            $table->string('icon')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['active', 'sort_order']);
            $table->index('slug'); // Déjà unique mais explicit pour les requêtes
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('categories');
    }
};
```

### 3. Table des articles

```php
<?php
// database/migrations/2024_01_01_000002_create_posts_table.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->string('featured_image')->nullable();
            $table->json('meta_data')->nullable(); // SEO, options personnalisées
            $table->integer('view_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->timestamp('published_at')->nullable();
            
            // Clés étrangères
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour les performances
            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('slug');
            $table->fullText(['title', 'content']); // Recherche full-text
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('posts');
    }
};
```

### 4. Table des tags

```php
<?php
// database/migrations/2024_01_01_000003_create_tags_table.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#28a745');
            $table->text('description')->nullable();
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['usage_count', 'name']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('tags');
    }
};
```

### 5. Table pivot posts-tags

```php
<?php
// database/migrations/2024_01_01_000004_create_post_tags_table.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('post_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Contrainte d'unicité
            $table->unique(['post_id', 'tag_id']);
            
            // Index pour les performances
            $table->index('post_id');
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('post_tags');
    }
};
```

### 6. Table des commentaires

```php
<?php
// database/migrations/2024_01_01_000005_create_comments_table.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $this->schema->create('comments', function (Blueprint $table) {
            $table->id();
            $table->longText('content');
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])
                  ->default('pending');
            $table->string('author_ip', 45)->nullable();
            $table->string('author_user_agent')->nullable();
            
            // Relations
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('comments')
                  ->onDelete('cascade');
            
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['post_id', 'status', 'created_at']);
            $table->index(['parent_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('comments');
    }
};
```

## Évolution du schéma

### Migration : Ajout du soft delete

```php
<?php
// database/migrations/2024_02_15_120000_add_soft_deletes.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Ajout du soft delete aux tags
        $this->schema->table('tags', function (Blueprint $table) {
            $table->softDeletes();
        });
        
        // Ajout du soft delete aux commentaires
        $this->schema->table('comments', function (Blueprint $table) {
            $table->softDeletes();
        });
        
        // Ajout du soft delete aux catégories
        $this->schema->table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $this->schema->table('tags', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        
        $this->schema->table('comments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        
        $this->schema->table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

### Migration : Index de performance

```php
<?php
// database/migrations/2024_03_10_140000_add_performance_indexes.php

use MulerTech\Database\Schema\Migration\Migration;
use MulerTech\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Index composés pour les requêtes complexes
        $this->schema->table('posts', function (Blueprint $table) {
            $table->index(['category_id', 'status', 'published_at'], 'posts_category_status_published');
            $table->index(['user_id', 'status', 'published_at'], 'posts_user_status_published');
        });
        
        // Index pour le comptage des commentaires
        $this->schema->table('comments', function (Blueprint $table) {
            $table->index(['post_id', 'status'], 'comments_post_status');
        });
        
        // Index pour les statistiques
        $this->schema->table('post_tags', function (Blueprint $table) {
            $table->index(['tag_id', 'created_at'], 'post_tags_usage');
        });
    }

    public function down(): void
    {
        $this->schema->table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_category_status_published');
            $table->dropIndex('posts_user_status_published');
        });
        
        $this->schema->table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_post_status');
        });
        
        $this->schema->table('post_tags', function (Blueprint $table) {
            $table->dropIndex('post_tags_usage');
        });
    }
};
```

## Rollback et versioning

### Commandes de migration

```bash
# Appliquer toutes les migrations en attente
php bin/console mt:migration:migrate

# Appliquer une migration spécifique
php bin/console mt:migration:migrate --target=2024_01_01_000003

# Annuler la dernière migration
php bin/console mt:migration:rollback

# Annuler plusieurs migrations
php bin/console mt:migration:rollback --steps=3

# Voir le statut des migrations
php bin/console mt:migration:status

# Actualiser toutes les migrations (DROP + CREATE)
php bin/console mt:migration:refresh

# Réinitialiser complètement
php bin/console mt:migration:reset
```

### Script de rollback sécurisé

```php
<?php
// scripts/safe_rollback.php

use MulerTech\Database\Migration\MigrationManager;
use MulerTech\Database\Connection\ConnectionManager;

class SafeRollback
{
    private MigrationManager $migrationManager;
    
    public function __construct()
    {
        $this->migrationManager = new MigrationManager(
            ConnectionManager::getDefault()
        );
    }
    
    public function rollbackWithBackup(string $targetVersion): void
    {
        // 1. Créer une sauvegarde
        $backupFile = $this->createBackup();
        echo "Sauvegarde créée: {$backupFile}\n";
        
        try {
            // 2. Effectuer le rollback
            $this->migrationManager->rollbackTo($targetVersion);
            echo "Rollback réussi vers {$targetVersion}\n";
            
        } catch (\Exception $e) {
            // 3. Restaurer en cas d'erreur
            echo "Erreur durant le rollback: " . $e->getMessage() . "\n";
            echo "Restauration de la sauvegarde...\n";
            $this->restoreBackup($backupFile);
            throw $e;
        }
    }
    
    private function createBackup(): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "backup_blog_{$timestamp}.sql";
        
        exec("mysqldump blog_example > {$backupFile}");
        
        return $backupFile;
    }
    
    private function restoreBackup(string $backupFile): void
    {
        exec("mysql blog_example < {$backupFile}");
    }
}

// Usage
$rollback = new SafeRollback();
$rollback->rollbackWithBackup('2024_01_01_000003');
```

## Bonnes pratiques

### 1. Nommage des migrations

```php
// ✅ Bon : descriptif et daté
2024_01_15_143000_add_email_verification_to_users.php
2024_01_20_091500_create_user_profiles_table.php
2024_02_01_160000_add_indexes_for_search_performance.php

// ❌ Mauvais : vague ou non daté
add_column.php
update_table.php
fix.php
```

### 2. Migrations atomiques

```php
// ✅ Une responsabilité par migration
class AddEmailVerificationToUsers extends Migration
{
    public function up(): void
    {
        $this->schema->table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
        });
    }
}

// ❌ Trop de changements dans une migration
class UpdateUserSystemCompletely extends Migration
{
    public function up(): void
    {
        // Modification de 10 tables différentes...
    }
}
```

### 3. Gestion des données sensibles

```php
class MigrateUserPasswords extends Migration
{
    public function up(): void
    {
        // ✅ Migration avec sauvegarde des données
        $this->connection->beginTransaction();
        
        try {
            // Sauvegarder les anciens mots de passe
            $this->connection->exec("
                CREATE TABLE user_passwords_backup AS 
                SELECT id, password FROM users
            ");
            
            // Effectuer la migration
            $this->schema->table('users', function (Blueprint $table) {
                $table->string('password', 255)->change();
            });
            
            $this->connection->commit();
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }
    
    public function down(): void
    {
        // Restaurer depuis la sauvegarde
        $this->connection->exec("
            UPDATE users u 
            JOIN user_passwords_backup b ON u.id = b.id 
            SET u.password = b.password
        ");
        
        $this->connection->exec("DROP TABLE user_passwords_backup");
    }
}
```

### 4. Tests des migrations

```php
<?php
// tests/Database/Migrations/CreateUsersTableTest.php

use MulerTech\Database\Testing\DatabaseTestCase;

class CreateUsersTableTest extends DatabaseTestCase
{
    public function testMigrationCreatesUsersTable(): void
    {
        // Exécuter la migration
        $this->runMigration('2024_01_01_000000_create_users_table');
        
        // Vérifier que la table existe
        $this->assertTrue($this->schema->hasTable('users'));
        
        // Vérifier les colonnes
        $this->assertTrue($this->schema->hasColumn('users', 'email'));
        $this->assertTrue($this->schema->hasColumn('users', 'password'));
        
        // Vérifier les index
        $this->assertTrue($this->schema->hasIndex('users', 'users_email_unique'));
    }
    
    public function testRollbackDropsUsersTable(): void
    {
        // Exécuter puis annuler la migration
        $this->runMigration('2024_01_01_000000_create_users_table');
        $this->rollbackMigration('2024_01_01_000000_create_users_table');
        
        // Vérifier que la table n'existe plus
        $this->assertFalse($this->schema->hasTable('users'));
    }
}
```

### 5. Documentation des changements

```php
<?php
/**
 * Migration: Ajout du système de commentaires hiérarchiques
 * 
 * Cette migration ajoute la possibilité d'avoir des commentaires
 * en réponse à d'autres commentaires (threading).
 * 
 * Changements:
 * - Ajout de la colonne parent_id à la table comments
 * - Création d'un index pour optimiser les requêtes hiérarchiques
 * - Contrainte de clé étrangère auto-référentielle
 * 
 * Impact performance: Négligeable
 * Compatibilité: Rétrocompatible
 * 
 * @author Jean Dupont <jean@example.com>
 * @date 2024-02-15
 */
class AddThreadingToComments extends Migration
{
    // Implementation...
}
```

---

Les migrations permettent une évolution contrôlée et sécurisée du schéma de base de données, essentielle pour maintenir l'intégrité des données en production.
